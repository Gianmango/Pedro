FROM php:8.3-fpm-alpine

RUN apk add --no-cache libpq freetype libjpeg-turbo libpng \
    && apk add --no-cache --virtual .build-deps \
        $PHPIZE_DEPS \
        postgresql-dev \
        freetype-dev \
        libjpeg-turbo-dev \
        libpng-dev \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j"$(nproc)" pdo_pgsql gd \
    && apk del .build-deps

WORKDIR /var/www
COPY . .

CMD ["php-fpm", "-F"]