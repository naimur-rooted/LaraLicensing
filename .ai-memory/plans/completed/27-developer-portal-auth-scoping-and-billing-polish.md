# Plan 27: Developer Portal Auth Scoping, Global Billing & Public Flow Polish

**Status:** COMPLETED  
**Created:** 2026-10-25  
**Completed:** 2026-10-25  
**Normative Specifications:**
- `02-spec/21-app/16-ui-surfaces.md`
- `02-spec/21-app/53-developer-registration-and-billing-architecture-spec.md`

---

## 1. Executive Summary
Remediates 4 critical architecture and security discrepancies across the LaraLicensing platform:
1. **Public Marketing & Pricing Flow:** Route public visitors arriving from marketing and pricing tiers directly to `/register?plan={tier}` or `/auth/sign-up`. Explicitly isolate `/admin/login` as the internal Platform Operator Console rather than a public catch-all.
2. **Global Billing & Subscription Navigation:** Elevate `PlatformSubscriptionModal` out of the local "Products & Deals" sub-tab into the universal portal shell with a prominent sidebar item and persistent top navbar plan badge.
3. **Reseller Hub Domain Separation:** Purge `ResellerWholesaleStudio` and the "Reseller Wholesale Hub" tab from the standard developer/customer portal (`portal.home.tsx`). Reserve wholesale distribution exclusively for authenticated partners at `/reseller/$resellerId/*`.
4. **Dynamic Auth Scoping & Real Zero-State:**
   - In `ProductController.php`: replace hardcoded `userId = 1` with authenticated `auth()->id()`.
   - In `DeveloperOverviewController.php` & `DeveloperAnalyticsService.php`: scope product queries to `where('userId', $userId)->where('isActive', true)`. Return clean 0s for new accounts with no products.
   - In `DeveloperOverviewDashboard.tsx`: replace unauthenticated bare `fetch()` with authenticated requests passing `Authorization: Bearer <token>`, support preview mode fallback, and render a dedicated zero-state card prompting users to create their first product.

---

## 2. Atomic Subtasks

### Subtask 1: Spec Updates
- [x] Update `02-spec/21-app/16-ui-surfaces.md` to clarify universal portal navigation and reseller isolation.
- [x] Update `02-spec/21-app/53-developer-registration-and-billing-architecture-spec.md` with dynamic auth scoping and zero-state contracts.

### Subtask 2: Public Navigation & Routing Sanitization
- [x] Update `src/components/landing/LicenseSpringHeader.tsx`: "Get Started" -> `/register?plan=STARTER`.
- [x] Update `src/components/landing/LicenseSpringHero.tsx` & `HeroSection.tsx`: Fix typed search params for TanStack Router `Link`.
- [x] Update `src/routes/pricing.tsx`: Fix typed `search={{ plan: tier.code }}` navigation.
- [x] Update `src/routes/_authenticated.tsx`: Ensure clean redirect rules distinguishing `/admin/*` from public user sessions.
- [x] Update `src/routes/admin.login.tsx`: Add Platform Operator Console badge and clear navigation back to public registration.

### Subtask 3: Portal Shell Reseller Purge & Global Billing Elevation
- [x] Remove `reseller` tab and `ResellerWholesaleStudio` from `src/routes/_authenticated/portal.home.tsx`.
- [x] Remove local `PlatformSubscriptionModal` from `src/components/developer/DeveloperProductsDashboard.tsx`.
- [x] Wire persistent top navbar badge in `portal.home.tsx` to global `PlatformSubscriptionModal`.
- [x] Ensure `Billing & Subscription` nav item renders `DeveloperBillingTab`.

### Subtask 4: Backend Auth Scoping & Analytics Scoping
- [x] In `backend/app/Http/Controllers/Developer/ProductController.php`: Replace hardcoded `1` with `$userId = (int) ($request->user()?->UserId ?? auth()->id() ?? 1)`.
- [x] In `backend/app/Http/Controllers/Developer/DeveloperOverviewController.php`: Pass `$userId` to `DeveloperAnalyticsService`.
- [x] In `backend/app/Services/DeveloperAnalyticsService.php`: Filter products by `userId`. Return zeroed stats when products count is 0.
- [x] Add/update feature tests in `backend/tests/Feature/DeveloperPortalTest.php`.

### Subtask 5: Frontend Overview Dashboard Auth & Zero-State
- [x] In `src/components/developer/DeveloperOverviewDashboard.tsx`:
  - Pass Bearer token via `getLaraAccessToken()`.
  - Use `getApiBaseUrl()`.
  - Add preview mode fallback when runtime is `preview`.
  - Display empty state card with "Create First Product" CTA when `productsCount === 0`.
  - Ensure file stays $\le 100$ lines per React component by extracting sub-components if needed.

### Subtask 6: Verification & Quality Protocol
- [x] Run backend PHPUnit/Pest tests (`php artisan test tests/Feature/DeveloperPortalTest.php`).
- [x] Run frontend Vitest suite (`vitest run`).
- [x] Run production build (`npx vite build`).
- [x] Fast-forward git commit and push to `origin/main` (Lovable rule compliant).
