# Architecture Memory: Three-Actor Capability Licensing Ecosystem

Reference: `02-spec/21-app/51-platform-ecosystem-and-capability-spec.md`  
Status: Authoritative  
Updated: 2026-10-19  

---

## 1. Core Paradigm: Three-Actor Model

The system enforces three distinct operating tiers:

1. **Platform Layer (Us):**
   - Central authority providing SaaS licensing infrastructure.
   - Sells subscription tiers (Free, Essential, Pro, Max, Lifetime) to software developers.
   - Operates Root DB (`database.sqlite`) managing developer accounts, global quotas, and subscription state.

2. **Developer / Reseller Layer (Our Customers):**
   - Software creators building apps, extensions, plugins, or tools.
   - Owns Products in the portal (e.g. Chrome extensions, desktop software).
   - Provisions product-specific pricing deals, machine slot limits, and capabilities.
   - Generates AI-ready integration instructions for autonomous codebase integration.

3. **End Consumer Layer (Downstream Users):**
   - End users who purchase licenses from developers.
   - Validate licenses through Zero-Trust (ZT) signed Ed25519 tokens.
   - Re-verify periodically (e.g., weekly) instead of continuous polling.

---

## 2. Dynamic Capability Engine

Replaces static boolean flags (`isAdmin`, `isDev`) with metered runtime capabilities:
- `canCreateProduct`
- `maxProductCount` (e.g., 1 for Free, 3 for Essential, 15 for Pro, 50 for Max)
- `dailyRequestQuota` (100 to 10,000)
- `hourlyRequestQuota` (15 to 500)
- `maxMachineSeats` per license
- `allowedIntervals` (monthly, annual, biennial, lifetime)

---

## 3. Database Sharding Architecture

- **Root Database:** `backend/database.sqlite` (Core accounts, global quotas, system audits).
- **Product Shard Database:** `backend/database/product_<slug>.sqlite` (Product-level tiers, serial registries, packages).
- **Reseller Shard Database:** `backend/database/shard_reseller_<slug>.sqlite` (Reseller client pools, allocated serials).
- **Storage Rule:** Enforce SQLite WAL mode and `busy_timeout=5000` on every connection to allow non-blocking concurrent reads and smooth writes.

---

## 4. Payment Gateway Invariant

- LaraLicensing does NOT store or process customer credit card numbers.
- License creation is initiated via authenticated, signed webhooks from developer payment gateways with strict idempotency keys.
