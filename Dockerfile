FROM php:8.2-apache

# Copy project files into the container
COPY . /var/www/html/

# Automatically create connection.php from the template in the root directory
RUN cp connection-template.php database-config/connection.php

# Enable Apache mod_rewrite
RUN a2enmod rewrite
