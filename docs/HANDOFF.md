# Production-readiness implementation handoff

29 October 2026 · Scope: shared municipal field booking pilot, website checkout first.

This release addresses the supplied Meda Pass Production Readiness Audit. Application changes are implemented; external contracts, merchant certification, policy approval and hosting acceptance remain release gates. No production service was deployed or changed.

| Audit area | Delivered changes | Remaining acceptance |
| --- | --- | --- |
| One shared system | Laravel/PostgreSQL/Redis designated canonical; root D1 app retained only as reference | Provision production infrastructure and import verified venue records; no automatic D1/demo migration |
| Signup/login | Fayda authorization-code/PKCE adapter, signed ID-token/issuer/audience/nonce checks, stable verified-subject identity mapping, contact verification, terms acceptance and safe guest return | Approved Fayda tenant, contract claims/endpoints/keys and real-device sign-in certification |
| Account recovery/staff | Staff invitations, password recovery, session revocation, mandatory production TOTP, encrypted secrets and single-use recovery codes; last-admin protection | Verified SMTP delivery, enrollment/recovery drills and accountable staff provisioning |
| Booking integrity | PostgreSQL range exclusion, Redis holds, field/user locks, hold limit, minimum notice, weekly calendars, dated closures, safe maintenance and credit restoration | Native concurrent PHP/PostgreSQL acceptance, load testing and real venue hours/prices |
| Telebirr checkout | One durable merchant order per booking; signed H5 preorder and checkout adapter; verified callback/query normalization, exact amount/currency/merchant checks | Approved merchant contract/test vectors, actual endpoint/field/acknowledgement mapping and provider certification |
| Payment recovery | Durable normalized receipts, replay handling, unknown-request query reconciliation, late/contradictory charge review, administrator evidence/resolution and read-only attempt history | Reconcile against provider settlement reports; verify refund procedures and support response times |
| Access and oversight | Staff jurisdiction policies, protected role changes, disable/revoke flow, audited field/account/payment actions, customer receipt authorization | Independent permission/security review and operational access drills |
| Runtime and deployment | Laravel 12 upgrade, committed Composer lock, dependency patch, production images/Compose, production guard, doctor/operations commands, backup/restore scripts and CI | Run CI on native PostgreSQL, build/start the stack, restore rehearsal, monitoring, TLS and backup custody |
| Customer journey | Signup/account/support/terms/privacy, payment uncertainty messaging, printable payment record, credit ledger, booking refresh and gate schedule refresh | Approve legal/operator text, mobile accessibility/camera checks, translation and actual email delivery |

## Decisions and preserved rules

- Municipal pilot only. Roles: USER, OPERATOR, WOREDA_ADMIN, SUBCITY_ADMIN, SUPER_ADMIN. Staff are invited; public signup always creates a USER.
- Production customers sign in through the approved Fayda flow. Entering a FAN alone is never proof of identity. Email/password recovery is for staff, not a second customer login that could merge identities incorrectly.
- 60-minute play, 15-minute buffer, up to five-minute holds, no more than two simultaneous pending holds per customer, booking within 30 days and more than five minutes ahead.
- Cancellation strictly more than two hours before play returns store credit. Venue closure is separately reviewed. A reschedule is cancellation plus a new reservation.
- Unknown checkout requests are not re-posted. Browser returns never settle money. Late payment never resurrects an unavailable reservation. A charge can require manual resolution even when no pass is issued.
- Cash refunds remain an externally verified operation. Selecting EXTERNAL_REFUND records an already verified refund; it does not send one. Store-credit resolutions require customer agreement and documented provider evidence.
- APP_KEY encrypts sensitive application values; a separate versioned key signs new QR passes. Old passes without a key ID use the old APP_KEY until retired.

## Launch blockers the operator must complete

1. Obtain approved Fayda and Telebirr integrations; confirm all protocol details and complete the cases in INTEGRATIONS.md. Do not set approval flags based only on the local tests.
2. Run the included native PostgreSQL/Redis CI and production container builds, then exercise simultaneous requests, process crashes, expiry, mail, Reverb reconnect/polling and backups on staging.
3. Load real venues, operator assignments, prices, addresses, calendars and opening policies. Resolve any duplicate case-insensitive email addresses before applying the new migration to an existing database.
4. Approve the named operator, contact channels, cancellation/refund/credit/identity/privacy/retention policies. Draft pages are not legal approval. Assign ownership and response times for payment cases.
5. Configure real SMTP, HTTPS, secret custody, off-host encrypted backups, log access, alerts, restoration and key recovery. Test every staff role and a full customer journey on real mobile devices.

## Known limits

- Provider adapters are implemented from documented protocol patterns and tested with local signed fixtures. Their exact compatibility with the merchant's active contract is not certified.
- Native PostgreSQL multi-connection concurrency, Docker deployment, real SMTP/Reverb networking, camera hardware, load/security testing and full browser visual acceptance were not executed in this workspace. Server-rendered pages and selected Livewire admin actions are exercised by PHP tests.
- Complete Amharic UI/notifications, SMS/phone OTP, private-owner onboarding/payouts, maps/PostGIS and embedded Telebirr mini-app/Macle navigation remain outside this municipal website release.
- Email jobs use Laravel queues. Queue failures require monitoring/retry; this release does not implement a separate transactional notification outbox. Payment records remain the source of truth even if an email is delayed or missed.
- Staff who lose both the authenticator and recovery codes require an audited, independently verified operator-assisted reset; there is no public MFA bypass. Data export/deletion/retention operations need an approved operator process, not a fabricated automatic legal policy.
- Framework support requires continued maintenance. Laravel 12 security support ends 24 February 2027; plan a tested framework/Filament upgrade before that date. A clean dependency audit is point-in-time evidence.
