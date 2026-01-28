FROM php:8.3-fpm-alpine AS base

RUN apk add --no-cache \
    curl \
    libzip-dev \
    oniguruma-dev \
    icu-dev \
    && docker-php-ext-install pdo pdo_mysql mbstring zip intl opcache

# Production-optimized php.ini
RUN cp /usr/local/etc/php/php.ini-production /usr/local/etc/php/php.ini

WORKDIR /var/www

# ---------- Composer install ----------
FROM base AS vendor

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer
COPY composer.json composer.lock* ./
RUN composer install --no-dev --no-scripts --no-autoloader --prefer-dist

COPY . .
RUN composer dump-autoload --optimize --no-dev

# ---------- Production image ----------
FROM base AS production

COPY --from=vendor /var/www /var/www

RUN chown -R www-data:www-data /var/www/storage \
    && chmod -R 775 /var/www/storage

EXPOSE 9000
CMD ["php-fpm"]
