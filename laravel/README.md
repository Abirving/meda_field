# Meda Pass Laravel application

The canonical municipal booking application uses Laravel 12, PHP 8.3+, PostgreSQL 16, Redis 7, Filament 3 and Blade/HTMX/Alpine. The React/D1 application one directory above is a separate reference demo.

## Local setup

Requirements: Docker Engine/Desktop with Compose, access to official package/container registries and free ports 8000/8080. Alternatively install PHP 8.3 with the extensions in `Dockerfile`, Composer 2, PostgreSQL 16 with `btree_gist`, Redis 7 and Node 22.

```sh
cp .env.example .env
# Edit MEDA_SEED_PASSWORD to a unique value of at least 12 characters.
bash setup.sh
```

Setup installs locked dependencies, creates application/QR keys if absent, applies migrations, seeds local fixtures, builds assets and starts the application, worker, scheduler and Reverb. Never regenerate an existing application's APP_KEY, QR keys or Fayda subject pepper without a planned migration. APP_KEY encrypts MFA/session data; old QR keys must remain available while their passes remain valid.

| Account | Local login | Password |
| --- | --- | --- |
| Customer | Synthetic FAN `1000000001` or another synthetic ten-digit value | None in local mock mode |
| Super administrator | `admin@meda.test` / `admin` | Your MEDA_SEED_PASSWORD |
| Subcity supervisor | `subcity@meda.test` / `subcity` | Same local seed password |
| Woreda supervisor | `woreda@meda.test` / `woreda` | Same local seed password |
| Gatekeeper | `operator@meda.test` / `operator` | Same local seed password |

Local seeding is prohibited in production. It creates two sample fields in Addis Ababa → Bole → Woreda 03. Re-running it does not replace existing passwords.

## Exercise the application

1. Sign in with a synthetic FAN. Complete the account profile, accept the draft local terms and verify the contact email. With `MAIL_MAILER=log`, the verification link is in `storage/logs/laravel.log` inside the app container. Open it in the same signed-in browser. Local logs may contain one-use links; do not publish them.
2. Choose a field and date, enter a team name and select an available hour. Complete the clearly marked simulated payment while the hold is valid. A payment failure, release or expiry returns reserved credit.
3. Inspect the pass/receipt, cancel an eligible reservation, and confirm that credit is automatically reserved on the next booking.
4. Staff sign in with the chosen seed password and enroll an authenticator using the displayed secret. Save the ten one-use recovery codes privately. The same TOTP code cannot be replayed. To simplify a disposable local demo only, `MEDA_STAFF_MFA=false` is allowed locally; production requires MFA.
5. Gatekeepers use `/gate` and their assigned field. A QR token is valid only during the booked play hour. Camera scanning needs HTTPS or localhost and explicit browser camera permission; pasted pass content is also supported.
6. Supervisors manage jurisdiction-scoped fields and staff. Weekly hours override daily hours; omitted weekdays are closed. Future reservations prevent calendar/location changes. Scheduled closures or maintenance release pending holds and route affected paid reservations to payment cases.
7. Super administrators use **Checkout attempts** for uncertain provider requests and **Payment cases** for late charges, contradictory results and closures. Case resolution records evidence and customer remedy; it does not call a cash-refund API.

Customer recovery is a fresh Fayda sign-in to the same verified subject. Staff invitation/recovery uses one-use email links to set a password; password reset also revokes old sessions. An invitation requires working SMTP in real use. There is no default production administrator: bootstrap one explicitly as described in the runbook.

## Tests

```sh
bash test.sh
docker compose run --rm -e APP_ENV=testing -e DB_DATABASE=meda_test -e BROADCAST_CONNECTION=null app php tests/concurrency.php
docker compose run --rm app composer validate --strict
docker compose run --rm app composer audit
docker compose run --rm app php artisan route:list
docker compose run --rm app php artisan schedule:list
```

Tests require a disposable database named `meda_test`, `APP_ENV=testing`, an empty DB_URL, and Redis. Fresh local Compose volumes create the test database automatically; for existing volumes create it explicitly as a database administrator. Tests refuse other database names. Never use production data. The concurrency script starts separate PHP processes and leaves synthetic fixtures in that disposable database.

`../.github/workflows/laravel.yml` runs dependency audits, the PHPUnit suite, native PostgreSQL/Redis concurrency checks, asset compilation, template/route compilation and production-image builds. The workflow is included but was not run remotely for this handoff. See [verification evidence](../docs/VERIFICATION.md) for checks actually executed here.

## Launch and integrations

Read [handoff](../docs/HANDOFF.md), [integration guide](../docs/INTEGRATIONS.md) and [production runbook](../docs/PRODUCTION_RUNBOOK.md). The default adapters are unavailable until explicitly enabled with approved provider settings. The production guard is deliberately strict: missing Fayda/Telebirr/SMTP/key/policy settings produce a maintenance response, not a silent mock flow.

The English UI and email notifications are implemented. The stored English/Amharic preference is for support; complete Amharic translation and SMS delivery are still separate work. This release covers municipal venues; private-owner onboarding and multi-owner payouts from the older reference demo are not part of this pilot.
