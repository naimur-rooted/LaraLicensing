# TTL Memory: Three-Actor End-to-End Feature Testing & Verification Spec

**Category:** Specification / Verification Matrix  
**Status:** Active  
**Last Updated:** 2026-10-25  

## 1. System Actor Contracts
- **End User / Client App:**
  - Machine node-locking via SHA-256 fingerprint hash.
  - Verification issuing asymmetric Ed25519 cryptographic tokens.
  - Seat count concurrency enforcement (`409 SeatLimitExceeded`).
  - Heartbeat status refreshes and client-side deactivations.
- **Reseller:**
  - Vanity prefix registration (`^[A-Z0-9]{3,12}$`).
  - Branded serial number generation `{PREFIX}-{CAT}-{VER}-{ENTROPY}`.
  - Transactional quota deduction and ledger tracking (`ResellerQuotaLedger`).
  - 24-hour quota restoration on license revocation.
  - Downstream machine binding inspection, 15-minute rebind cooldown, and manual cooldown release.
  - Optimistic concurrency control using `If-Match` ETags on license renewal.
- **SuperAdmin:**
  - Product sharding with automated SQLite tenant provisioning.
  - Reseller quota request lifecycle (review, approve with delta bump, deny).
  - Centralized immutable audit logs (`AuditLogs`).
  - Decoupled payment checkout webhook ingestion (Stripe, LemonSqueezy, WooCommerce).

## 2. Testing & Verification Protocol
- All tests must verify both database persistence and wire API envelope compliance.
- Code changes must adhere to max 15 lines per function, positive booleans, and no history rewriting.
