# Fayda and Telebirr integration guide

Both live adapters are disabled by default. Local cryptographic tests prove rejection/normalization behavior against generated fixtures; they do not certify compatibility with a provider's active contract. Supply secrets through the deployment secret store/files, never through source control or browser code.

## Fayda customer identity

`App/Services/FaydaOidc.php` implements OIDC authorization code + PKCE S256. It uses one-use session state and nonce, a ten-minute authorization window, HTTPS token/JWKS endpoints, RS256 signature verification, token expiry/issued-at checks and exact issuer/audience/authorized-party/nonce validation. Client authentication supports `private_key_jwt` or the explicitly configured `client_secret_basic` method.

Configure FAYDA_ENABLED, ISSUER, AUTHORIZATION_URL, TOKEN_URL, JWKS_URL, CLIENT_ID, CLIENT_AUTH and the matching credential variables in `.env.production`. Register the exact HTTPS redirect URI `/auth/fayda/callback`. Confirm scopes and claim names with the licensed provider. The implementation expects a standard `id_token` with a stable `sub`; it is not a generic FAN lookup or OTP API. If the approved tenant exposes a different flow, adapt and certify it before enabling the flag.

Account identity is HMAC(issuer + subject, FAYDA_SUBJECT_PEPPER), stored in `verified_identities`. Keep this pepper stable and backed up securely; changing it without a migration creates different identity mappings. A name/email from another account is never used to merge identities automatically. Public signup can create only USER. Raw FAN, DOB and portrait are not required by this live onboarding. Legacy/mock accounts are not automatically migrated into verified identities.

The profile asks for a contact email, verifies ownership with a signed email link and records the accepted terms version. Existing customers recover through the same Fayda subject. Staff accounts use a separate invitation/password/MFA flow. An approved recovery/support procedure is required if a customer cannot recover access through the identity provider.

Provider acceptance cases: new/repeat identity; rejected consent; expired/wrong/replayed state; wrong nonce/issuer/audience/signature; signing-key rotation; server timeout; stable subject across sessions; disabled user; no automatic email merge; profile/email verification; logout and return to the selected field. Test these in the approved sandbox and on real mobile browsers.

## Telebirr website H5 checkout

Relevant code: `TelebirrH5`, `TelebirrSignature`, `Payments`, `BookingEngine`, `PaymentResolution` and `TelebirrWebhookController`.

1. The server creates one `payment_attempts` row and unique merchant order for the unpaid ETB remainder of a pending booking. No provider POST happens before this durable record.
2. The configured token endpoint supplies a fabric token. A signed `payment.preorder` request includes the merchant/order, server amount, callback URL and hold-linked expiry. A signed provider response supplies prepay ID. The customer is sent only to the configured HTTPS checkout origin/path.
3. `/api/payments/telebirr/callback` verifies the provider signature before trusting merchant/order/amount/currency/status/reference. It persists a normalized receipt and settles under database locks. The browser return page merely displays/polls server state.
4. An interrupted request stays UNKNOWN. Reopening checkout never creates a second merchant order or blindly repeats the POST. Scheduled queries reconcile REQUESTED/PENDING/UNKNOWN attempts; administrators can request another status check from Checkout attempts.
5. Duplicate receipts have one financial effect. Late success or contradictory results enter a payment case and never generate an invalid pass. Relevant paid passes are quarantined. A customer-facing uncertainty message says not to pay again for that booking while support reconciles it.

### Required merchant confirmation

Fill every TELEBIRR variable from the active merchant contract. Confirm token/preorder/query/checkout URLs, app IDs, merchant code, token authorization prefix, timeout format, signing keys, request fields, signed response shape, exact status and provider-reference fields, query method, success/failure values and callback acknowledgement body. Defaults such as `payment.queryorder`, `payment_order_id`, `trade_status`, `SUCCESS`, `FAILED,CLOSED` and `success` are configuration starting points, not certification evidence.

Choose the approved RSA algorithm explicitly: `pss-sha256` (SHA-256, MGF1 SHA-256, 32-byte salt) or `pkcs1-sha256`. The public signature guide's algorithm descriptions/examples are not sufficient to choose a merchant contract setting automatically. Obtain provider test vectors. The implementation flattens `biz_content`, sorts keys, excludes sign/sign_type and rejects duplicate keys, unsupported value types and ambiguous `&`/`=` values. Compare this byte-for-byte with the approved contract; change the canonicalizer with fixture coverage if the provider specifies a different encoding. TLS verification and HTTP redirect suppression remain enabled.

Preorder/query responses currently require signatures. A provider contract with a differently nested/unsigned response format needs an explicit reviewed adaptation; never skip verification silently. Callback bodies are limited to 32 KiB. No provider secrets, raw payloads or checkout credentials are logged by these adapters.

Only after passing merchant sandbox acceptance set both TELEBIRR_ENABLED and TELEBIRR_CONTRACT_APPROVED to true. The approval flag is an operator assertion, not proof the provider approved the deployment.

### Merchant acceptance matrix

Exercise valid checkout; wrong signature/key/merchant/order/amount/currency; duplicate callback; callback before preorder response; browser return before callback; lost HTTP response; callback/query disagreement; failure then success and the reverse with the same/different reference; query returning a different order; duplicate actual charge; paid-after-expiry; closure during payment; credit-only and partial-credit bookings; cancelled pass; worker restart; provider outage; same order replay; store-credit and externally verified cash-refund resolution. Reconcile transaction totals and booking/credit ledgers against the provider report.

This release has no automated refund API, settlement-file importer, merchant payouts or embedded mini-app/Macle SDK. Website H5 checkout is the first integration target. Payment case resolution records evidence and an agreed remedy. Do not mark EXTERNAL_REFUND until the provider independently confirms the refund; never use NO_CHARGE when a charge remains uncertain. Follow the runbook to query and escalate uncertain attempts without inventing a successful payment.

## Email and notifications

Use a verified SMTP sender with SPF/DKIM/DMARC and test inbox delivery. SMTP requires TLS. Set MAIL_SCHEME=smtp with port 587 for STARTTLS, or smtps with port 465 for implicit TLS as supported by the provider. Test verification, invitations, recovery, confirmations, reminders and closure/resolution messages. Inspect failed jobs and queue health. Local log transport sends no external email. Current customer notifications are English email; SMS and full Amharic translation are pending.

## Primary references

- Fayda authentication: https://www.id.gov.et/authentication
- Telebirr H5 introduction: https://developer.ethiotelecom.et/docs/H5%20C2B%20Web%20Payment%20Integration%20Quick%20Guide/Introduction
- Telebirr signature process: https://developer.ethiotelecom.et/docs/H5%20C2B%20Web%20Payment%20Integration%20Quick%20Guide/Request_signature_Process
- Laravel 12 release/support policy: https://laravel.com/docs/12.x/releases
- Laravel authentication: https://laravel.com/docs/12.x/authentication
- Filament support policy: https://filamentphp.com/docs/4.x/introduction/version-support-policy

The merchant's current approved contract and test vectors take precedence over assumptions in this adapter. Do not copy credentials or disable certificate verification from forum examples.
