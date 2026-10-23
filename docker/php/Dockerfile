# PHP-FPM para la API de Datecito (igual en local y producción).
FROM php:8.4-fpm-alpine

RUN apk add --no-cache \
        icu-libs libzip libpng libjpeg-turbo libwebp freetype \
        $PHPIZE_DEPS icu-dev libzip-dev libpng-dev libjpeg-turbo-dev libwebp-dev freetype-dev linux-headers \
    && docker-php-ext-configure gd --with-jpeg --with-webp --with-freetype \
    && docker-php-ext-install -j"$(nproc)" pdo_mysql intl zip gd bcmath opcache pcntl exif \
    && pecl install redis \
    && docker-php-ext-enable redis \
    && apk del $PHPIZE_DEPS icu-dev libzip-dev libpng-dev libjpeg-turbo-dev libwebp-dev freetype-dev linux-headers

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer
COPY docker/php/php.ini /usr/local/etc/php/conf.d/zz-datecito.ini

WORKDIR /var/www/html
