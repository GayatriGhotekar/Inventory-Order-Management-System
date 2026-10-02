#!/bin/sh
set -e

# Bind mounts use host permissions, so make Laravel's writable folders available.
chown -R www-data:www-data storage bootstrap/cache

exec "$@"
