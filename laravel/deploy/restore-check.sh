#!/usr/bin/env bash
set -euo pipefail
# Always restore into a separate disposable database; never the live database.
: "${RESTORE_TEST_DATABASE_URL:?Set the dedicated restore-test PostgreSQL URL}"
case "$RESTORE_TEST_DATABASE_URL" in */meda_restore_test) ;; *) echo 'Use a database named meda_restore_test' >&2; exit 1;; esac
: "${1:?Pass the backup dump path}"
pg_restore --exit-on-error --no-owner --no-privileges --dbname="$RESTORE_TEST_DATABASE_URL" "$1"
psql "$RESTORE_TEST_DATABASE_URL" -v ON_ERROR_STOP=1 -c 'SELECT COUNT(*) FROM bookings' -c 'SELECT COUNT(*) FROM payment_attempts' -c 'SELECT COUNT(*) FROM credits'
echo 'Restore completed. Run the application smoke checks against this isolated database.'
