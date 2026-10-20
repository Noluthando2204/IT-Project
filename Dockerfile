FROM php:8.2-apache

# 1. Install mysqli extension (Fixes the Class "mysqli" not found error)
RUN docker-php-ext-install mysqli && docker-php-ext-enable mysqli

# 2. Copy all project files (including ca.pem and connection-template.php)
COPY . /var/www/html/

# 3. Generate connection.php from the template (Since connection.php is gitignored)
RUN cp /var/www/html/database-config/connection-template.php /var/www/html/database-config/connection.php

# 4. Enable Apache rewrite module
RUN a2enmod rewrite
