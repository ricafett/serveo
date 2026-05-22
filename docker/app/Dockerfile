FROM php:8.4-fpm-bookworm

ARG WWWUSER=1000
ARG WWWGROUP=1000

ENV DEBIAN_FRONTEND=noninteractive

# Set timezone
ENV TZ=Europe/Lisbon
RUN ln -snf /usr/share/zoneinfo/$TZ /etc/localtime && echo $TZ > /etc/timezone

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

# Copy app as ROOT so composer can write composer.lock
COPY . /var/www/html

# Create storage/cache directories needed at runtime
RUN mkdir -p storage/framework/cache \
             storage/framework/sessions \
             storage/framework/views \
             storage/logs \
             bootstrap/cache

# Install dependencies (skip post-autoload scripts — entrypoint handles optimization at runtime)
RUN composer install --no-dev --no-interaction --no-progress --no-scripts --optimize-autoloader

# Set final permissions
RUN chown -R serveo:serveo /var/www/html

# Entrypoint: runs migrate + optimize at runtime with real env vars, then starts php-fpm
COPY --chmod=755 docker/app/entrypoint.sh /entrypoint.sh

USER serveo

EXPOSE 9000

ENTRYPOINT ["/entrypoint.sh"]
