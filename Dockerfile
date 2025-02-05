# Usa PHP 8.3 con Alpine
FROM php:8.3-fpm-alpine

# Installa pacchetti base
RUN apk add --no-cache \
    git \
    curl \
    zip \
    unzip \
    libintl \
    icu-dev \
    icu-libs \
    nodejs \
    npm \
    mysql-client

# Installa le estensioni PHP necessarie
RUN docker-php-ext-install \
    mysqli \
    pdo \
    pdo_mysql \
    intl

# Copia i file di Laravel nel container
WORKDIR /var/www/html/arkvel

# Installa Composer globalmente
RUN curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer

# Imposta l'entrypoint e avvia PHP-FPM
COPY entrypoint.sh /entrypoint.sh
COPY seed.sh /seed.sh

RUN chmod +x /entrypoint.sh
ENTRYPOINT ["/entrypoint.sh"]
CMD ["php-fpm"]
