# Etapa 1: se usa la imagen oficial de Composer solo para copiar el binario
FROM composer:2 AS composer_stage

FROM php:8.2-apache

RUN docker-php-ext-install pdo pdo_mysql
RUN apt-get update && apt-get install -y --no-install-recommends libonig-dev \
    && docker-php-ext-install mbstring \
    && rm -rf /var/lib/apt/lists/*

COPY --from=composer_stage /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html

# Se copia primero solo composer.json para aprovechar el cache de Docker:
# si el código cambia pero las dependencias no, no se reinstalan de nuevo.
COPY composer.json ./
RUN composer install --no-dev --no-interaction --optimize-autoloader

COPY . /var/www/html/

RUN a2enmod rewrite

EXPOSE 80
