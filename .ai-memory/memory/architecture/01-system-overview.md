# System Overview & Domain Architecture

**File:** `.ai-memory/memory/architecture/01-system-overview.md`  
**Reference Document:** `docs/repository-analysis.md`  
**Recorded Date:** 2026-10-13  

---

## 1. System Identity

- **Product:** `LaraLicensingV1` (`alimtvnetwork/lara-licensing-v4`)
- **Domain:** Multi-tenant Software Licensing, Hardware-Binding Verification, and Client Self-Update Distribution Platform.
- **Backend:** Laravel 11 / PHP 8.3 REST API with Sanctum JWT, OAuth2 client-credentials, and Casbin RBAC.
- **Frontend:** React 19 / TanStack Start operator dashboards for Admins, Resellers, and End-User verification.
- **Database Architecture:** Split SQLite / Multi-tenant partitioning:
  - **Root DB:** Platform users, roles, permissions, global reseller directory, prefix registry, audit logs, and runtime configurations.
  - **Shard DB:** Dedicated database per reseller managing reseller licenses, license ledgers, machine bindings, and quota request tracking.

---

## 2. Core Functional Pillars

1. **License Issuance & Categories:**
   - Categories: `Daily`, `Weekly`, `Monthly`, `Yearly`, `Lifetime`, `Dev`, `Key`.
   - Variations: `User` concurrency limits and `Machine` count constraints.
   - Serials: Formatted with reseller prefixes (e.g. `ALIM-XXXX-YYYY`) and embedded metadata.

2. **3-Step Client Verification Handshake:**
   - Step 1: `/Verify/Serial` validates active serial status.
   - Step 2: `/Verify/Hash` takes client hardware fingerprint (motherboard, MAC, disk UUID) and mints a single-use 5-minute `VerifyKey`.
   - Step 3: `/Verify/Final` executes transactional binding, checks environment compatibility (`Development`, `Staging`, `Production`), resolves tier features, and returns authorization payload.

3. **Reseller Quotas & Append-Only Ledger:**
   - Reseller quota consumption is transactional and decremented in sync with license minting.
   - Invariant: `SUM(Delta) = -LicensesConsumed` enforced via `ResellerQuotaLedger`.
   - Resellers submit `QuotaRequests` which Admins approve or deny.

4. **Client App Self-Update Service:**
   - Serves update manifests at `/App/UpdateManifest`.
   - Delivers binary assets at `/App/UpdateAsset/{Version}/{Platform}`.
   - Enforces detached Ed25519 cryptographic signatures (`.sig`) for binary tampering prevention.

5. **Security & Auditing:**
   - Structured audit logging (`AuditLogs`) on every mutation.
   - Admin-to-user impersonation sessions capped at 30 minutes with strict audit attribution.
   - PII protection using rotating cryptographic salt hashing for IP and User-Agent data.
