FROM php:8.3-fpm-bookworm

ARG WWWUSER=1000
ARG WWWGROUP=1000

ENV DEBIAN_FRONTEND=noninteractive

RUN apt-get update && apt-get install -y --no-install-recommends \
        git curl unzip ca-certificates gnupg \
        libpq-dev libzip-dev libonig-dev libxml2-dev \
        libpng-dev libjpeg-dev libfreetype6-dev libicu-dev \
        zip iputils-ping \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j"$(nproc)" \
        pdo_pgsql pgsql bcmath intl mbstring zip gd opcache pcntl \
    && pecl install redis \
    && docker-php-ext-enable redis \
    && apt-get clean && rm -rf /var/lib/apt/lists/*

# Composer
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

RUN groupadd -g ${WWWGROUP} serveo \
    && useradd -m -u ${WWWUSER} -g serveo -s /bin/bash serveo

WORKDIR /var/www/html

# Copy composer files first for better layer caching
COPY --chown=serveo:serveo composer.json composer.lock /var/www/html/

RUN composer install --no-interaction --no-dev --optimize-autoloader --no-scripts

# Copy the rest of the application
COPY --chown=serveo:serveo . /var/www/html

RUN mkdir -p storage/framework/{cache,sessions,views} bootstrap/cache \
    && chown -R serveo:serveo /var/www/html/storage /var/www/html/bootstrap/cache \
    && composer dump-autoload --no-dev --optimize \
    && php artisan optimize

USER serveo

EXPOSE 9000

CMD ["php-fpm"]
