# Fixes Log — 2026-10-15

## 1. Reseller License Partitioning & Shard Alignment
- **Auto-Healing Migration:** In `src/lib/preview-fixtures/licenses.ts`, `loadAllLicenses` detects user-minted unassigned licenses (`LICNEW...` with `ResellerId === null`) and auto-binds them to `01H000000000000000RSLLR2` (Beta Reseller LLC).
- **Direct Issue Action:** Added an "Issue license" button directly inside `/reseller/$resellerId/licenses` forwarding `resellerId` to `admin.licenses.new.tsx`.
- **Form State Pre-selection:** `license-create-wizard.tsx` consumes `search.resellerId` so licenses created from reseller contexts are correctly assigned to the target reseller shard.
- **Cache Invalidation:** Added `invalidateQueries({ queryKey: ["LaraApi"] })` on license creation to avoid stale React Query data across tables.
- **Assigned Reseller Dropdown:** Added reseller selector in `license-detail-actions.tsx` and wired `lara-license.ts` patch routines to enable updating `ResellerId`.

## 2. Backend OpenAPI & Controller Architecture
- **Base Controller:** Created `backend/app/Http/Controllers/Controller.php` extending Laravel base controller with `AuthorizesRequests` and `ValidatesRequests`.
- **L5-Swagger Docs:** Fixed OA docblock tags and parameters across 18 backend controllers and verified via `php artisan l5-swagger:generate`.
- **Middleware & Services:** Hardened `SignedRequestMiddleware`, `RequestIdMiddleware`, `IdempotencyKeyMiddleware`, and services (`QuotaService`, `RuntimeConfigService`).

## 3. Test Suites
- All 9 unit tests across `tests/lara-license-preview-bridge.test.ts` and `tests/lara-reseller-license-preview-bridge.test.ts` pass cleanly (100% green).

## 4. Platform Audit, Handshake & Test Suite Remediation
- **Multi-Tenant Shard Resolver:** Dynamic SQLite path expansion and connection purging in `backend/app/Db/ShardResolver.php`.
- **Exception & Envelope Parity:** `LaraException.php` implements Symfony HTTP exception interfaces; `config/lara.php` tax mappings expanded; `ApiEnvelope.php` provides both top-level and attribute-level error envelopes.
- **Audit Logging Keys:** Corrected `QuotaRequestId` assignment in `QuotaRequestController.php` audit log writes.
- **Preconditions:** Updated `EtagMiddleware.php` to pass unauthenticated requests to route authentication guards before precondition validation.
- **Test Results:** 18/18 Portal tests, 28/28 Reseller tests, 12/12 Unit tests, and frontend test suite passing.
