FROM php:8.2-apache

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

# Match the application's PDO queries and UTF-8 registration validation.
RUN apt-get update \
    && apt-get install -y --no-install-recommends libonig-dev libcurl4-openssl-dev unzip \
    && docker-php-ext-install pdo_mysql mbstring curl \
    && rm -rf /var/lib/apt/lists/*

# Copy the project into the web root
COPY . /var/www/html/

WORKDIR /var/www/html
RUN composer install --no-dev --prefer-dist --no-interaction --no-progress

# Generate connection.php from the template
RUN cp database-config/connection-template.php database-config/connection.php

# Enable Apache rewrite
RUN a2enmod rewrite

# Render terminates HTTPS at its proxy. Apache still needs these protection files.
RUN printf '<Directory /var/www/html>\nAllowOverride All\nRequire all granted\n</Directory>\n' > /etc/apache2/conf-available/eventnest.conf \
    && a2enconf eventnest \
    && mkdir -p /var/www/eventnest-private/organiser-documents \
    && chown -R www-data:www-data /var/www/eventnest-private /var/www/html/uploaded-content
