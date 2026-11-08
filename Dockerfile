# ArkVel — PHP 8.4 FPM (Alpine)
FROM php:8.4-fpm-alpine

# System dependencies
RUN apk add --no-cache \
    git curl bash zip unzip \
    # icu-data-full: senza, l'ICU di Alpine ha solo l'inglese e Number::format(locale: 'it')
    # formatta all'inglese (1,240 invece di 1.240).
    icu-dev icu-libs icu-data-full \
    libzip-dev \
    libpng-dev libjpeg-turbo-dev freetype-dev \
    postgresql-dev \
    oniguruma-dev \
    linux-headers \
    $PHPIZE_DEPS \
    nodejs npm

# PHP extensions
RUN docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j"$(nproc)" \
        pdo pdo_pgsql pgsql \
        intl gd zip bcmath exif pcntl opcache sockets \
    && pecl install redis \
    && docker-php-ext-enable redis \
    && apk del $PHPIZE_DEPS

# Composer
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

# Sensible PHP defaults for dev
RUN { \
        echo 'memory_limit=512M'; \
        echo 'upload_max_filesize=20M'; \
        echo 'post_max_size=25M'; \
        echo 'opcache.enable=1'; \
        echo 'opcache.enable_cli=0'; \
    } > /usr/local/etc/php/conf.d/arkvel.ini

WORKDIR /var/www/html/arkvel

COPY entrypoint.sh /entrypoint.sh
RUN chmod +x /entrypoint.sh

ENTRYPOINT ["/entrypoint.sh"]
CMD ["php-fpm"]
