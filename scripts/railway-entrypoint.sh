#!/bin/sh
set -eu

: "${PORT:=8080}"
case "$PORT" in
    *[!0-9]*|'')
        echo "PORT must be a numeric TCP port." >&2
        exit 1
        ;;
esac

sed -i "s/^Listen 80$/Listen ${PORT}/" /etc/apache2/ports.conf
sed -i "s/__PORT__/${PORT}/g" /etc/apache2/sites-available/000-default.conf

mkdir -p \
    /var/www/html/uploads/incidents \
    /var/www/html/uploads/printing \
    /var/www/html/uploads/health \
    /var/www/html/uploads/disclosures \
    /var/www/html/assets/images/events

if [ -d /opt/skalinga-event-seeds ]; then
    cp -an /opt/skalinga-event-seeds/. /var/www/html/assets/images/events/
fi

chown -R www-data:www-data \
    /var/www/html/uploads \
    /var/www/html/assets/images/events

exec docker-php-entrypoint "$@"
