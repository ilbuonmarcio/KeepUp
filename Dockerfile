FROM node:22-alpine AS assets

WORKDIR /app

COPY package.json package-lock.json ./
RUN npm ci

COPY postcss.config.js tailwind.config.js vite.config.js ./
COPY resources ./resources
RUN npm run build


FROM composer:2 AS dependencies

WORKDIR /var/www/html

COPY composer.json composer.lock ./
RUN composer install \
    --no-dev \
    --no-interaction \
    --no-progress \
    --no-scripts \
    --prefer-dist

COPY . .
RUN composer dump-autoload --classmap-authoritative --no-dev --no-interaction


FROM php:8.4-apache-trixie AS runtime

RUN apt-get update \
    && apt-get install -y --no-install-recommends \
        curl \
        gosu \
        libonig-dev \
        openssh-client \
        sshpass \
    && docker-php-ext-install -j"$(nproc)" mbstring pcntl pdo_mysql \
    && a2enmod headers rewrite \
    && rm -rf /var/lib/apt/lists/*

WORKDIR /var/www/html

COPY . .
COPY --from=dependencies /var/www/html/vendor ./vendor
COPY --from=assets /app/public/build ./public/build
COPY docker/apache-vhost.conf /etc/apache2/sites-available/000-default.conf
COPY docker/entrypoint.sh /usr/local/bin/keepup-entrypoint
COPY docker/start-app.sh /usr/local/bin/keepup-start-app

RUN chmod +x /usr/local/bin/keepup-entrypoint /usr/local/bin/keepup-start-app \
    && mkdir -p \
        storage/app/private/ssh_private_keys \
        storage/framework/cache/data \
        storage/framework/sessions \
        storage/framework/views \
        storage/logs \
        bootstrap/cache \
    && chown -R www-data:www-data storage bootstrap/cache \
    && chmod -R u+rwX,g+rwX,o-rwx storage bootstrap/cache

ENTRYPOINT ["keepup-entrypoint"]
CMD ["keepup-start-app"]
