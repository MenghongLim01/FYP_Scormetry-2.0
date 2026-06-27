#!/usr/bin/env bash
#
# Scormetry 2.0 — production deploy.
# Run as root on the droplet:   sudo bash deploy.sh
#
# Safe to run as root: it pulls + builds, then ALWAYS resets ownership
# (repo -> scoreadmin, storage/cache -> www-data) and runs artisan as
# www-data, so the "ran as root and broke permissions" trap can't happen.

set -euo pipefail

APP_DIR="/home/scoreadmin/FYP_Scormetry-2.0"
OWNER="scoreadmin"
WEB="www-data"

if [ "$(id -u)" -ne 0 ]; then
  echo "Please run as root:  sudo bash deploy.sh" >&2
  exit 1
fi

cd "$APP_DIR"
git config --global --add safe.directory "$APP_DIR" >/dev/null 2>&1 || true

echo "==> [1/6] Pull latest main"
git pull origin main

echo "==> [2/6] PHP dependencies"
COMPOSER_ALLOW_SUPERUSER=1 composer install --no-dev --optimize-autoloader --no-interaction

echo "==> [3/6] Build frontend"
npm ci
npm run build

echo "==> [4/6] Fix ownership & permissions"
chown -R "$OWNER":"$OWNER" "$APP_DIR"
chown -R "$WEB":"$WEB" storage bootstrap/cache
chmod -R 775 storage bootstrap/cache
find storage bootstrap/cache -type d -exec chmod g+s {} \;

echo "==> [5/6] Migrate + cache (as $WEB)"
sudo -u "$WEB" HOME=/tmp php artisan migrate --force
sudo -u "$WEB" HOME=/tmp php artisan config:cache
sudo -u "$WEB" HOME=/tmp php artisan route:cache
sudo -u "$WEB" HOME=/tmp php artisan view:cache

echo "==> [6/6] Restart services"
systemctl restart php8.3-fpm scormetry-queue

echo
echo "✅ Deploy complete. Now hard-refresh the browser (Cmd+Shift+R)."
