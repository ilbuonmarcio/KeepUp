#!/bin/sh

set -eu

if [ -z "${APP_KEY:-}" ]; then
    echo "APP_KEY is empty. Generate one and add it to .env.docker before starting KeepUp." >&2
    exit 1
fi

gosu www-data php artisan migrate --force
gosu www-data php artisan optimize

exec apache2-foreground
