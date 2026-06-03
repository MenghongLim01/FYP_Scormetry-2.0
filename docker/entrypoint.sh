#!/bin/sh
set -e
cd /var/www/html

# Wait for the database to accept connections.
echo "Waiting for database at ${DB_HOST:-db}:${DB_PORT:-3306} ..."
until php -r "new PDO('mysql:host='.getenv('DB_HOST').';port='.(getenv('DB_PORT')?:'3306'), getenv('DB_USERNAME'), getenv('DB_PASSWORD'));" >/dev/null 2>&1; do
    sleep 3
done
echo "Database is up."

# Only the main app container runs migrations (workers/scheduler skip this).
if [ "${RUN_MIGRATIONS}" = "true" ]; then
    php artisan migrate --force
    php artisan storage:link || true
fi

# Cache config/routes/views with the real runtime environment.
php artisan config:cache
php artisan route:cache
php artisan view:cache

chown -R www-data:www-data storage bootstrap/cache || true

exec "$@"
