#!/bin/sh
set -eu

wait_for_http() {
    name="$1"
    url="$2"
    attempts="${3:-120}"
    delay="${4:-2}"
    count=1

    until curl -fsS "$url" >/dev/null; do
        if [ "$count" -ge "$attempts" ]; then
            echo "$name chÆ°a sáºµn sÃ ng sau $attempts láº§n kiá»ƒm tra; thoÃ¡t Ä‘á»ƒ Docker tá»± restart."
            exit 1
        fi

        echo "Äá»£i $name sáºµn sÃ ng ($count/$attempts)..."
        count=$((count + 1))
        sleep "$delay"
    done
}

if [ -n "${CLOUDFLARED_ENV_FILE:-}" ] && [ -f "$CLOUDFLARED_ENV_FILE" ]; then
    TUNNEL_TOKEN="$(grep -E '^TUNNEL_TOKEN=' "$CLOUDFLARED_ENV_FILE" | tail -n 1 | cut -d= -f2- | tr -d '\r' || true)"
    export TUNNEL_TOKEN
fi

if [ -z "${TUNNEL_TOKEN:-}" ]; then
    echo "TUNNEL_TOKEN chÆ°a Ä‘Æ°á»£c cáº¥u hÃ¬nh trong BE_StayHub/.env."
    exit 1
fi

wait_for_http "Nginx/Frontend" "${CLOUDFLARED_FRONTEND_URL:-http://webserver/}" "${CLOUDFLARED_WAIT_ATTEMPTS:-120}" "${CLOUDFLARED_WAIT_DELAY:-2}"
wait_for_http "Nginx/Laravel" "${CLOUDFLARED_WEB_URL:-http://webserver/sanctum/csrf-cookie}" "${CLOUDFLARED_WAIT_ATTEMPTS:-120}" "${CLOUDFLARED_WAIT_DELAY:-2}"
wait_for_http "phpMyAdmin" "${CLOUDFLARED_DB_URL:-http://phpmyadmin/}" "${CLOUDFLARED_WAIT_ATTEMPTS:-120}" "${CLOUDFLARED_WAIT_DELAY:-2}"

exec /usr/local/bin/cloudflared "$@"
