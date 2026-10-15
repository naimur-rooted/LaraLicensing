# Licensing System Testing Methodology & Verification Reference

Summary of testing protocols documented in [`docs/licensing-testing-methods.md`](file:///c:/Users/nahid/OneDrive/Desktop/riseup-practical-assignment/Licencing/docs/licensing-testing-methods.md).

## Core Principle: Real External Software Is NOT Required
- External apps interact with the licensing system purely over HTTP REST APIs (`/api/Portal/Verify/*`, `/api/App/*`).
- Any HTTP tool (cURL, Postman, Bruno, Python/Node scripts) can simulate the client software, hardware hashing, and token consumption with 100% fidelity.

## The 5 Verification Methods
1. **API Client Simulation (REST Handshake):**
   - Step 1: `POST /api/Portal/Verify/Serial` - Check serial validity & status.
   - Step 2: `POST /api/Portal/Verify/Hash` - Submit SHA-256 hardware hash & acquire single-use `VerifyKey` (5-min TTL).
   - Step 3: `POST /api/Portal/Verify/Final` - Bind machine, check environment, return authorized features & expiry.
   - Self-Update: `GET /api/App/UpdateManifest` & `.sig` Ed25519 signature download.
2. **Web Portal & Seed Mode UI Testing:**
   - Explore via `http://localhost:8080/admin/login` using `Ctrl + Shift + D` (Seed Mode Identities: SuperAdmin, Reseller, AppBuilder).
   - Test quota deduction, prefix registry, reseller management, and 30-minute capped impersonation.
3. **Automated Test Suites:**
   - Backend: `php artisan test` (sanctum, HMAC signatures, quota ledgers, Casbin RBAC).
   - Frontend: `pnpm test` (Vitest component & seed mode tests).
4. **Standalone Mock Client Script:**
   - Ready-to-run 30-line script simulating hardware ID hashing and license activation.
5. **Database & Shard Isolation:**
   - Verify Root DB (`lara_root.sqlite`) vs Reseller Shard DBs (`lara_tenant_{id}.sqlite`).
   - Validate immutable append-only `reseller_quota_ledgers`.
