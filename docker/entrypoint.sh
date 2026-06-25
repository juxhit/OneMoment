#!/bin/sh
set -eu

CONFIG_LOCAL="/var/www/html/config/config.local.php"
PUBLIC_PATH="${PUBLIC_PATH:-/onemoment/public}"
PUBLIC_PATH="$(echo "$PUBLIC_PATH" | sed 's#/$##')"

if [ -z "${DOMAIN:-}" ]; then
    echo "ERROR: DOMAIN is required (e.g. photos.example.com)" >&2
    exit 1
fi

if [ -z "${ADMIN_PASSWORD:-}" ]; then
    echo "ERROR: ADMIN_PASSWORD is required" >&2
    exit 1
fi

if [ -z "${EVENT_ACCESS_SECRET:-}" ]; then
    echo "ERROR: EVENT_ACCESS_SECRET is required" >&2
    exit 1
fi

ADMIN_HASH="$(php -r "echo password_hash(getenv('ADMIN_PASSWORD'), PASSWORD_DEFAULT);")"
PUBLIC_BASE="https://${DOMAIN}${PUBLIC_PATH}"

cat > "$CONFIG_LOCAL" <<EOF
<?php

declare(strict_types=1);

define('BASE_URL', '${PUBLIC_PATH}');
define('PUBLIC_BASE_URL', '${PUBLIC_BASE}');
define('ADMIN_PASSWORD_HASH', '${ADMIN_HASH}');
define('EVENT_ACCESS_SECRET', '${EVENT_ACCESS_SECRET}');
EOF

chown www-data:www-data "$CONFIG_LOCAL"
chmod 640 "$CONFIG_LOCAL"

for dir in data uploads thumbs exports; do
    mkdir -p "/var/www/html/$dir"
    chown -R www-data:www-data "/var/www/html/$dir"
done

exec "$@"