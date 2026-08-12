FROM php:8.4-cli-bookworm

ENV DEBIAN_FRONTEND=noninteractive \
    COMPOSER_ALLOW_SUPERUSER=1 \
    PYTHONUNBUFFERED=1

RUN apt-get update && apt-get install -y --no-install-recommends \
    git unzip curl \
    libsqlite3-dev libzip-dev libpng-dev libonig-dev \
    python3 python3-pip python3-venv \
    && docker-php-ext-install pdo_sqlite pdo_mysql zip bcmath \
    && rm -rf /var/lib/apt/lists/*

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html

COPY composer.json composer.lock ./
RUN composer install --no-dev --prefer-dist --no-interaction --no-scripts

COPY python/requirements.txt python/requirements.txt
RUN pip3 install --break-system-packages --no-cache-dir -r python/requirements.txt

COPY . .
RUN composer dump-autoload --optimize \
    && mkdir -p storage/framework/{cache,sessions,views} storage/logs storage/app/medcast database \
    && chmod -R 775 storage bootstrap/cache database

COPY docker/entrypoint.sh /entrypoint.sh
RUN chmod +x /entrypoint.sh \
    && ln -sf /usr/bin/python3 /usr/local/bin/python

EXPOSE 8000
ENTRYPOINT ["/entrypoint.sh"]
