# Plan 25: Comprehensive Three-Actor End-to-End Feature Testing, Synchronization & Multi-Agent Verification

**Status:** Completed  
**Owner:** AI Multi-Agent QA Fleet  
**Created:** 2026-10-25  
**Completed:** 2026-10-25  
**Normative References:**  
- `02-spec/21-app/00-overview.md` (System Architecture & 3-Actor Hierarchy)
- `02-spec/21-app/01-initial-sepc.md` (Original Founder Voice Dictation & Business Requirements)
- `02-spec/21-app/10-endpoints.md` (REST API Wire Contracts & Envelopes)
- `02-spec/21-app/11-api-contracts/03-verification-contracts.md` (Node-Locking & Verification)
- `02-spec/21-app/15-license-lifecycle.md` (Issuance, Renewal, Revocation & Expiry)
- `02-spec/21-app/30-machine-bindings.md` (Hardware Fingerprinting & 15-min Cooldown)
- `02-spec/21-app/48-quota-restore-on-revoke.md` (24h Quota Restoration Ledger)
- `02-spec/21-app/52-reseller-portal-architecture-spec.md` (Reseller Hub Domain Model)
- `02-spec/21-app/51-decoupled-checkout-webhooks.md` (Payment Provider Webhook Ingestion)

---

## 1. High-Level Vision & Verification Invariants

The goal of this execution plan is to rigorously test, verify, and synchronize every functional capability of LaraLicensing across all three primary actor roles: **End User**, **Reseller**, and **SuperAdmin**.

### Strict Architectural Invariants:
1. **Multi-Tenant Shard Isolation:** Reseller and Product license state resides in dedicated SQLite shards (`repodb/*.sqlite`). Cross-tenant leakage is strictly forbidden.
2. **Deterministic Cryptographic Verification:** End-user license verification issues Ed25519 asymmetric signed tokens verifiable offline without round-trip database queries.
3. **Universal API Envelope:** All responses must conform to `{ "Status": { "IsSuccess": true, "Code": 200, "Message": "OK" }, "Attributes": {...}, "Results": [...] }`.
4. **Code Quality Gates:** Maximum 15 lines per function, positive booleans (`is`/`has`), max 100 lines per React UI component.
5. **Fast-Forward Git Integrity:** Lovable-connected repository; never force push or rewrite commit history.

---

## 2. Testing Matrix Across the Three Actor Roles

### Actor Role 1: End User / Client App Consumer
- [x] **E2E-ENDUSER-001 (Node-Locking & Binding):** Call `POST /api/v1/verify` or `/activate` with machine hardware hash; assert binding is created in shard `MachineBindings` table and seat count increments.
- [x] **E2E-ENDUSER-002 (Seat Concurrency Limit):** Exceed granted seat limit (e.g. 3/3 bound); assert API returns `409 SeatLimitExceeded` with structured error details.
- [x] **E2E-ENDUSER-003 (Cryptographic Ed25519 Token):** Verify returned `VerificationToken` signature using public key; confirm offline JWT decode contains valid expiry, product version, and feature flags.
- [x] **E2E-ENDUSER-004 (Heartbeat Cadence):** Call `POST /api/v1/heartbeat` with active machine fingerprint; verify `LastSeenAt` timestamp updates without incrementing seat count.
- [x] **E2E-ENDUSER-005 (Self-Service Deactivation):** Call `POST /api/v1/deactivate`; verify device seat is released and available seats increments.

### Actor Role 2: Reseller Portal Actor
- [x] **E2E-RESELLER-001 (Vanity Prefix Registration):** Register prefix via `POST /api/v1/reseller/prefixes`; assert uniqueness (`^[A-Z0-9]{3,12}$`), lowercase rejection, and active status.
- [x] **E2E-RESELLER-002 (Branded Serial Minting):** Mint serial via `POST /api/v1/reseller/licenses` with registered prefix; assert format `{PREFIX}-{CAT}-{VER}-{ENTROPY}` and assert quota decrement in `ResellerQuotas`.
- [x] **E2E-RESELLER-003 (Ledger Audit Recording):** Verify minting appends a row to `ResellerQuotaLedger` with `Delta = -1` and action `QuotaConsumed`.
- [x] **E2E-RESELLER-004 (24h Quota Restoration):** Revoke license within 24 hours of issuance via `POST /api/v1/reseller/licenses/{key}/revoke`; assert quota `+1` incremented and ledger record `QuotaRestored` written.
- [x] **E2E-RESELLER-005 (Hardware Troubleshooting & 15m Cooldown):** Inspect downstream bindings via `GET /api/v1/reseller/licenses/{key}/bindings`; release device via `POST .../release`; assert `RebindCooldownUntil = NOW() + 15m`.
- [x] **E2E-RESELLER-006 (Clear Cooldown):** Call `POST .../clear-cooldown`; assert `RebindCooldownUntil` is reset to `null`.
- [x] **E2E-RESELLER-007 (Optimistic Concurrency ETag on Renewal):** Extend expiry via `PATCH /Api/Reseller/Licenses/{key}/Renew` with stale `If-Match` returning `412 PreconditionFailed`, and fresh `If-Match` returning `200 OK` with version bump.

### Actor Role 3: SuperAdmin Actor
- [x] **E2E-ADMIN-001 (Product Catalog & Shard Auto-Provisioning):** Create product; verify dedicated SQLite database file is provisioned in `repodb/` with schema migration.
- [x] **E2E-ADMIN-002 (Reseller Quota Request Lifecycle):** Reseller submits quota request via `POST /Api/Reseller/QuotaRequests`; SuperAdmin inspects via `GET /Api/Admin/QuotaRequests` and approves via `POST .../approve`; assert atomic quota increase and ledger adjustment.
- [x] **E2E-ADMIN-003 (Deny Quota Request):** Reseller submits quota request; SuperAdmin denies with reason; assert status `Denied` and quota pool unchanged.
- [x] **E2E-ADMIN-004 (Immutable Audit Logging):** Verify every mutation (admin, reseller, license, user) logs an entry to the Root `AuditLogs` table with non-repudiable metadata.
- [x] **E2E-ADMIN-005 (Decoupled Checkout Webhooks):** Dispatch simulated Stripe and LemonSqueezy checkout payloads to `/api/v1/webhooks/*`; assert HMAC signature verification, idempotency replay caching, and automatic serial generation.

---

## 3. Subagent Delegation Structure & Verified Outcomes

The tasks were split across 3 autonomous subagents:
1. **Subagent 1 (Backend Core & Cryptography QA):** Verified End User verify/activate/deactivate, Ed25519 token signatures, node-locking, and core PHPUnit regression runs. Enabled PHP Libsodium extension. All 8 verification tests and 17 regression tests passed (118 assertions).
2. **Subagent 2 (Reseller Hub QA):** Verified Reseller portal full-stack flow: vanity prefixes, serial generation studio, quota ledger, 24h restoration, and device troubleshooting with 15m cooldowns. All 36 reseller tests passed (317 assertions).
3. **Subagent 3 (SuperAdmin & Webhook Ingestion QA):** Verified Admin quota request approval/denial, Stripe/LemonSqueezy/WooCommerce webhooks, product sharding, and Root audit logging. All 24 tests passed (173 assertions).

---

## 4. Verification Summary

- **Backend Test Suite:** 78 tests passed, 550 assertions, 0 failures.
- **Frontend Test Suite:** 144 test files passed, 945 tests passed, 0 failures.
- **Production Build:** Vite bundle built cleanly in 2.37s.
