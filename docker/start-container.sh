#!/bin/sh
set -e

APP_DIR=/var/www/html/BE_StayHub
APP_USER="${APP_USER:-${DOCKER_USER:-khang}}"
COMPOSER_LOCK_HASH_FILE=vendor/.composer-lock.sha256
COMPOSER_INSTALL_LOCK_DIR=storage/framework/composer-install.lock

export APP_USER
cd "$APP_DIR"

wait_for_project_mount() {
    attempts="${PROJECT_MOUNT_ATTEMPTS:-60}"
    delay="${PROJECT_MOUNT_DELAY:-2}"
    count=1

    while [ "$count" -le "$attempts" ]; do
        if [ -f artisan ] && [ -f composer.json ] && [ -f composer.lock ] && [ -d app ] && [ -d routes ]; then
            return 0
        fi

        echo "Backend source chưa sẵn sàng, đợi WSL/Docker ổn định (${count}/${attempts})..."
        sleep "$delay"
        count=$((count + 1))
    done

    echo "Backend source vẫn chưa đầy đủ file Laravel cần thiết; thoát để Docker tự restart."
    exit 1
}

ensure_runtime_user() {
    if id "$APP_USER" >/dev/null 2>&1; then
        return 0
    fi

    if [ "$(id -u)" = "0" ]; then
        useradd -G www-data,root -u "${APP_UID:-1000}" -d "/home/$APP_USER" "$APP_USER" 2>/dev/null || true
        mkdir -p "/home/$APP_USER/.composer"
        chown -R "$APP_USER:$APP_USER" "/home/$APP_USER" 2>/dev/null || true
    fi
}

prepare_writable_directories() {
    mkdir -p \
        storage/app/public \
        storage/framework/cache \
        storage/framework/sessions \
        storage/framework/views \
        storage/logs \
        bootstrap/cache \
        public/upload \
        vendor

    if [ "$(id -u)" = "0" ] && id "$APP_USER" >/dev/null 2>&1; then
        chown -R "$APP_USER:$APP_USER" storage bootstrap/cache public/upload vendor 2>/dev/null || true
    fi

    chmod -R u+rwX,g+rwX storage bootstrap/cache public/upload vendor 2>/dev/null || true
}

composer_lock_checksum() {
    sha256sum composer.lock | cut -d ' ' -f 1
}

needs_composer_install() {
    if [ ! -f vendor/autoload.php ] || [ ! -d vendor/composer ] || [ ! -d vendor/laravel/framework ] || [ ! -d vendor/laravel/horizon ]; then
        return 0
    fi

    current_checksum="$(composer_lock_checksum)"
    installed_checksum="$(cat "$COMPOSER_LOCK_HASH_FILE" 2>/dev/null || true)"

    [ -n "$current_checksum" ] && [ "$current_checksum" != "$installed_checksum" ]
}

release_composer_install_lock() {
    rm -f "$COMPOSER_INSTALL_LOCK_DIR/created_at" 2>/dev/null || true
    rmdir "$COMPOSER_INSTALL_LOCK_DIR" 2>/dev/null || true
}

acquire_composer_install_lock() {
    ttl="${COMPOSER_INSTALL_LOCK_TTL:-900}"

    while ! mkdir "$COMPOSER_INSTALL_LOCK_DIR" 2>/dev/null; do
        created_at="$(cat "$COMPOSER_INSTALL_LOCK_DIR/created_at" 2>/dev/null || echo 0)"
        now="$(date +%s 2>/dev/null || echo 0)"

        case "$created_at" in
            ''|*[!0-9]*) created_at=0 ;;
        esac

        if [ "$created_at" -gt 0 ] && [ "$now" -gt 0 ] && [ $((now - created_at)) -gt "$ttl" ]; then
            echo "Lock Composer quá hạn, mở khóa để cài lại dependencies..."
            release_composer_install_lock
            continue
        fi

        echo "Composer đang được container khác xử lý, đợi hoàn tất..."
        sleep 2
    done

    date +%s > "$COMPOSER_INSTALL_LOCK_DIR/created_at" 2>/dev/null || true
}

clear_vendor_contents() {
    if [ -d vendor ]; then
        find vendor -mindepth 1 -maxdepth 1 -exec rm -rf {} +
    fi
}

composer_install() {
    composer install --no-interaction --prefer-dist --optimize-autoloader
}

ensure_composer_dependencies() {
    if ! needs_composer_install; then
        return 0
    fi

    acquire_composer_install_lock
    trap 'release_composer_install_lock' EXIT INT TERM

    if needs_composer_install; then
        echo "Composer vendor đang thiếu hoặc lệch composer.lock, cài lại dependencies..."
        if ! composer_install; then
            echo "Composer install lỗi do vendor/cache cũ, làm sạch vendor rồi cài lại..."
            clear_vendor_contents
            composer clear-cache || true
            composer_install
        fi

        composer_lock_checksum > "$COMPOSER_LOCK_HASH_FILE"
    fi

    prepare_writable_directories
    release_composer_install_lock
    trap - EXIT INT TERM
}

run_artisan() {
    command="php artisan $*"

    if [ "$(id -u)" = "0" ] && id "$APP_USER" >/dev/null 2>&1 && command -v su >/dev/null 2>&1; then
        su -s /bin/sh -c "cd '$APP_DIR' && $command" "$APP_USER"
        return $?
    fi

    sh -lc "cd '$APP_DIR' && $command"
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
        run_artisan key:generate --force || true
    fi
}

ensure_storage_link() {
    if [ ! -e public/storage ]; then
        run_artisan storage:link || true
    fi
}

wait_for_tcp() {
    label="$1"
    host="$2"
    port="$3"

    until nc -z "$host" "$port"; do
        echo "waiting for $label"
        sleep 2
    done
}

wait_for_backend_services() {
    wait_for_tcp mysql "${DB_HOST:-db}" "${DB_PORT:-3306}"
    wait_for_tcp redis "${REDIS_HOST:-redis}" "${REDIS_PORT:-6379}"
}

exec_as_app_user() {
    command="$1"

    if [ "$(id -u)" = "0" ] && id "$APP_USER" >/dev/null 2>&1 && command -v su >/dev/null 2>&1; then
        exec su -s /bin/sh -c "cd '$APP_DIR' && exec $command" "$APP_USER"
    fi

    exec sh -lc "cd '$APP_DIR' && exec $command"
}

start_horizon() {
    wait_for_backend_services
    run_artisan config:clear || true
    run_artisan cache:clear || true
    run_artisan horizon:purge || true
    exec_as_app_user "php artisan horizon"
}

start_octane_supervisor() {
    exec /usr/bin/supervisord -n -c /etc/supervisor/conf.d/supervisord.conf
}

wait_for_project_mount
ensure_runtime_user
prepare_writable_directories
ensure_composer_dependencies
ensure_app_key
ensure_storage_link

case "${1:-app}" in
    horizon)
        start_horizon
        ;;
    app|octane)
        start_octane_supervisor
        ;;
    *)
        exec "$@"
        ;;
esac
