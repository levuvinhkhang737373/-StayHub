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

    echo "Backend source chưa mount sẵn cho nginx (${count}/${attempts})..."
    sleep "$delay"
    count=$((count + 1))
done

echo "Backend source vẫn chưa mount đúng cho nginx; thoát để Docker tự restart và mount lại."
exit 1
