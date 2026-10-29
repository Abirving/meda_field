# Verification record

29 October 2026. This record describes evidence from the updated source, not a deployed real-money service.

## Executed successfully

| Check | Result |
| --- | --- |
| PHP regression suite | **55 tests, 167 assertions passed** on PHP 8.3.6, Laravel 12.70.2, Redis and PGlite 0.5.12 with btree_gist through pglite-socket 0.2.15. |
| Booking and credit | Exclusivity/buffer exclusion, idempotent holds, expiry/release, cancellation boundary, credit conservation, closure and late-payment review, changed calendars and hold limits. |
| Authentication/access | Public role isolation, verified-contact gate, password recovery/token reuse/session revocation, MFA enrollment/replay, disabled users, customer receipt isolation, last administrator and scoped jurisdiction. |
| Provider fixture security | PKCE/state consumption/expiry, RS256 signature/issuer/audience/nonce/expiry rejection, both explicit Telebirr RSA algorithms, signed/tampered/ambiguous payloads, merchant/currency/reference validation, signed preorder, wrong-order query rejection. |
| Server UI | Public field/login/account/booking/receipt/QR routes, admin resource pages and a Livewire edit/suspend action; missing production configuration returns 503. |
| Dependency validation | Composer validate --strict passed; Composer audit reports no advisories or abandoned packages; Laravel npm audit reports zero vulnerabilities at this check. Symfony Mime updated to 7.4.22 for the newly reported advisory. |
| PHP/static checks | 98 authored PHP files linted without syntax errors; shell scripts parsed; git diff whitespace check passed. |
| Application compilation | Blade template cache, route cache and Vite production assets built; scheduler definitions listed with Redis running. |
| Unchanged reference demo | 20 service tests passed; TypeScript typecheck passed. This evidence applies only to the separate React/D1 demo. |

The Vite build emits HTMX's upstream dynamic-evaluation warning. A restrictive CSP and full browser compatibility review remain staging acceptance work; no strict CSP certification is claimed.

The PHP runtime and Redis were installed into a temporary validation environment. PGlite runs the PostgreSQL engine in WebAssembly and supports the tested exclusion constraint. Its socket adapter multiplexes connections and is **not native multi-process PostgreSQL concurrency evidence**. The harness used PostgreSQL unnamed parameterized statements (`DB_DISABLE_PREPARES=true`) to avoid socket-adapter prepared-statement collisions. Production defaults to native prepared statements.

Provider tests use generated ephemeral RSA keys, synthetic claims and HTTP fakes. They send no requests to Fayda/Telebirr and no real payments or email. Validation artifacts in `docs/validation/` contain the final PHP test/audit outputs.

## Included but not executed here

- Native PostgreSQL 16 + Redis CI with separate PHP process races for same-slot exclusivity and shared-wallet conservation: `.github/workflows/laravel.yml`, `laravel/tests/concurrency.php`.
- Production Docker image builds/startup, Caddy TLS/proxy/networking, actual queue/Reverb process supervision and real SMTP delivery.
- Encrypted/off-host backup scheduling, native restore script execution and recovery rehearsal.
- Live Fayda/Telebirr certification, merchant signing vectors, refund/settlement report reconciliation, phone/camera hardware and embedded Telebirr shell.
- Full Laravel browser visual/accessibility acceptance, load/security review and weak-network reconnect behavior. HTTP/Livewire assertions do not replace these checks.

Do not describe this ZIP as a launched or certified production system. Run the remaining checks on staging, preserve the evidence and approve the operational/policy/provider gates before accepting real users or money. Dependency audits must be repeated at deployment and regularly thereafter.
