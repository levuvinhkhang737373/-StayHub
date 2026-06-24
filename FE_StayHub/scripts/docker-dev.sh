#!/bin/sh
set -eu

cd /app

echo "Waiting for frontend source files..."
attempt=1
while [ ! -f package.json ]; do
  if [ "$attempt" -ge "60" ]; then
    echo "package.json was not mounted into /app after ${attempt} seconds" >&2
    exit 1
  fi

  attempt=$((attempt + 1))
  sleep 1
done

install_dependencies=false
if [ ! -d node_modules/.bin ]; then
  install_dependencies=true
elif [ -f package-lock.json ]; then
  lock_hash="$(sha256sum package-lock.json | cut -d ' ' -f 1)"
  installed_hash="$(cat node_modules/.package-lock.sha256 2>/dev/null || true)"

  if [ "$lock_hash" != "$installed_hash" ]; then
    install_dependencies=true
  fi
else
  install_dependencies=true
fi

if [ "$install_dependencies" = true ]; then
  if [ -f package-lock.json ]; then
    echo "Installing frontend dependencies with npm ci..."
    npm ci --prefer-offline
    sha256sum package-lock.json | cut -d ' ' -f 1 > node_modules/.package-lock.sha256
  else
    echo "package-lock.json is missing; using npm install instead of npm ci..."
    npm install --prefer-offline
    if [ -f package-lock.json ]; then
      sha256sum package-lock.json | cut -d ' ' -f 1 > node_modules/.package-lock.sha256
    fi
  fi
fi

exec npm run dev -- --host 0.0.0.0 --port 80
