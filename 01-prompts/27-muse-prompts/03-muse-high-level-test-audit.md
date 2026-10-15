# [V6] MUSE MASTER PROMPT: CHUNKED HIGH-LEVEL TEST AUDIT & VERIFICATION

```text
N = 300 (Total self-loop budget)
A = 2   (Mandatory concurrent autonomous subagents via invoke_subagent)
H = 2   (Operational hands per agent: dual-task capacity)
MODE = turbo
TARGET = Licensing Platform (Laravel Backend + TanStack/Vite Frontend + Split SQLite Sharding)
```

> [!IMPORTANT]
> **TOP-INSTRUCTION PRIORITY MANDATE & ANTI-HALLUCINATION RULES:**
> 1. Follow the exact chunked sequence below. Do not jump ahead or combine phases.
> 2. Zero hallucination: Never invent endpoints, schema fields, or CLI flags. All valid endpoints live in `backend/routes/api.php`, and all valid schemas live in `backend/app/Models` and `02-spec/`.
> 3. Prove all results: Every claim of success must be backed by exit code 0 and verbatim terminal output.
> 4. Do not rewrite published git history. Commits must be atomic.

---

## CHUNK 1: ENVIRONMENT & DAEMON PREFLIGHT

### Step 1.1: Verify Tooling & Dependencies
Run the following commands synchronously to ensure the runtime environment is operational:
```bash
php --version
node --version
pnpm --version
git status
```
- **Expected Outcome:** PHP 8.2+, Node 20+, working tree clean.

### Step 1.2: Check Background Service Daemons
Check whether the Laravel backend and Vite frontend development servers are active:
- Backend target: `http://127.0.0.1:8000/Api/Public/Health`
- Frontend target: `http://localhost:8080/`

If not responding, start them as daemons:
- Backend: `php artisan serve --port=8000` (run from `backend/`)
- Frontend: `pnpm dev` (run from workspace root)

---

## CHUNK 2: BACKEND TEST SUITE AUDIT (PARALLEL EXECUTION)

*Execute this chunk using Worker Subagent 1 or direct sequential runs.*

### Step 2.1: Portal Verification & Cryptographic Handshake Tests
Run the portal feature test suite:
```bash
cd backend
php artisan test tests/Feature/Portal
```
- **Target Coverage:**
  - `ApiContractTest.php`: ETag generation, error taxonomy mapping.
  - `ImpersonationTransactionTest.php`: Transactional session impersonation across root and shard.
  - `SelfUpdateManifestTest.php`: Stable vs Beta channel release manifests, yanked release filtering.
  - `SerialIssueTest.php`: HMAC signed request verification, serial generation.
  - `ShardIsolationTest.php`: Cross-tenant license probing rejection (404).
  - `VerifyFinalHandshakeTest.php`: Machine binding, device token consumption, activation handshake.
- **Pass Criteria:** 18/18 tests pass with zero failures.

### Step 2.2: Reseller Shard & License Lifecycle Tests
Run the reseller feature test suite:
```bash
cd backend
php artisan test tests/Feature/Reseller
```
- **Target Coverage:**
  - `LicenseIndexTest.php`: Multi-tenant scoped license listings.
  - `LicenseRenewTest.php`: Concurrency control via `If-Match` ETags, ledger entries, version increment.
  - `QuotaRequestSubmitTest.php`: Reseller quota requests, idempotent replays (200/201), status transitions.
- **Pass Criteria:** 28/28 tests pass with zero failures.

### Step 2.3: Core Backend Unit Tests
Run backend unit test suite:
```bash
cd backend
php artisan test tests/Unit
```
- **Target Coverage:**
  - `RuntimeConfigServiceTest.php`: Dynamic runtime configuration and environment guards.
  - `ShardResolverTest.php`: Dynamic SQLite database path binding and connection purging.
- **Pass Criteria:** 12/12 tests pass with zero failures.

---

## CHUNK 3: FRONTEND & ARCHITECTURE BOUNDARY AUDIT

*Execute this chunk using Worker Subagent 2 or direct sequential runs.*

### Step 3.1: Architecture & Parity Linters
Run the architectural boundary test suite:
```bash
npx vitest run tests/api-client-boundary.test.ts tests/command-registry.test.ts tests/lara-fetch-preview-guard.test.ts tests/preview-branch-parity.arch.test.ts
```
- **Target Coverage:**
  - `api-client-boundary.test.ts`: Enforces boundary between typed transport and Zod transport.
  - `command-registry.test.ts`: Verifies Command Palette hides deferred (status D) navigation routes.
  - `lara-fetch-preview-guard.test.ts`: Verifies preview-mode bypass interceptor and logging.
  - `preview-branch-parity.arch.test.ts`: Verifies preview bridge waivers match locked baseline.
- **Pass Criteria:** 4/4 files pass with zero failures.

### Step 3.2: Full Frontend Test Suite
Run the comprehensive Vitest test runner:
```bash
pnpm test
```
- **Pass Criteria:** 143 test files pass cleanly.

---

## CHUNK 4: LIVE END-TO-END APPLICATION PROBING

### Step 4.1: Probe Public Health Endpoint
Send an HTTP GET request to verify application liveness:
```powershell
Invoke-RestMethod -Uri "http://127.0.0.1:8000/Api/Public/Health" -Method Get
```
- **Expected Outcome:** HTTP 200 with JSON payload containing status details.

### Step 4.2: Probe Portal Handshake Security Boundary
Send a POST request without HMAC signature headers to verify authentication rejection:
```powershell
try {
    Invoke-RestMethod -Uri "http://127.0.0.1:8000/Api/Portal/Serials/Issue" -Method Post -Body '{}' -ContentType "application/json"
} catch {
    $_.Exception.Response.StatusCode.value__
}
```
- **Expected Outcome:** HTTP 401 Unauthorized (unauthenticated requests rejected at edge).

### Step 4.3: Probe Frontend Features Catalog Route
Verify that the Features Catalog route renders:
- Inspect `src/routes/_authenticated/admin.features.tsx`.
- Ensure it displays the standard feature catalog and entitlements without throwing runtime errors.

---

## CHUNK 5: AUDIT LOGGING & SCORECARD GENERATION

### Step 5.1: Verify Audit Trail & Documentation Integrity
Confirm that all findings and verification logs are recorded in:
- `docs/fix-log.md` (Section 5: Core Platform Handshake Audit & System Verification)
- `.ai-memory/memory/history/02-fixes-log.md`

### Step 5.2: Output Audit Scorecard
Output a Markdown table in the final turn displaying:
- Subsystem Name
- Test Count / Status
- Verified Capability
- Handshake / Shard Isolation Status
