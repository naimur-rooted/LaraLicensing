# Plan 28: Production Sanitization, Admin Concealment & Multi-Tenant Isolation Fix

## User Request (Verbatim)
01-prompts/14-execute/08-portal-sanitization-and-multi-tenant-isolation-fix.md
read and start implementation

## Parameters
- Budget: N = 100 steps
- Agents: A = 2
- Hands: H = 2

## Traceable Subtasks
- **Task-01: Conceal Internal Admin Console from Public Surface**
  - Target: `src/components/landing/HeroSection.tsx`, `src/components/landing/LicenseSpringFooter.tsx`, `src/components/landing/CtaFooter.tsx`, `src/routes/login.tsx`, `src/routes/reseller-info.tsx`
  - Scope: Remove operator console links so unauthenticated visitors cannot discover internal admin consoles.
- **Task-02: Purge Mock Reseller Hub from Developer Portal**
  - Target: `src/routes/_authenticated/portal.home.tsx`, `src/components/portal/PortalSidebar.tsx`, `src/components/portal/PortalTabContent.tsx`
  - Scope: Ensure software creators see strictly developer tools (Overview, Products & Deals, Billing, Fraud Guard, Issued Licenses).
- **Task-03: Remediate Multi-Tenant Data Leak in Backend API**
  - Target: `backend/app/Http/Controllers/Developer/ProductController.php`, `backend/app/Services/DeveloperAnalyticsService.php`
  - Scope: Scope product queries and overview metrics strictly to the authenticated `$userId`. Fresh users see clean zero-state (`0/50`).
- **Task-04: Transmit Bearer Authorization in Frontend Dashboard Fetch**
  - Target: `src/components/developer/DeveloperOverviewDashboard.tsx`, `src/components/developer/overview/useDeveloperOverview.ts`
  - Scope: Ensure `Authorization: Bearer <token>` is attached to all developer overview API requests.
- **Task-05: Automated Verification & Regression Gate**
  - Target: Vitest test suite and Laravel Feature tests.
  - Scope: Confirm 100% test pass and compile verification before atomic git commit.


## Status: COMPLETED
- All 5 subtasks verified and passing.
- Vitest: 145/145 files (950/950 tests) passing.
- PHPUnit: DeveloperPortalTest 9/9 passing.
- Frontend build: Clean build with zero errors.
- Zero em-dashes and zero en-dashes verified.
