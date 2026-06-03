# Scormetry 2.0 — production image (PHP-FPM + Nginx + built Vite assets)
FROM php:8.3-fpm-bookworm

# --- System packages + PHP extensions ---
RUN apt-get update && apt-get install -y --no-install-recommends \
        nginx supervisor git curl unzip zip \
        libzip-dev libpng-dev libonig-dev libxml2-dev libicu-dev \
    && docker-php-ext-install pdo_mysql mbstring zip exif pcntl bcmath gd intl \
    && rm -rf /var/lib/apt/lists/*

# Composer
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

# Node 22 (for the Vite build)
RUN curl -fsSL https://deb.nodesource.com/setup_22.x | bash - \
    && apt-get install -y nodejs && rm -rf /var/lib/apt/lists/*

# PHP upload limits (match the app's 20MB document rule)
RUN { echo 'upload_max_filesize=25M'; echo 'post_max_size=30M'; echo 'memory_limit=512M'; } \
    > /usr/local/etc/php/conf.d/scormetry.ini

WORKDIR /var/www/html

# --- PHP deps (cached layer) ---
COPY composer.json composer.lock ./
RUN composer install --no-dev --no-scripts --no-autoloader --prefer-dist --no-progress

# --- Node deps (cached layer) ---
COPY package.json package-lock.json ./
RUN npm ci

# --- App source + build ---
COPY . .
RUN cp .env.example .env \
    && composer install --no-dev --optimize-autoloader --no-progress \
    && php artisan key:generate \
    && php artisan wayfinder:generate \
    && npm run build \
    && rm -f .env public/hot \
    && chown -R www-data:www-data storage bootstrap/cache

# --- Nginx + Supervisor + entrypoint ---
COPY docker/nginx.conf /etc/nginx/sites-available/default
COPY docker/supervisord.conf /etc/supervisor/conf.d/supervisord.conf
COPY docker/entrypoint.sh /usr/local/bin/entrypoint.sh
RUN chmod +x /usr/local/bin/entrypoint.sh

EXPOSE 80
ENTRYPOINT ["/usr/local/bin/entrypoint.sh"]
CMD ["/usr/bin/supervisord", "-c", "/etc/supervisor/conf.d/supervisord.conf"]
