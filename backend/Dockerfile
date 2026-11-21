FROM composer:2 AS composer

FROM php:8.4-apache

RUN apt-get update && apt-get install -y --no-install-recommends \
    ca-certificates git unzip supervisor libicu-dev libzip-dev libonig-dev \
    libpng-dev libjpeg62-turbo-dev libfreetype6-dev \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j2 pdo_mysql bcmath intl zip pcntl gd mbstring \
    && a2enmod rewrite headers \
    && rm -rf /var/lib/apt/lists/*

COPY --from=composer /usr/bin/composer /usr/local/bin/composer
WORKDIR /var/www/html
COPY composer.json composer.lock ./
RUN composer install --no-dev --prefer-dist --no-interaction --no-scripts --no-autoloader
COPY . .
RUN composer dump-autoload --no-dev --optimize --no-interaction \
    && mkdir -p storage/framework/cache/data storage/framework/sessions storage/framework/views storage/logs storage/app/private/ml \
    && chown -R www-data:www-data storage bootstrap/cache \
    && cp deploy/apache.conf /etc/apache2/sites-available/000-default.conf \
    && cp deploy/supervisord.conf /etc/supervisor/conf.d/tungku.conf \
    && chmod +x deploy/start.sh

ENV APP_ENV=production APP_DEBUG=false LOG_CHANNEL=stderr \
    SESSION_DRIVER=database CACHE_STORE=database QUEUE_CONNECTION=database \
    SESSION_SECURE_COOKIE=true SESSION_SAME_SITE=lax
EXPOSE 10000
CMD ["/var/www/html/deploy/start.sh"]
