#!/bin/sh

set -eu

mkdir -p \
    storage/app/private/ssh_private_keys \
    storage/framework/cache/data \
    storage/framework/sessions \
    storage/framework/views \
    storage/logs \
    bootstrap/cache

chown -R www-data:www-data storage bootstrap/cache
chmod -R u+rwX,g+rwX,o-rwx storage bootstrap/cache

if [ "${1:-}" = "php" ]; then
    exec gosu www-data "$@"
fi

exec "$@"
