#!/bin/sh
set -e

# Ensure .env exists if not provided by host
if [ ! -f /var/www/html/.env ]; then
    if [ -f /var/www/html/.env.example ]; then
        cp /var/www/html/.env.example /var/www/html/.env
    else
        touch /var/www/html/.env
    fi
fi

# Ensure SQLite database exists
if [ ! -f /var/www/html/database/database.sqlite ]; then
    touch /var/www/html/database/database.sqlite
fi
chmod 777 /var/www/html/database/database.sqlite 2>/dev/null || true

# Ensure storage and cache directories have write permissions
chmod -R 777 /var/www/html/storage /var/www/html/bootstrap/cache 2>/dev/null || true

# If custom command was passed (e.g. from local docker-compose), execute it directly
if [ "$#" -gt 0 ]; then
    exec "$@"
fi

# Default standalone mode (Render / Cloud deployment)
echo "Bootstrapping application..."

# Generate app key if missing
if [ -z "$APP_KEY" ] && ! grep -q "^APP_KEY=base64:" /var/www/html/.env 2>/dev/null; then
    echo "Generating application encryption key..."
    php artisan key:generate --force
fi

echo "Running database migrations..."
php artisan migrate --force

echo "Seeding initial report if empty..."
php artisan db:seed --force

# Start background queue worker
echo "Starting background queue worker..."
php artisan queue:work --tries=1 --timeout=120 &

# Start web server on dynamic PORT provided by host (or default to 8000)
echo "Starting web server on port ${PORT:-8000}..."
exec php artisan serve --host=0.0.0.0 --port="${PORT:-8000}"
