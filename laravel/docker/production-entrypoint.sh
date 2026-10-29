#!/bin/sh
set -eu
# Explicit management commands run without starting a public service.
case "${1:-} ${2:-}" in
 "php artisan")
  case "${3:-}" in
   migrate|meda:doctor|meda:bootstrap-admin|config:cache|route:cache|view:cache|optimize:clear) exec "$@" ;;
  esac ;;
esac
php artisan meda:doctor --strict
exec "$@"
