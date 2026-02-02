#!/bin/bash
set -e

echo "=== CueSports Production Startup ==="

# Wait for database to be ready
echo "Waiting for database..."
until php artisan db:monitor --databases=pgsql 2>/dev/null; do
    echo "Database not ready, waiting..."
    sleep 2
done
echo "Database is ready!"

# Run migrations
echo "Running migrations..."
php artisan migrate --force

# Cache configuration for production
echo "Caching configuration..."
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan event:cache

# Create storage link if not exists
echo "Creating storage link..."
php artisan storage:link 2>/dev/null || true

# Start the scheduler in background
echo "Starting scheduler..."
php artisan schedule:work >> /var/log/scheduler.log 2>&1 &

# Start queue worker in background
echo "Starting queue worker..."
php artisan queue:work redis --sleep=3 --tries=3 --max-time=3600 >> /var/log/queue.log 2>&1 &

# Start Octane (main process - foreground)
echo "Starting Octane server..."
exec php artisan octane:start --server=frankenphp --host=0.0.0.0 --port=${PORT:-8000}
