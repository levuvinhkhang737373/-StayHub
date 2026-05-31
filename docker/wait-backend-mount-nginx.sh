#!/bin/sh
set -e

APP_DIR=/var/www/html/BE_StayHub
attempts="${PROJECT_MOUNT_ATTEMPTS:-20}"
delay="${PROJECT_MOUNT_DELAY:-3}"
count=1

while [ "$count" -le "$attempts" ]; do
    if [ -f "$APP_DIR/artisan" ] && [ -d "$APP_DIR/public" ] && [ -d "$APP_DIR/routes" ]; then
        exec nginx -g 'daemon off;'
    fi

    echo "Backend source chÆ°a mount sáºµn cho nginx (${count}/${attempts})..."
    sleep "$delay"
    count=$((count + 1))
done

echo "Backend source váº«n chÆ°a mount Ä‘Ãºng cho nginx; thoÃ¡t Ä‘á»ƒ Docker tá»± restart vÃ  mount láº¡i."
exit 1
