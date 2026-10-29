# Attack and Security Test Cases (to run after the full suite at 7404ea8)

Status: CASES WRITTEN, NOT YET RUN. Test data only. No real customer data, no production system, no real payment gateway. Expected results are from code/spec, not from a run.

## A. Fraud: actually try to fraud LaraLicensing

- A1 Cracked client: test client that skips the verify condition entirely and unlocks premium locally. Expected: local unlock proves nothing about the server; every server-gated feature must still refuse without a valid, unexpired, fingerprint-bound token. Record which features, if any, trust the client check alone.
- A2 Tampered file: change one release file hash, submit the manifest to `POST /v1/products/{slug}/tamper-verify`. Expected: isValid false, mismatch listed, failClosed true, tamper event logged to shard SecurityAuditEvents, no auto-revoke from the client report alone.
- A3 Old version: submit a correctly signed older manifest when a newer manifest sets a higher minVersion. Expected: VersionBelowMinimum, even though the old signature is valid.
- A4 Wrong alg/kid: submit a manifest with alg not ed25519, or kid not matching the product public key hash. Expected: invalid, failClosed true.
- A5 Shared key: activate the same serial on two distinct fingerprints, then a third, up to and beyond allowed seats. Expected: seat-limit 409 at the limit, concurrent bindings counted by LicenceGate, fraud score +40 for server-observed concurrency above seats.
- A6 Stolen key replay: replay a heartbeat and a verify request with a copied serial from a different fingerprint/IP. Expected: fingerprint/IP drift scored, not silently accepted as the original machine; seat binding must not auto-move without the rebind cooldown path.
- A7 Heartbeat abuse: stop heartbeating one binding, wait past the 45-minute cull threshold (30-minute window + 15-minute resurrection), run `lara:heartbeats:cull --dry-run` then a live cull on test data only. Expected: dry-run lists the stale binding and releases nothing; live cull releases the seat only, never deletes the licence, writes an audit row.
- A8 Auto-quarantine trigger: create recent live bindings within 45 minutes above allowed seats, run `lara:fraud:auto-quarantine --dry-run`. Expected: quarantine only on recent live concurrency, not on stale bindings, not on tamper events alone. Dry-run quarantines nothing.
- A9 Token replay: replay an offline Ed25519 licence token after revocation, and after expiry. Expected: revoked is final, expired is rejected on server time, not client clock.

## B. Security and rate limiting

- B1 No auth: call developer, reseller, and admin endpoints with no Sanctum token. Expected: 401, no state change.
- B2 Wrong role: authenticated non-admin calls admin endpoints; authenticated non-reseller calls reseller endpoints; reseller calls another reseller's licence/quota. Expected: 403 or scope denial, no leak (other reseller's serial must return LicenseNotFound/scope denial, not the other tenant's data).
- B3 Tenant override: call a loose preview route with `X-Reseller-Id` header and `?ResellerId` query for a different reseller, and a strict route with the same overrides. Expected: record both behaviours; they must not silently return tenant 1 / slug `alim` data to an unauthenticated or wrong-tenant caller.
- B4 Webhook abuse: `POST /v1/products/{slug}/licenses/issue` with missing signature, bad signature, unsigned body, unknown dealCode, invalid email, replayed transactionId+email. Expected: 401 on missing/bad signature, no DEFAULT fallback, same serial on replay via md5 derivation with COALESCE fill only, no duplicate licence row.
- B5 Multi-gateway abuse: Stripe webhook with timestamp older than 5 minutes or in the future, LemonSqueezy with bad HMAC, WooCommerce with bad signature, unsupported event type, replayed event id. Expected: rejection, no issuance, deduplication via Root WebhookEvents.
- B6 Rate limiting: serial lookup rapid repeat (spec: 5 per minute per IP), heartbeat rapid repeat (60 per minute per fingerprint), auth rapid repeat. Expected: 429 with the contract error, then recovery after the window, no bypass by changing a header alone.
- B7 Idempotency abuse: quota submit twice with the same 32-character key and different payload. Expected: IdempotencyConflict handling, not a second row and not a silent overwrite. Missing key: IdempotencyKeyRequired message, no row created.
- B8 Session abuse: revoked/expired Sanctum session, impersonation timeout, refresh reuse. Expected: 401, no state change, security event logged.
- B9 Email approval forgery (negative): assert no approve-by-email endpoint exists for reseller applications or quota requests. A crafted link/email must not change state; expected endpoint absent (404/405) or panel-only with auth.

## C. Dashboard sync attack surface (not a security exploit, a correctness check)

- C1 After quota approve, read shard `Quotas`/`LicenseLedger` and Root `ResellerQuotas`/`ResellerQuotaLedger` immediately, then read the reseller gauge, developer overview, and admin metrics. Record every disagreement with its source table.
- C2 Make one shard unreachable, read admin metrics and quota inbox. Expected: request succeeds with `Warnings`/`ShardUnavailable`, totals must not silently pretend the tenant does not exist.
- C3 Create a product whose shard exists only in `repodb`, read developer overview. Expected per code: product skipped silently, zero shown. Record it as drift if confirmed.

## D. Not in this run

- Real code signing and store submission (guidance only).
- Extension thin client implementation (guidance only).
- Load/stress at scale, dependency vulnerability scan (record as missing evidence if not run).
- Playwright UI click-through (needs user-approved browser install).
- Real payment through a developer's own gateway.
