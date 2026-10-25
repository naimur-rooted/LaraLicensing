---
name: portal-sanitization-and-multi-tenant-isolation-fix
description: Autonomously conceal internal admin operator consoles from unauthenticated public surfaces, purge redundant mock reseller prototypes from the developer portal, and isolate backend multi-tenant queries so new users see strictly 0 products.
---

# Portal Sanitization & Multi-Tenant Isolation Fix

## Overview
Autonomously sanitize public-facing pages to conceal internal admin console links from unauthorized visitors, purge redundant mock reseller prototypes from the software creator portal, enforce strict tenant-scoping across product and analytics controllers, and transmit valid bearer tokens from the overview dashboard.

## Scope of Remediation
1. **Conceal Internal Admin Console:**
   - Remove operator console links from `HeroSection.tsx`, `LicenseSpringFooter.tsx`, `CtaFooter.tsx`, and `login.tsx`.
2. **Purge Mock Reseller Hub:**
   - Verify developer navigation and tab switcher in `portal.home.tsx` contain only genuine developer modules (Overview, Products, Deals, Integration, Analytics).
3. **Backend Multi-Tenant Scoping:**
   - Scope `ProductController.php` and `DeveloperAnalyticsService.php` to `$userId`.
   - Ensure zero-state metrics (`0/50` products and activations) for fresh accounts.
4. **Bearer Token Authorization:**
   - Verify `useDeveloperOverview.ts` transmits `Authorization: Bearer <token>` in dashboard API calls.
5. **Verification Gate:**
   - Run `npx.cmd vitest run` and `php artisan test tests/Feature/DeveloperPortalTest.php`.
   - Execute single atomic commit without em or en dashes.
