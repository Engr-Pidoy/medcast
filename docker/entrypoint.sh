#!/usr/bin/env bash
set -euo pipefail

cd /var/www/html

mkdir -p database storage/framework/{cache,sessions,views} storage/logs storage/app/medcast bootstrap/cache
touch database/database.sqlite
chmod -R 775 storage bootstrap/cache database || true

if [ -z "${APP_KEY:-}" ]; then
  export APP_KEY="$(php -r "echo 'base64:'.base64_encode(random_bytes(32));")"
  echo "WARNING: APP_KEY was missing; generated a temporary key for this boot."
fi

export DB_CONNECTION="${DB_CONNECTION:-sqlite}"
export DB_DATABASE="${DB_DATABASE:-/var/www/html/database/database.sqlite}"

php artisan config:clear || true
php artisan migrate --force

# Seed only when empty (first boot)
USER_COUNT="$(php -r "require 'vendor/autoload.php'; \$app=require 'bootstrap/app.php'; \$app->make(Illuminate\\Contracts\\Console\\Kernel::class)->bootstrap(); echo App\\Models\\User::count();")"
if [ "${USER_COUNT}" = "0" ]; then
  echo "First boot: seeding MEDCAST demo data..."
  php artisan db:seed --force
  php artisan medcast:run-forecast --python=python3 || true
fi

PORT="${PORT:-8000}"
exec php artisan serve --host=0.0.0.0 --port="$PORT"
