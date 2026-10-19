# Decision: Three-Actor Capability Model, Product Sharding, and Decoupled Payments

Date: 2026-10-19  
Status: Accepted  
Deciders: Platform Architect & Lead Developer  

---

## Context

The Bangla system architecture session outlined the vision for LaraLicensing:
1. It is not merely a single-product license key generator; it is a multi-tenant platform for software creators (developers, extension authors, desktop app makers, and resellers).
2. The platform operates on a three-tier actor model:
   - Platform Owner (Us)
   - Software Creators & Resellers (Direct Customers)
   - End Consumers (Users of the creators' applications)
3. Access rights must be granular, metered, and capability-driven rather than static roles.
4. Sharding must scale horizontally with per-product SQLite partitions to prevent locking issues.
5. LaraLicensing must completely avoid hosting credit card processing to eliminate PCI compliance liability.

---

## Decisions

### 1. Three-Actor Hierarchical Ecosystem
- We provide licensing SaaS infrastructure to software creators.
- Software creators define products, packages, deals, and seats for their own end-users.
- Downstream software uses lightweight Zero-Trust Ed25519 offline token verification with periodic re-validation (e.g., weekly).

### 2. Capability-Based Access Control Over Static Roles
- System replaces coarse boolean roles with a runtime capability matrix (`canCreateProduct`, `maxProductCount`, `dailyRequestQuota`, `hourlyRequestQuota`, `maxMachineSeats`, `canIssueOfflineTokens`).
- Platform tiers (Free, Essential, Pro, Max, Lifetime) assign specific capability limits to creator accounts.

### 3. Product-Level Database Sharding
- Platform metadata lives in `backend/database.sqlite`.
- Products maintain isolated SQLite shard databases (`backend/database/product_<slug>.sqlite`).
- Reseller wholesale pools are partitioned into `backend/database/shard_reseller_<slug>.sqlite`.
- Every SQLite connection enforces `PRAGMA journal_mode=WAL;` and `PRAGMA busy_timeout=5000;`.

### 4. Decoupled Payment Integration
- LaraLicensing does not accept, store, or process credit cards.
- Developers use their own payment gateways and invoke webhook endpoints with idempotency keys to trigger license generation.

### 5. AI-Ready Integration Instruction Generator
- The Developer Portal will export structured Markdown integration prompts that developers can directly paste into AI assistants (Gemini, Claude, Cursor) for automated zero-friction client SDK integration.

---

## Consequences

- Clear separation of liability and compliance.
- Massive concurrency scaling via isolated SQLite shards in WAL mode.
- Seamless developer onboarding via AI-ready prompts.
