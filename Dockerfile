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
COPY deployment/apache-privacy.conf /etc/apache2/conf-available/eventnest-privacy.conf
RUN a2enconf eventnest-privacy

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

# Keep a read-only seed copy of approved repository assets. At runtime the
# entrypoint exposes /var/data through the paths the PHP application already
# uses, allowing one Render persistent disk to preserve all mutable files.
RUN mkdir -p /var/www/eventnest-seed \
    && cp -a /var/www/html/uploaded-content /var/www/eventnest-seed/uploaded-content \
    && chmod +x /var/www/html/deployment/render-entrypoint.sh

# SIT startup applies additive schema only; catalogue/data changes stay explicit.
ENTRYPOINT ["/var/www/html/deployment/render-entrypoint.sh"]
CMD ["apache2-foreground"]
