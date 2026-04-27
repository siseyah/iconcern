FROM php:8.2-apache

# Copy project files
COPY . /var/www/html/

# Enable Apache rewrite (optional but useful)
RUN a2enmod rewrite