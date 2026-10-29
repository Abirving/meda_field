# Production deployment and operations

This runbook prepares a private staging installation, then an approved pilot. It does not record a completed deployment. Docker/production networking and restore checks must run on the target host before public launch.

## Prepare

Use a dedicated host with Docker Compose, DNS pointing at that host and ports 80/443 available. PostgreSQL and Redis ports must remain private. The included stack uses Caddy TLS → PHP-FPM, separate queue/scheduler/Reverb processes, PostgreSQL 16 and authenticated persistent Redis 7. Application containers run as www-data with a read-only root and dedicated writable volumes. For managed databases use the provider's required TLS/CA configuration and networking; do not expose the included database publicly.

```sh
cd laravel
cp .env.production.example .env.production
mkdir -p secrets
chmod 700 secrets
chmod 600 .env.production
```

Set a real domain, APP_URL, independent random database/Redis/Reverb credentials, stable APP_KEY and QR key, operator/support details, SMTP, licensed Fayda configuration and approved Telebirr configuration. Generate APP_KEY as `base64:` plus 32 random bytes encoded in base64; QR key and Fayda subject pepper should each have at least 32 bytes of independent entropy. Do not reuse example/demo credentials. Mount provider private/public PEM files under `secrets/`; make required files readable by the container's www-data user without making them public. `.dockerignore` excludes the entire secrets directory.

Keep APP_KEY, QR key versions, identity pepper and provider keys in a separately backed-up secret store. Revoking APP_KEY without migration can make MFA secrets unreadable and invalidate legacy passes. QR rotation: add v2, retain v1, switch MEDA_QR_ACTIVE_KEY, deploy consistently, then retire v1 only after its passes and recovery requirements expire. Changing the identity pepper requires a controlled identity migration.

Set TRUSTED_PROXIES to the real ingress proxy address/network when forwarded headers are used; do not trust arbitrary internet clients. Configure REVERB_ALLOWED_ORIGINS to the actual site hostname. Production builds embed public Reverb host/key, so rebuild frontend/web images when these values change. The public key is not the Reverb secret.

## Validate and initialize

First run the native CI/test suite on a disposable database. For an existing application, take and verify a backup before migration. Inspect existing data for duplicate `lower(email)` values; the new unique index deliberately rejects ambiguous addresses. Do not seed or import demonstration accounts into production. The root D1 demo has no automatic migration path into real inventory/accounts.

The commands below refer to `.env.production` explicitly. Keep the public web service stopped until acceptance is complete.

```sh
docker compose --env-file .env.production -f compose.production.yaml build
docker compose --env-file .env.production -f compose.production.yaml up -d db redis
docker compose --env-file .env.production -f compose.production.yaml run --rm app php artisan migrate --force
docker compose --env-file .env.production -f compose.production.yaml run --rm app php artisan meda:bootstrap-admin admin@your-real-domain.example
docker compose --env-file .env.production -f compose.production.yaml run --rm app php artisan meda:doctor --strict
```

Replace the administrator address with a real controlled address. The bootstrap command prompts privately for an initial password; it runs only while no active super administrator exists. Complete MFA at first sign-in. Create real locations/venues and invite other staff through scoped administration. Store MFA recovery codes in approved private custody.

`meda:doctor --strict` checks production settings plus database/Redis connectivity and exits nonzero on missing prerequisites. Fix its findings; do not disable the guard to open an unfinished system. The guard checks declared configuration, not actual merchant certification or successful email delivery.

After all configuration and external acceptance gates pass:

```sh
docker compose --env-file .env.production -f compose.production.yaml run --rm app php artisan config:cache
docker compose --env-file .env.production -f compose.production.yaml run --rm app php artisan route:cache
docker compose --env-file .env.production -f compose.production.yaml run --rm app php artisan view:cache
docker compose --env-file .env.production -f compose.production.yaml up -d app queue scheduler reverb web
```

Do not run source upgrades concurrently with active migrations. Rebuild and recreate services after source/dependency changes. Refresh configuration caches and restart workers after secret/configuration changes. Bootstrap/cache is shared between these single-host services; build caches while application services are stopped or during a controlled maintenance window. Rehearse updates on staging first.

## Daily operations

```sh
docker compose --env-file .env.production -f compose.production.yaml exec -T app php artisan meda:operations-check
docker compose --env-file .env.production -f compose.production.yaml exec -T app php artisan queue:failed
docker compose --env-file .env.production -f compose.production.yaml logs --tail=100 queue scheduler reverb
```

The scheduler expires holds every minute, reconciles uncertain checkout attempts every minute (at most 100 per batch, at least two minutes between checks), sends reminders every five minutes, and marks finished paid/checked-in bookings as no-show/completed. Heartbeats expire after ten minutes. Configure external alerts for missing heartbeats, overdue holds, uncertain payments older than ten minutes, credit mismatches, open payment cases and failed jobs. `meda:operations-check` returns nonzero for missing heartbeats, overdue/uncertain payments or ledger mismatch; open payment cases are reported for your separate support alert/SLA. This command is not an external monitoring service.

Reconcile bank/provider settlement reports daily against payment attempts, receipts, events, reviews and credits. A query failure leaves money uncertain. From Checkout attempts, check provider status again using the same merchant order; do not create a replacement order or edit database status to manufacture a pass. If the provider confirms money but the signed API cannot deliver a definitive result, escalate via the merchant support process, document the case and verify any external refund. Do not grant store credit based solely on an uncertain request.

Payment cases require verified evidence and notes. STORE_CREDIT records the customer-agreed remedy once. EXTERNAL_REFUND records a completed external cash refund and restores any eligible reserved credit; the application does not send the refund. NO_CHARGE requires provider confirmation that money was not taken. Never close a case merely to clear an alert.

Monitor SMTP delivery, retries and worker restarts. Notifications are not the payment source of truth; customers can inspect the booking record when mail is delayed. No separate transactional notification outbox is supplied. Audit logs contain operational IDs/changes; restrict access, retention and exports. Avoid logging raw identity responses, payment payloads, private keys, QR content or signed URLs. Caddy access logging is intentionally not enabled by default.

## Backup and restoration

Schedule `deploy/backup.sh` on the host with BACKUP_DIRECTORY pointing to an encrypted, off-host mounted destination. It writes a custom-format PostgreSQL dump atomically and a SHA-256 checksum with restrictive permissions. Choose retention/frequency and recovery objectives based on the operator's approved policy; the script does not schedule itself or provide encryption/off-host replication. Back up secrets and deployment configuration separately.

```sh
BACKUP_DIRECTORY=/absolute/encrypted-offhost/meda bash deploy/backup.sh
# On a separate isolated host/database, after verifying the checksum:
export RESTORE_TEST_DATABASE_URL='postgresql://restore-user@isolated-db/meda_restore_test'
bash deploy/restore-check.sh /path/to/meda-backup.dump
```

Use a secure PostgreSQL password file rather than embedding a password in the URL/command line. The restoration target must be an empty dedicated `meda_restore_test` database; the script does not delete or overwrite the live database. Restore rehearsal must include row/ledger totals, application boot with the correct secrets, sample authorized pass access and provider-state reconciliation. Redis persistence is useful for sessions/queues/locks but does not replace PostgreSQL backups. After restoring older data, reconcile all provider money movement since the backup before accepting new bookings.

## Incident and rollback

Stop new traffic/checkout during a financial or integrity incident, preserve records and provider evidence, and keep staff communications on approved channels. Reconcile affected attempts before reopening. A bad provider configuration must not be “fixed” by accepting unsigned callbacks.

Keep the previous application image and a verified database backup. Prefer a forward fix. Schema rollback drops payment/identity records and is not a safe routine financial rollback. Restore only through an explicit, tested recovery procedure; do not silently roll a live database back behind already received payments. Re-enable services only after key compatibility, queues, holds, balances and provider reconciliation are verified.

## Final staging acceptance

Run native PostgreSQL parallel tests; load-test actual PHP requests and locks; terminate/restart workers and simulate provider outages; confirm signed callback reachability and exact acknowledgement; test all staff scopes; test SMTP/invitation/recovery/MFA; check mobile camera and accessibility; test Reverb reconnect and polling fallback; approve policy/venue data; verify alarms and restore a backup. Capture evidence before enabling public real-money bookings.
