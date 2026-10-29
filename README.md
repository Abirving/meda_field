# Meda Pass — application handoff

Updated 29 October 2026 against `Meda.pdf` and the supplied production-readiness audit.

**Use `laravel/` for the real shared booking application.** This release adds the application changes needed for a municipal pilot: verified-identity onboarding, account recovery, staff MFA, durable payment attempts, signed provider adapters, booking/closure protections, payment review and deployment tooling. Live launch still requires provider certification and operational acceptance. No real identities, payments or messages were used in validation.

| Location | Purpose |
| --- | --- |
| `laravel/` | Canonical application: Laravel 12, PHP 8.3+, PostgreSQL 16, Redis 7, Filament 3, Blade/HTMX/Alpine and Reverb. |
| `docs/HANDOFF.md` | Fixes mapped to the audit, remaining launch requirements and known limitations. Read this first. |
| `docs/PRODUCTION_RUNBOOK.md` | Production configuration, deployment, backups, monitoring and rollback. |
| `docs/INTEGRATIONS.md` | Fayda and Telebirr setup/certification requirements. |
| `docs/VERIFICATION.md` | Exact validation evidence and checks still required. |
| Root `app/`, `lib/`, `db/` | Original React/D1 reference demo. It remains a separate private sandbox and does not share Laravel inventory. |

## Run locally

Install Docker with Compose, then:

```sh
cd laravel
cp .env.example .env
# Choose a unique MEDA_SEED_PASSWORD of at least 12 characters in .env.
bash setup.sh
```

Open `http://localhost:8000`. Staff use `/admin`; gatekeepers use `/gate`. See [Laravel instructions](laravel/README.md) for sample accounts, email verification, MFA and tests. Local mode explicitly simulates Fayda and payments; use synthetic data only.

**Production uses `compose.production.yaml`, not the development server.** Copy `.env.production.example` and follow the production runbook. Public production requests and service startup fail closed while required provider/security configuration is missing. The example intentionally contains no live keys.

## Booking rules

A reservation covers 60 minutes of play plus 15 minutes of changeover. Holds last up to five minutes. Store credit is reserved atomically; unpaid holds release it on expiry/failure. Cancellation is available strictly more than two hours before play and returns the full price as store credit. Venue closure and uncertain/late payment cases have a separate reviewed resolution. Browser payment returns never prove payment. QR entry checks the paid reservation, assigned field, current play hour and single use. Times display in Ethiopia time (UTC+3).

## Original reference demo

The root Node project is retained for comparison with the earlier design. It is not the production entry point. Its demo password, sample FANs, role switching and per-viewer D1 sandbox must never be used for real reservations. Existing private hosting was not changed. See [demo infrastructure](docs/DEMO_INFRASTRUCTURE.md).

The source ZIP excludes private environment files, keys, databases, logs and downloaded dependencies. It includes Composer/npm lockfiles and compiled Laravel/Filament frontend assets. `setup.sh` reinstalls dependencies and rebuilds assets. This is a tested implementation handoff, not a live-payment certification or deployment.
