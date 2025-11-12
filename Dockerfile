FROM php:8.2-fpm

RUN apt-get update && apt-get install -y unzip git libpq-dev

RUN docker-php-ext-install pdo pdo_pgsql

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

EXPOSE 9000
