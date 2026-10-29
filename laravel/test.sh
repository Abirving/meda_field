#!/usr/bin/env bash
set -euo pipefail
cd "$(dirname "$0")"
docker compose run --rm -e APP_ENV=testing -e DB_DATABASE=meda_test -e CACHE_PREFIX=meda_test_cache app php vendor/bin/phpunit
