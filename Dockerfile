ARG PHP_VERSION=8.3

# Stage 1: FPM-specific environment
FROM php:${PHP_VERSION}-fpm-alpine AS fpm

RUN apk add --no-cache \
    zlib-dev \
    libpng-dev \
    libzip-dev \
    icu-dev \
    oniguruma-dev \
    gcc \
    g++ \
    make \
    autoconf \
    bash \
    && docker-php-ext-install gd zip intl pdo pdo_mysql

WORKDIR /var/www/html

# Stage 2: CLI-specific environment (WebSocket)
FROM php:${PHP_VERSION}-cli-alpine AS websocket

RUN apk add --no-cache \
    zlib-dev \
    libpng-dev \
    libzip-dev \
    icu-dev \
    oniguruma-dev \
    supervisor \
    gcc \
    g++ \
    make \
    autoconf \
    bash \
    && docker-php-ext-install pcntl

WORKDIR /var/www/html

COPY docker/supervisord.conf /etc/supervisor.d/websocket.conf