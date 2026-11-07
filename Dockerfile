FROM php:8.2-apache

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

# Install required PHP/system dependencies
RUN apt-get update \
    && apt-get install -y --no-install-recommends \
        libonig-dev \
        libcurl4-openssl-dev \
        unzip \
    && docker-php-ext-install \
        pdo_mysql \
        mbstring \
        curl \
    && rm -rf /var/lib/apt/lists/*

# Copy the project into the Apache web root
COPY . /var/www/html/

WORKDIR /var/www/html

# Install Composer dependencies
RUN composer install \
    --no-dev \
    --prefer-dist \
    --no-interaction \
    --no-progress

# Generate the runtime database connection file from the environment-based template
RUN cp database-config/connection-template.php database-config/connection.php

# Enable Apache rewrite support
RUN a2enmod rewrite

# Configure Apache and required writable directories
RUN printf '<Directory /var/www/html>\nAllowOverride All\nRequire all granted\n</Directory>\n' \
    > /etc/apache2/conf-available/eventnest.conf \
    && a2enconf eventnest \
    && mkdir -p /var/www/eventnest-private/organiser-documents \
    && mkdir -p /var/www/html/database-config/runtime \
    && chown www-data:www-data /var/www/html/database-config/runtime \
    && chown -R www-data:www-data \
        /var/www/eventnest-private \
        /var/www/html/uploaded-content

# TEMPORARY SIT DATABASE UPDATE
#
# Start Apache first so Render detects an open web port.
# Then update and verify the Aiven SIT database in the same container.
#
# Sequence:
# 1. Start Apache
# 2. Apply presentation/demo data
# 3. Add presentation reaction data
# 4. Apply final public-facing presentation wording
# 5. Verify the resulting SIT database
# 6. Keep Apache running
#
# Remove this temporary CMD after the SIT database has been
# successfully updated and verified.

CMD ["/bin/sh", "-c", "apache2-foreground & APACHE_PID=$!; (php database/apply_images.php || true); php database/seed_presentation.php --apply && php database/seed_presentation.php --reactions && php database/update_presentation_copy.php && (php database/apply_images.php || true) && php database/verify_presentation.php; STATUS=$?; if [ $STATUS -ne 0 ]; then kill $APACHE_PID 2>/dev/null || true; exit $STATUS; fi; wait $APACHE_PID"]