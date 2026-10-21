FROM php:8.2-apache

# Match the application's PDO queries and UTF-8 registration validation.
RUN apt-get update \
    && apt-get install -y --no-install-recommends libonig-dev libcurl4-openssl-dev \
    && docker-php-ext-install pdo_mysql mbstring curl \
    && rm -rf /var/lib/apt/lists/*

# Copy the project into the web root
COPY . /var/www/html/

# Generate connection.php from the template
RUN cp database-config/connection-template.php database-config/connection.php

# Enable Apache rewrite
RUN a2enmod rewrite
