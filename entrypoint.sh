#!/bin/sh

# Naviga nella directory del progetto Laravel
cd /var/www/html/arkvel

# Se il file .env non esiste, copialo da .env.example
if [ ! -f .env ]; then
    echo "Copying default .env file..."
    cp .env.example .env
fi

# Carica le variabili d'ambiente
export $(grep -v '^#' .env | xargs)

# Installa le dipendenze di Laravel se non sono già installate
if [ ! -d "vendor" ]; then
    echo "Installing Composer dependencies..."
    composer install
fi

# Avvia PHP-FPM
exec php-fpm
