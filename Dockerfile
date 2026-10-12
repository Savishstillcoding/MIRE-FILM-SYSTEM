# syntax=docker/dockerfile:1.7
# Targets: dev (bind-mounted code), prod (code baked in), web (nginx + built public/)
ARG PHP_VERSION=8.4

# ---------- 1. Composer dependencies (prod only, cached layer) ----------
FROM composer:2 AS vendor
WORKDIR /app
COPY composer.json composer.lock ./
RUN composer install --no-dev --no-interaction --no-progress --no-scripts \
    --prefer-dist --optimize-autoloader

# ---------- 1b. Front-end assets (Vite + Bootstrap) ----------
FROM node:22-alpine AS assets
WORKDIR /app
COPY package.json package-lock.json* .npmrc* ./
RUN npm install
COPY . .
RUN npm run build

# ---------- 2. Shared PHP-FPM base ----------
FROM php:${PHP_VERSION}-fpm-alpine AS base
ADD --chmod=0755 https://github.com/mlocati/docker-php-extension-installer/releases/latest/download/install-php-extensions /usr/local/bin/
RUN install-php-extensions pdo_mysql mbstring zip intl bcmath opcache pcntl gd exif \
    && apk add --no-cache su-exec curl
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer
COPY docker/entrypoint.sh /usr/local/bin/entrypoint
RUN chmod +x /usr/local/bin/entrypoint
WORKDIR /var/www/html
ENTRYPOINT ["entrypoint"]
CMD ["php-fpm"]

# ---------- 3. Development ----------
FROM base AS dev
ARG UID=1000
ARG GID=1000
RUN apk add --no-cache shadow git unzip \
    && groupmod -o -g ${GID} www-data && usermod -o -u ${UID} -g ${GID} www-data
COPY docker/php/php.dev.ini $PHP_INI_DIR/conf.d/99-app.ini
ENV APP_ENV=local

# ---------- 4. Production app ----------
FROM base AS prod
COPY docker/php/php.prod.ini $PHP_INI_DIR/conf.d/99-app.ini
COPY --from=vendor /app/vendor ./vendor
COPY --chown=www-data:www-data . .
COPY --from=assets --chown=www-data:www-data /app/public/build ./public/build
RUN composer dump-autoload --optimize --classmap-authoritative --no-dev --no-scripts \
    && php artisan package:discover --ansi \
    && mkdir -p storage/framework/cache storage/framework/sessions storage/framework/views storage/logs bootstrap/cache \
    && chown -R www-data:www-data storage bootstrap/cache \
    && chmod -R ug+rwX storage bootstrap/cache
ENV APP_ENV=production APP_DEBUG=false
HEALTHCHECK --interval=30s --timeout=5s --start-period=20s --retries=3 \
    CMD pgrep -x php-fpm >/dev/null || exit 1

# ---------- 5. Production web (nginx with static assets baked in) ----------
FROM nginx:1.27-alpine AS web
COPY docker/nginx/default.conf /etc/nginx/conf.d/default.conf
COPY --from=prod /var/www/html/public /var/www/html/public
HEALTHCHECK --interval=30s --timeout=5s --retries=3 \
    CMD wget -qO- http://127.0.0.1/up >/dev/null 2>&1 || exit 1
