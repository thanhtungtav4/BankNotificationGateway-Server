FROM php:8.3-fpm-alpine

RUN apk add --no-cache bash git curl icu-dev libzip-dev oniguruma-dev zip unzip \
    && docker-php-ext-install intl mbstring pdo_mysql zip bcmath

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer
WORKDIR /var/www/html
CMD ["php-fpm"]
