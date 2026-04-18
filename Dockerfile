# Stage 1: Composer
FROM composer:2.7 AS composer
WORKDIR /app
COPY composer.json composer.lock ./
RUN composer install --no-dev --no-scripts --no-autoloader --prefer-dist --ignore-platform-reqs
COPY . .
RUN composer dump-autoload --optimize --no-dev

# Stage 2: Node (Frontend Build)
FROM node:20-alpine AS node
WORKDIR /app
COPY package.json package-lock.json ./
RUN npm ci
COPY . .
# Copy vendor dari stage 1 jika ada assets di sana
COPY --from=composer /app/vendor ./vendor
RUN npm run build

# Stage 3: Final Production Image
FROM php:8.3-fpm-alpine
RUN apk add --no-cache libpq-dev libzip-dev oniguruma-dev icu-dev nginx supervisor \
    && docker-php-ext-install pdo_pgsql pgsql zip mbstring intl pcntl bcmath \
    && apk add --no-cache --virtual .build-deps autoconf g++ make \
    && pecl install redis && docker-php-ext-enable redis \
    && apk del .build-deps

WORKDIR /var/www/html
COPY --from=composer /app /var/www/html
COPY --from=node /app/public/build /var/www/html/public/build

# Copy configs
COPY docker/nginx.conf /etc/nginx/nginx.conf
COPY docker/supervisord.conf /etc/supervisor/conf.d/supervisord.conf
COPY docker/php.ini /usr/local/etc/php/conf.d/app.ini
COPY docker/entrypoint.sh /entrypoint.sh
RUN chmod +x /entrypoint.sh

RUN chown -R www-data:www-data /var/www/html/storage /var/www/html/bootstrap/cache \
    && chmod -R 775 /var/www/html/storage /var/www/html/bootstrap/cache

EXPOSE 80
ENTRYPOINT ["/entrypoint.sh"]
