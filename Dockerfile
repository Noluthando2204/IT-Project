FROM php:8.2-apache

# Install the mysqli extension (this is the missing piece)
RUN docker-php-ext-install mysqli && docker-php-ext-enable mysqli

# Copy the project into the web root
COPY . /var/www/html/

# Generate connection.php from the template
RUN cp database-config/connection-template.php database-config/connection.php

# Enable Apache rewrite
RUN a2enmod rewrite
