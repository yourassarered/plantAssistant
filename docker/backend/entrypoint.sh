#!/usr/bin/env sh
set -eu

cd /var/www/html

mkdir -p \
    storage/app/public \
    storage/app/roadrunner \
    storage/framework/cache \
    storage/framework/sessions \
    storage/framework/views \
    storage/logs \
    bootstrap/cache

if [ ! -e public/storage ]; then
    php artisan storage:link >/dev/null 2>&1 || true
fi

run_migrations() {
    attempts=0

    until php artisan migrate --force; do
        attempts=$((attempts + 1))

        if [ "$attempts" -ge "${DB_MIGRATION_ATTEMPTS:-30}" ]; then
            echo "Database migrations failed after ${attempts} attempts." >&2
            return 1
        fi

        echo "Database is not ready yet, retrying migrations in 2 seconds..." >&2
        sleep 2
    done
}

if [ "${1:-}" = "octane" ]; then
    if [ "${RUN_MIGRATIONS:-true}" = "true" ]; then
        run_migrations
    fi

    if [ "${RUN_SEEDERS:-false}" = "true" ]; then
        php artisan db:seed --force
    fi

    RR_CONFIG_PATH="${OCTANE_RR_CONFIG:-storage/app/roadrunner/.rr.yaml}"
    mkdir -p "$(dirname "$RR_CONFIG_PATH")"
    touch "$RR_CONFIG_PATH"

    set -- php artisan octane:start \
        --server=roadrunner \
        --host=0.0.0.0 \
        --port="${PORT:-8000}" \
        --rpc-port="${OCTANE_RPC_PORT:-6001}" \
        --rr-config="$RR_CONFIG_PATH" \
        --max-requests="${OCTANE_MAX_REQUESTS:-500}"

    if [ -n "${OCTANE_WORKERS:-}" ]; then
        set -- "$@" --workers="${OCTANE_WORKERS}"
    fi
fi

exec "$@"
