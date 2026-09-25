# Etapa 1: se usa la imagen oficial de Composer solo para copiar el binario
FROM composer:2 AS composer_stage

FROM php:8.2-apache

RUN docker-php-ext-install pdo pdo_mysql
RUN apt-get update && apt-get install -y --no-install-recommends libonig-dev \
    && docker-php-ext-install mbstring \
    && rm -rf /var/lib/apt/lists/*

COPY --from=composer_stage /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html

# Se copia todo el código antes de composer install: el autoload usa
# "classmap" para connection.php y services.php, que necesita encontrar
# esos archivos físicamente en ese momento (no solo composer.json).
COPY . /var/www/html/
RUN composer install --no-dev --no-interaction --optimize-autoloader

RUN a2enmod rewrite

EXPOSE 80
