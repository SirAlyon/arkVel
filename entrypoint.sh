#!/bin/sh
set -e

cd /var/www/html/arkvel

# Copy default .env on first boot
if [ ! -f .env ]; then
    echo "Copying default .env file..."
    cp .env.example .env
fi

# Install PHP dependencies if missing
if [ ! -d "vendor" ]; then
    echo "Installing Composer dependencies..."
    composer install --no-interaction --prefer-dist
fi

# Ensure storage is writable
mkdir -p storage/framework/cache storage/framework/sessions storage/framework/views bootstrap/cache
chmod -R ug+rw storage bootstrap/cache || true

exec "$@"
