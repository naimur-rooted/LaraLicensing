# Plan 26: Developer Registration Flow, Operator Console Isolation & Global Billing Architecture

**Plan ID:** `26-developer-registration-flow-and-global-billing`  
**Status:** In Progress  
**Normative Specification:** `02-spec/21-app/53-developer-registration-and-billing-architecture-spec.md`  
**Parent Goals:**  
1. Decouple public sign-up from internal platform operator administration.
2. Enable self-service Developer onboarding with plan selection (`STARTER`, `ESSENTIAL`, `PRO`, `MAX`) at `/auth/sign-up` / `/register`.
3. Ensure `/admin/login` is strictly reserved for platform operators and NOT the catch-all redirect for unauthenticated public traffic.
4. Elevate the platform subscription & plan upgrade modal into a dedicated, first-class global **"Billing & Subscription"** tab in `/portal/home`.
5. Update all public marketing and pricing CTAs to route to `/register?plan={tier}` or `/auth/sign-in`.

---

## 1. Work Breakdown & Subagent Division

To maximize efficiency and maintain clean boundaries, the execution is split between two specialized autonomous subagents:

### Agent 1: Developer Backend & Registration Architect
- **Scope:**
  - Create `DeveloperRegisterRequest` FormRequest validator (`backend/app/Http/Requests/Auth/DeveloperRegisterRequest.php`).
  - Create `DeveloperRegisterController` (`backend/app/Http/Controllers/Auth/DeveloperRegisterController.php`) handling `POST /api/v1/auth/developer-register`.
  - Provision User with `Role = Developer` or `AppBuilder`, allocate tenant workspace, configure default plan capabilities in `Capabilities` or user profile, open session, and issue Sanctum token.
  - Wire up route in `backend/routes/api.php` under public `/api/v1/auth/developer-register` and alias `/Api/Auth/DeveloperRegister`.
  - Add feature test `backend/tests/Feature/Auth/DeveloperRegisterTest.php` verifying 201 creation, 422 validation, and 409 conflict.

### Agent 2: Developer Frontend, Marketing & Global Billing Architect
- **Scope:**
  - Enhance `/register` and `/auth/sign-up` in `src/routes/register.tsx` (and `src/routes/auth.sign-up.tsx` if alias) to be a full Developer Sign-Up page:
    - Plan selector matching `PLATFORM_PLANS` (`Starter`, `Essential`, `Pro`, `Max`).
    - Query param reader (`?plan=STARTER` or `?plan=PRO` auto-selects the plan).
    - Email, password (min 12 chars), Display Name, Company Name fields.
    - Submit calls `POST /api/v1/auth/developer-register`, stores session token, and redirects to `/portal/home`.
  - Update `src/routes/_authenticated.tsx` so unauthenticated visitors attempting to access `/portal/home` redirect to `/register` or `/auth/sign-in` (NOT `/admin/login`).
  - Ensure `/admin/login` explicitly displays "Platform Operator Console" title and branding, reserved for operators.
  - Update marketing buttons in `src/components/landing/LicenseSpringHeader.tsx`, `HeroSection.tsx`, `LicenseSpringHero.tsx`, `LicenseSpringFooter.tsx`, and `src/routes/pricing.tsx`:
    - "Get Started Free" / "Start Free" -> `/register?plan=STARTER`
    - "Get Pro Access" -> `/register?plan=PRO`
    - "Sign In" -> `/auth/sign-in` or login modal.
  - Create `src/components/developer/DeveloperBillingTab.tsx`:
    - First-class view for the Billing & Subscription tab.
    - Current Plan card with Active badge, renewal date, and billing interval.
    - Quota utilization meters (Product shards used vs allowed, daily request allowance, bound machine seats).
    - "Change Plan / Upgrade" button that opens `PlatformSubscriptionModal`.
  - Update `src/routes/_authenticated/portal.home.tsx`:
    - Add `Billing & Subscription` as a 6th item in `navItems` with `CreditCard` icon.
    - Add persistent Plan Badge (e.g. `[Pro Tier] Upgrade`) in the top navbar that opens the `PlatformSubscriptionModal` from anywhere.

---

## 2. Invariants & Coding Guidelines
- **No Git History Rewrites:** No rebase, amend, or force-push (Lovable rule).
- **Function Body Limit:** Max 15 lines per function.
- **Component File Limit:** Max 100 lines per React component (extract sub-components as needed).
- **Positive Booleans:** Always use `is`/`has` prefixes (e.g. `isSubmitting`, `hasLoaded`, never `notLoggedIn`).
- **Response Envelopes:** `{ Status: { IsSuccess, Code, Message }, Attributes: {}, Results: [] }`.
- **Zero Swallowed Errors:** Catch, log, rethrow or wrap in typed `LaraApiError`.
