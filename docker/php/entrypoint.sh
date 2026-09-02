#!/bin/sh
set -e

# vendor/ is never committed (it's dependency-managed) — a fresh clone (dev
# or prod) has none until this runs once. Skipped on later starts once it
# exists, so this doesn't slow down normal restarts.
if [ ! -f /var/www/html/vendor/autoload.php ]; then
    composer install --optimize-autoloader --no-interaction
fi

chown -R www-data:www-data /var/www/html/storage /var/www/html/bootstrap/cache
chmod -R 775 /var/www/html/storage /var/www/html/bootstrap/cache

exec "$@"
