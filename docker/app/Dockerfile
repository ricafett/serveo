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
        zip iputils-ping procps \
        postgresql-client \
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

# Install dependencies. Use .env.ci to provide a safe build-time
# environment so all Composer post-autoload hooks (package:discover,
# filament:upgrade, and any future hooks) run correctly without a
# real database. .env.ci is deleted after install — it never reaches
# the production image.
COPY .env.ci .env
RUN composer install --no-dev --no-interaction --no-progress --optimize-autoloader
RUN rm .env

# Set final permissions
RUN chown -R serveo:serveo /var/www/html

# Backup the entire public/ directory so the entrypoint can sync it
# into a volume-mounted public/ at runtime. Named volumes survive
# image updates and hide the image's files — this backup lives
# outside the mount and survives. Using a full-directory backup
# means any file added to public/ (sw.js, manifest.webmanifest,
# icons, build assets, Filament assets) is automatically deployed.
RUN echo "=== Checking public/ assets before backup ===" \
    && ls -la /var/www/html/public/build/ \
    && echo "=== Backing up public/ ===" \
    && cp -a /var/www/html/public /var/www/public-backup \
    && echo "=== Backup complete ===" \
    && ls /var/www/public-backup/build/manifest.json

# Entrypoint: runs migrate + optimize at runtime with real env vars, then starts php-fpm
COPY --chmod=755 docker/app/entrypoint.sh /entrypoint.sh

USER serveo

EXPOSE 9000

ENTRYPOINT ["/entrypoint.sh"]
