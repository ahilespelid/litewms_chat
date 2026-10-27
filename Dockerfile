# syntax=docker/dockerfile:1

########################################
# 1. Фронтенд (Vite/Tailwind) + локальная копия Swagger UI
########################################
FROM node:22-alpine AS frontend
WORKDIR /app
COPY package.json package-lock.json ./
RUN npm ci --no-audit --no-fund
COPY resources ./resources
COPY scripts ./scripts
COPY vite.config.js ./
COPY public ./public
RUN npm run build

########################################
# 2. PHP-зависимости
########################################
FROM dunglas/frankenphp:php8.5 AS vendor
WORKDIR /app
RUN install-php-extensions pdo_mysql bcmath pcntl intl opcache zip redis >/dev/null
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer
COPY composer.json composer.lock ./
RUN composer install --no-dev --no-scripts --no-autoloader --prefer-dist --no-interaction
COPY . .
COPY --from=frontend /app/public/build ./public/build
# Swagger UI кладётся рядом (npm run build -> scripts/copy-swagger.mjs); public/vendor в git не хранится
COPY --from=frontend /app/public/vendor ./public/vendor
RUN composer dump-autoload --optimize --no-dev

########################################
# 3. Рантайм (общий образ для app / reverb / queue / scheduler)
########################################
FROM dunglas/frankenphp:php8.5 AS runtime
RUN install-php-extensions pdo_mysql bcmath pcntl intl opcache redis >/dev/null \
    && rm -rf /var/lib/apt/lists/* /tmp/*

# Файлы и голосовые до 25 МБ (config/chat.php: CHAT_FILE_MAX_KB).
RUN { echo 'upload_max_filesize=30M'; echo 'post_max_size=32M'; echo 'max_execution_time=120'; } \
    > /usr/local/etc/php/conf.d/chat-uploads.ini

WORKDIR /app
COPY --from=vendor /app /app
COPY docker/entrypoint.sh /entrypoint.sh

RUN chmod +x /entrypoint.sh \
    && mkdir -p storage/framework/{cache,sessions,testing,views} storage/logs bootstrap/cache \
    && chown -R www-data:www-data /app/storage /app/bootstrap/cache

ENV SERVER_NAME=":80" \
    APP_RUNTIME_ENV=production \
    OCTANE_SERVER=frankenphp

EXPOSE 80 8080
ENTRYPOINT ["/entrypoint.sh"]
CMD ["app"]
