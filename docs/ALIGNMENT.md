# Alignment to Meda.pdf and the production audit

The supplied [Meda.pdf](Meda.pdf) remains the product blueprint. The later production-readiness audit changes the implementation from a mock-only demo into a deployment foundation with explicit live launch gates.

| Blueprint | Current implementation / deliberate adjustment |
| --- | --- |
| Laravel 11 monolith, PostgreSQL, Filament | Laravel **12**, PHP 8.3+, PostgreSQL 16 and Filament 3.3. Laravel 11 was upgraded for support/security; the architecture is preserved. |
| Blade, HTMX, Alpine, Reverb | Implemented in `laravel/`; server rendering and frontend compilation tested. Real browser/network Reverb acceptance remains pending. |
| Five-minute Redis holds | Redis owner locks, PostgreSQL transactions/range exclusion and scheduled/request-time expiry. |
| 60-minute play + 15-minute changeover | Separate end_time and reserved_until; the entire reservation fits opening hours. Weekly schedules and closure windows added. |
| FAN mock | Retained only for local/testing. Live signup uses verified OIDC/PKCE, never FAN-only entry. |
| Unique required national ID | Changed: legacy national_id is nullable; production identity uses a unique provider + HMAC(subject) mapping. Staff do not need synthetic national IDs in production. |
| Staff/gatekeeper credentials | Password invitation/recovery, mandatory production TOTP/recovery codes and session revocation added. |
| Region → Subcity → Woreda | Preserved with scoped staff/field administration and assigned gatekeepers. |
| Cancellation strictly >2h, full store credit | Preserved with transactional reserve/release/cancellation ledger; no automatic cash refund. |
| Correct-field/time/paid single-use QR | Preserved; new passes use independent versioned signing keys, and closure/contradictory-payment passes are invalidated. |
| Telebirr checkout and callback | Signed configurable H5 adapter, durable attempts/receipts/query reconciliation and reviewed remedies added. Merchant certification is still required. |
| Queued confirmation | Email confirmations, reminders and closure/resolution messages; real SMTP acceptance pending. |
| Maps/PostGIS future work | Still deferred; no geospatial or private-owner payout claims. |

PostgreSQL uses UUID rather than the PDF's BIGUUID term. Roles explicitly include SUBCITY_ADMIN and WOREDA_ADMIN. Gate time remains start-inclusive/end-exclusive; the exact two-hour cancellation boundary is rejected. Prices and gateway balances are server-authoritative. Rescheduling remains cancellation followed by a new booking.

The root React/D1 private sandbox is retained for design comparison. It is not a production Laravel deployment or shared live inventory. See [handoff](HANDOFF.md) and [verification](VERIFICATION.md) for scope and evidence.
