# Pilot release plan

The original eight-week sequence is retained as a planning framework, not a claim about time elapsed or completion.

| Phase | Foundation delivered | Remaining acceptance |
| --- | --- | --- |
| 1 · Weeks 1–3 | Supported Laravel runtime, locked dependencies, shared inventory, onboarding/account flows, staff MFA/recovery, scoped administration and slot/credit protections | Native PostgreSQL concurrency/CI, browser role/form review, verified venue data and production topology |
| 2 · Weeks 4–6 | Signed configurable Telebirr H5 adapter, durable attempts/receipts, query reconciliation, customer uncertainty UI and payment cases | Approved merchant mappings/test vectors, all sandbox money/retry/closure cases, SMTP delivery and settlement/refund process |
| 3 · Weeks 7–8 | Fayda OIDC/PKCE verification, current-hour QR gate, schedule refresh, closure operations, production guard/monitor/backup tooling | Licensed Fayda certification, real mobile/camera and slow-network testing, load/security review, restore rehearsal and operator pilot acceptance |

## Next practical steps

1. Run `.github/workflows/laravel.yml` against native PostgreSQL/Redis. Review production image builds and test the stack privately.
2. Obtain the actual Fayda/Telebirr contracts, keys and test fixtures. Complete `INTEGRATIONS.md`; adapt protocol fields only from approved documentation.
3. Configure real SMTP, operator/support channels, venues and policy approvals. Test signup → verified contact → hold → payment → pass → gate → cancellation/refund review.
4. Exercise concurrent requests and crash recovery; rehearse backups/restoration and provider reconciliation; assign daily operational ownership.
5. Approve a small monitored municipal pilot before expanding capacity or adding private-owner settlement/mini-app/SMS features.

See `VERIFICATION.md` for executed checks. The code has passed the local PHP regression suite with a PostgreSQL-compatible WASM backend and Redis; native production deployment and live-provider acceptance remain open.
