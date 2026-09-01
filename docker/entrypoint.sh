#!/usr/bin/env bash
set -euo pipefail

cd /var/www/html

mkdir -p database storage/framework/{cache,sessions,views} storage/logs storage/app/medcast bootstrap/cache
chmod -R 775 storage bootstrap/cache database || true

if [ -z "${APP_KEY:-}" ]; then
  export APP_KEY="$(php -r "echo 'base64:'.base64_encode(random_bytes(32));")"
  echo "WARNING: APP_KEY was missing; generated a temporary key for this boot."
fi

DATABASE_CONNECTION_URL="${DB_URL:-${DATABASE_URL:-}}"
if [ -z "${DB_CONNECTION:-}" ] && [[ "${DATABASE_CONNECTION_URL}" == postgres://* || "${DATABASE_CONNECTION_URL}" == postgresql://* ]]; then
  export DB_CONNECTION="pgsql"
fi

export DB_CONNECTION="${DB_CONNECTION:-sqlite}"

if [ "${APP_ENV:-production}" = "production" ] && [ "${DB_CONNECTION}" = "sqlite" ]; then
  echo "ERROR: Production cannot use the container-local SQLite database because it is erased on restart. Configure DB_CONNECTION=pgsql and DB_URL/DATABASE_URL to a persistent PostgreSQL database."
  exit 1
fi

if [ "${DB_CONNECTION}" = "sqlite" ]; then
  export DB_DATABASE="${DB_DATABASE:-/var/www/html/database/database.sqlite}"
  touch "${DB_DATABASE}"
fi

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
