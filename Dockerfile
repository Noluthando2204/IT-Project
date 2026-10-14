
# Use official PHP image with Apache
FROM php:8.2-apache

# Install MySQL extensions for Aiven
RUN docker-php-ext-install mysqli pdo pdo_mysql

# Enable Apache rewrite rules
RUN a2enmod rewrite

# Copy project files into Apache's web folder
COPY . /var/www/html/

# Expose web port
EXPOSE 80
