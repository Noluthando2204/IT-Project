FROM php:8.2-apache

COPY . /var/www/html/

RUN cp database-config/connection-template.php database-config/connection.php

RUN a2enmod rewrite
