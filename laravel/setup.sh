#!/usr/bin/env bash
set -euo pipefail
cd "$(dirname "$0")"
if [ ! -f .env ]; then cp .env.example .env; fi
docker compose build app
docker compose up -d db redis
docker compose run --rm app composer install --prefer-dist --no-interaction
if ! grep -Eq '^APP_KEY=.+$' .env; then
  docker compose run --rm app php artisan key:generate --force
fi
if ! grep -Eq '^MEDA_QR_KEY_V1=.+$' .env; then
  key=$(docker compose run --rm app php -r 'echo bin2hex(random_bytes(32));')
  umask 077
  awk -v qr_key="$key" '/^MEDA_QR_KEY_V1=/{print "MEDA_QR_KEY_V1=" qr_key; next} {print}' .env > .env.next
  mv .env.next .env
fi
docker compose run --rm app php artisan migrate --seed --force
docker compose run --rm app php artisan filament:assets
docker compose --profile tools run --rm assets
docker compose up -d app queue scheduler reverb
printf '\nMeda Pass: http://localhost:8000\nAdmin: http://localhost:8000/admin\nGate desk: http://localhost:8000/gate\n'
