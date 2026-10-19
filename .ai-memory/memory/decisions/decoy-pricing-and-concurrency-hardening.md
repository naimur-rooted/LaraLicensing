# Decision: VPN-Style Decoy Pricing, Rate Limiting & Shard WAL Hardening

- **Date:** 2026-10-19
- **Status:** Approved & Implemented

---

## Context
Commercial software monetization requires a high-conversion retail pricing structure (anchored by a higher-priced 1-month tier and incentivizing multi-year adoption with free bonus months), while protecting public licensing endpoints against credential stuffing and brute-force key enumeration. Additionally, concurrent reads and writes across split SQLite shards required SQLite concurrency hardening.

---

## Decisions

1. **Decoy Pricing Matrix in Root DB:**
   - Table `PricingPlans` in Root DB (`backend/database.sqlite`).
   - 3 Tiers:
     - `PLAN_1M`: \$12.99/mo anchor (1 slot)
     - `PLAN_1Y`: \$4.99/mo (\$59.88/yr, 3 slots)
     - `PLAN_2Y`: \$2.39/mo (\$64.50 biennial + 3 months free, 10 seats, 82% OFF best value)
2. **SQLite WAL & Busy Timeout Invariant:**
   - Instead of building a redundant parallel switcher, [`App\Db\ShardResolver.php`](file:///c:/Users/naimu/Desktop/Licensing/backend/app/Db/ShardResolver.php) injects `PRAGMA journal_mode = WAL;` and `PRAGMA busy_timeout = 5000;` on dynamic SQLite shard connections.
3. **Dual-Layer Rate Limiting:**
   - Public serial lookups are throttled via [`App\Http\Middleware\ThrottleLicenseLookups.php`](file:///c:/Users/naimu/Desktop/Licensing/backend/app/Http/Middleware/ThrottleLicenseLookups.php) to 60 requests per minute per IP with HTTP 429 and `Retry-After` headers.
   - Client simulator heartbeats gracefully back off upon receiving HTTP 429 instead of reporting a connection failure.
4. **Offline Token Issuance:**
   - Air-gapped offline activation token signing is handled by [`App\Services\JwtTokenService.php`](file:///c:/Users/naimu/Desktop/Licensing/backend/app/Services/JwtTokenService.php) and verified locally in [`scripts/demo_client_app.py`](file:///c:/Users/naimu/Desktop/Licensing/scripts/demo_client_app.py) without external pip dependencies.
