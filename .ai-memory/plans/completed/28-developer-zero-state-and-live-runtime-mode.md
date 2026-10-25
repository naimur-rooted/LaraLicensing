# Plan 28: Fix Developer Zero-State, Purge Hardcoded Mock Metrics, and Wire Live Runtime Mode

**Status:** COMPLETED  
**Created:** 2026-10-26  
**Completed:** 2026-10-26  
**Normative Specifications:**
- `02-spec/21-app/16-ui-surfaces.md`
- `02-spec/21-app/53-developer-registration-and-billing-architecture-spec.md`
- `02-spec/28-runtime-modes/00-overview.md`
- `02-spec/28-runtime-modes/02-mode-selection-precedence.md`

---

## 1. Executive Summary
Remediated the issue where a freshly registered developer logs into the portal and sees hardcoded seeded metrics (14 activations, 18 licenses, 4 users, "Sample Desktop Suite") instead of a true zero-state:
1. **Purged Hardcoded Dummy Mock in `useDeveloperOverview.ts` & `overview-types.ts`:**
   - Replaced `PREVIEW_OVERVIEW_DATA` with `ZERO_STATE_OVERVIEW`. Any developer account without products strictly resolves to `ZERO_STATE_OVERVIEW` (0 activations, 0 licenses, 0 products, empty charts, empty countries) and renders the `OverviewEmptyState` CTA prompting product creation.
2. **Elevated `RuntimeModeFlipButton` to `portal.home.tsx`, `register.tsx`, and `login.tsx`:**
   - Embedded `RuntimeModeFlipButton` so developers and testers can clearly see whether they are in `Preview (Mock)` or `Live (API)` mode and flip between them with one click.
3. **Backend Multi-Tenant Scoping Safeguards:**
   - In `DeveloperAnalyticsService.php`: When `$userId === null` or `$userId <= 0`, returns `buildZeroStateMetrics()` so unauthenticated callers or new accounts NEVER see another tenant's metrics.
   - In `ProductController.php`: Updated `resolveUserId` to return `0` when unauthenticated rather than falling back to `1`. In `index()`, `$userId > 0 ? $this->productService->listProducts($userId) : collect()`.
4. **Sanitized Developer APIs & Dialogs:**
   - Replaced bare unauthenticated `fetch("http://127.0.0.1:8000/...")` across `DeveloperProductsDashboard.tsx`, `CreateProductDialog.tsx`, `AiPricingArchitectDialog.tsx`, `DeveloperSupportChatbot.tsx`, and `lara-customer-request.ts` with dynamic `getApiBaseUrl()` and authenticated headers carrying `Authorization: Bearer <token>` and `Accept: application/json`.
5. **Verification & Quality Protocol:**
   - Backend PHPUnit Feature tests passed (DeveloperPortalTest 8/8, Auth 31/31).
   - Frontend Vitest suite passed: 144 test files, 945 tests (100% pass).
   - Production bundle compiled cleanly via `npx vite build`.
