#!/bin/sh
set -e

APP_DIR=/var/www/html/BE_StayHub
cd "$APP_DIR"

wait_for_project_mount() {
    attempts="${PROJECT_MOUNT_ATTEMPTS:-20}"
    delay="${PROJECT_MOUNT_DELAY:-3}"
    count=1

    while [ "$count" -le "$attempts" ]; do
        if [ -f artisan ] && [ -f composer.json ] && [ -d app ] && [ -d routes ]; then
            return 0
        fi

        echo "Backend source chưa mount sẵn, đợi WSL/Docker ổn định (${count}/${attempts})..."
        sleep "$delay"
        count=$((count + 1))
    done

    echo "Backend source vẫn chưa mount đúng; thoát để Docker tự restart và mount lại."
    exit 1
}

ensure_app_key() {
    if [ ! -f .env ] && [ -f .env.example ]; then
        cp .env.example .env
    fi

    app_key=""
    if [ -f .env ]; then
        app_key="$(grep -E '^APP_KEY=' .env 2>/dev/null | tail -n 1 | cut -d= -f2- | tr -d "'\"" || true)"
    fi

    if [ -z "$app_key" ]; then
        php artisan key:generate --force || true
    fi
}

wait_for_project_mount

mkdir -p storage/framework/cache storage/framework/sessions storage/framework/views storage/logs bootstrap/cache
chmod -R u+rwX,g+rwX storage bootstrap/cache || true

if [ ! -f vendor/autoload.php ]; then
    composer install --no-interaction --prefer-dist --optimize-autoloader
fi

ensure_app_key

exec /usr/bin/supervisord -n -c /etc/supervisor/conf.d/supervisord.conf
