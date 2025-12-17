FROM php:8.2-cli

# Paquetes + driver Postgres para PHP
RUN apt-get update && apt-get install -y \
    git unzip libpq-dev \
    && docker-php-ext-install pdo_pgsql pgsql

WORKDIR /app
COPY . /app

# Composer
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

RUN composer install --no-dev --optimize-autoloader

# Railway usa PORT
CMD sh -c "\
    php artisan key:generate --force || true && \
    php artisan optimize:clear && \
    php artisan migrate --force || true && \
    php -S 0.0.0.0:${PORT:-8080} -t public "
