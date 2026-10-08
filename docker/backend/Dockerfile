FROM php:8.3-cli-alpine

RUN apk add --no-cache \
        bash curl git unzip icu-dev libpq-dev oniguruma-dev libzip-dev \
        $PHPIZE_DEPS \
    && docker-php-ext-install -j$(nproc) pdo_pgsql pcntl intl zip bcmath \
    && apk del $PHPIZE_DEPS

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html

COPY backend/composer.json backend/composer.lock ./
RUN composer install --no-dev --prefer-dist --no-interaction --no-scripts --optimize-autoloader

COPY backend/ ./
# mkdir BEFORE dump-autoload: package:discover needs bootstrap/cache present/writable (D1).
RUN mkdir -p storage/framework/cache storage/framework/sessions storage/framework/views storage/logs bootstrap/cache \
    && chmod -R ug+rwx storage bootstrap/cache \
    && composer dump-autoload --optimize

COPY docker/backend/entrypoint.sh /usr/local/bin/entrypoint.sh
RUN chmod +x /usr/local/bin/entrypoint.sh

ENV APP_ENV=production \
    APP_DEBUG=false \
    LOG_CHANNEL=stderr

EXPOSE 8000
ENTRYPOINT ["/usr/local/bin/entrypoint.sh"]
CMD ["php", "artisan", "serve", "--host=0.0.0.0", "--port=8000"]
