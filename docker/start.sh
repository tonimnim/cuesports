#!/bin/bash
set -e

# =============================================================================
# CueSports Production Entrypoint
# =============================================================================
# Supports CONTAINER_ROLE environment variable:
#   - "web"       : Runs Octane + Queue + Scheduler (all-in-one, default)
#   - "octane"    : Runs only Octane web server
#   - "worker"    : Runs only queue workers
#   - "scheduler" : Runs only the scheduler
# =============================================================================

ROLE=${CONTAINER_ROLE:-web}
PORT=${PORT:-8000}

echo "=============================================="
echo "CueSports Production Container"
echo "Role: ${ROLE}"
echo "Port: ${PORT}"
echo "=============================================="

# -----------------------------------------------------------------------------
# Wait for required services
# -----------------------------------------------------------------------------
wait_for_service() {
    local service=$1
    local max_attempts=${2:-30}
    local attempt=1

    echo "Waiting for ${service}..."

    while [ $attempt -le $max_attempts ]; do
        if php artisan tinker --execute="try { ${service}; echo 'OK'; } catch(Exception \$e) { exit(1); }" 2>/dev/null | grep -q "OK"; then
            echo "${service} is ready!"
            return 0
        fi
        echo "Attempt ${attempt}/${max_attempts}: ${service} not ready, waiting..."
        sleep 2
        attempt=$((attempt + 1))
    done

    echo "ERROR: ${service} failed to become ready"
    return 1
}

# Wait for database
wait_for_service "DB::connection()->getPdo()" 30

# Wait for Redis (non-blocking, just warn if not available)
if ! wait_for_service "Illuminate\Support\Facades\Redis::ping()" 10; then
    echo "WARNING: Redis not available, some features may be degraded"
fi

# -----------------------------------------------------------------------------
# One-time initialization (only for web/octane role or first container)
# -----------------------------------------------------------------------------
if [ "$ROLE" = "web" ] || [ "$ROLE" = "octane" ]; then
    echo "Running database migrations..."
    php artisan migrate --force --no-interaction

    echo "Caching configuration..."
    php artisan config:cache
    php artisan route:cache
    php artisan view:cache
    php artisan event:cache

    echo "Creating storage link..."
    php artisan storage:link 2>/dev/null || true
fi

# -----------------------------------------------------------------------------
# Configure supervisor based on role
# -----------------------------------------------------------------------------
configure_supervisor() {
    # Clear any existing configs
    rm -f /etc/supervisor/conf.d/*.conf

    case "$ROLE" in
        web)
            # All-in-one: Octane + Queue + Scheduler
            cp /app/docker/supervisor/octane.conf /etc/supervisor/conf.d/
            cp /app/docker/supervisor/queue.conf /etc/supervisor/conf.d/
            cp /app/docker/supervisor/scheduler.conf /etc/supervisor/conf.d/
            echo "Configured: Octane + Queue Workers + Scheduler"
            ;;
        octane)
            # Only web server
            cp /app/docker/supervisor/octane.conf /etc/supervisor/conf.d/
            echo "Configured: Octane only"
            ;;
        worker)
            # Only queue workers
            cp /app/docker/supervisor/queue.conf /etc/supervisor/conf.d/
            echo "Configured: Queue Workers only"
            ;;
        scheduler)
            # Only scheduler
            cp /app/docker/supervisor/scheduler.conf /etc/supervisor/conf.d/
            echo "Configured: Scheduler only"
            ;;
        *)
            echo "ERROR: Unknown CONTAINER_ROLE: ${ROLE}"
            echo "Valid roles: web, octane, worker, scheduler"
            exit 1
            ;;
    esac
}

configure_supervisor

# -----------------------------------------------------------------------------
# Start supervisor (manages all configured processes)
# -----------------------------------------------------------------------------
echo "Starting supervisor..."
exec /usr/bin/supervisord -c /etc/supervisor/supervisord.conf
