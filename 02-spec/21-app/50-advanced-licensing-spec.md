# Advanced Features & Licensing Architecture Specification

> **Phase:** 21 App Domain (Advanced Extensions)  
> **Sequence:** 50  
> **Status:** Authoritative  
> **Path:** `02-spec/21-app/50-advanced-licensing-spec.md`  

---

## 1. Executive Summary

This specification defines advanced commercial, security, and cryptographic enhancements for the `LaraLicensingV1` platform:
1. **VPN-Style Decoy Pricing Matrix:** A 3-tier high-conversion model optimizing for 2-year subscriptions.
2. **Split SQLite Hardening & M2 Concurrency:** WAL journal mode and busy timeout configuration injected via `ShardResolver`.
3. **Dual-Layer Throttling Pipeline:** Leaky-bucket IP limits on public serial lookups and heartbeat throttling.
4. **Risk-Based Assessment (RBA):** Geo-velocity calculation and hardware signature drift detection.
5. **Cryptographic Offline Activation Tokens:** Asymmetric Ed25519 or HMAC-SHA256 signature tokens for air-gapped machine activations.

---

## 2. VPN-Style Decoy Pricing Matrix

### Tier Specification

| Attribute | 1. Monthly (Anchor) | 2. Annual | 3. Biennial Deal (Decoy Target) |
| :--- | :--- | :--- | :--- |
| **Plan Code** | `PLAN_1M` | `PLAN_1Y` | `PLAN_2Y` |
| **Display Name** | 1 Month Plan | 1 Year Plan | 2 Years + 3 Months Free |
| **Monthly Equivalent** | \$12.99 / mo | \$4.99 / mo | \$2.39 / mo |
| **Billed Amount** | \$12.99 billed monthly | \$59.88 billed yearly | \$64.50 billed for 27 months |
| **Savings Badge** | None (Anchor) | Save 61% | Save 82% (Best Value) |
| **Machine Slots** | 1 Seat | 3 Seats | 10 Floating Seats |
| **Is Best Value** | `false` | `false` | `true` |
| **Is Active** | `true` | `true` | `true` |

### Database Representation (Root DB)
- **Table Name:** `PricingPlans` (PascalCase)
- **Schema:**
  - `id`: integer primary key auto-increment
  - `planCode`: string(32) unique (e.g. `PLAN_1M`)
  - `planName`: string(100)
  - `billingInterval`: string(20) (`monthly`, `yearly`, `biennial`)
  - `retailPriceCents`: integer (cents)
  - `discountedPriceCents`: integer (cents)
  - `allowedMachineSlots`: integer
  - `isBestValue`: boolean
  - `isActive`: boolean
  - `createdAt`: timestamp
  - `updatedAt`: timestamp

---

## 3. Split-Database Concurrency & M2 Isolation Strategy

1. **Root DB vs. Shard DB Boundary:**
   - `PricingPlans` lives strictly in Root DB (`backend/database.sqlite`).
   - `Licenses`, `MachineBindings`, and `VerifyKeys` live strictly in Reseller Shard DBs (`backend/database/shard_reseller_<slug>.sqlite`).
2. **SQLite WAL & Busy Timeout Hardening:**
   - Every connection instantiated via `App\Db\ShardResolver` must execute:
     ```sql
     PRAGMA journal_mode = WAL;
     PRAGMA busy_timeout = 5000;
     PRAGMA synchronous = NORMAL;
     ```
   - This ensures multiple concurrent reads and writes between the web portal and client heartbeats do not result in `database is locked` errors.

---

## 4. Security Pipeline Contract

### 4.1 Dual-Layer Throttling
1. **Serial Lookup Throttling (`/api/Serials/{clean}`):**
   - Max 5 requests per minute per IP address.
   - Headers returned: `X-RateLimit-Limit`, `X-RateLimit-Remaining`, `Retry-After`.
   - On limit exhaustion: HTTP 429 with `ERR_RATE_LIMIT_EXCEEDED`.
2. **Heartbeat Throttling:**
   - Normal heartbeat interval is 1200ms.
   - Max 60 requests per minute per `MachineFingerprint`.

### 4.2 Risk-Based Assessment (RBA)
`App\Services\RiskAssessmentService` calculates a risk score (0 to 100):
- **Impossible Travel / Geo-Velocity:** If consecutive requests for the same serial occur from disparate IP ranges in a window physically impossible for travel (> 800 km/h), score adds +50 points.
- **HWID Signature Drift:** If 3 out of 4 hardware components match but 1 differs (e.g. replaced NIC or OS update), score adds +25 points (warning state).
- **Thresholds:**
  - `0 - 39`: Low risk, allow binding.
  - `40 - 74`: Medium risk, require re-authentication.
  - `75 - 100`: High risk, auto-suspend serial and emit security audit log.

### 4.3 Cryptographic Offline Activation Tokens
When an air-gapped machine requests offline activation, the server signs a base64url token:
- **Claims:**
  ```json
  {
    "sub": "LARA-AAAA-0001",
    "hwid": "9A3F1B008C21EF44",
    "tier": "PLAN_2Y",
    "seats": 10,
    "iat": 1776500000,
    "exp": 1808000000
  }
  ```
- **Signature:** Cryptographic signature generated using the shard's `VerifyKey` private key.
- **Client Verification:** Client simulator reads token, verifies signature against the server's public key, and activates offline without connecting to the network.
