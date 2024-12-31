FROM php:8.3-fpm

# Install required PHP extensions
RUN apt update && apt install -y zlib1g zlib1g-dev libpng-dev libzip-dev libicu-dev \
    && docker-php-ext-install gd zip intl pdo pdo_mysql

WORKDIR /var/www/html
