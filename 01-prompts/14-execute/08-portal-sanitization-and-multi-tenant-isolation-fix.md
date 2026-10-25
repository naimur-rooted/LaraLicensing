# [V6] Production Sanitization, Admin Concealment & Multi-Tenant Isolation Fix

```text
N = 100 (Total self-loop steps budget)
A = 2   (Number of autonomous subagents)
H = 2   (Parallel operations per agent)
```

> [!IMPORTANT]
> Prompt Version: 6.1.0
> Priority: CRITICAL PRODUCTION HYGIENE & SECURITY
>
> **Top-Instruction Priority Mandate (Preamble Precedence):**
> Directives, constraints, checklists, and specifications given in this document are HIGHEST PRIORITY and MUST BE FOLLOWED as strictly NON-NEGOTIABLE. They supersede and override any conflicting default conventions.

[/goal](slashCommand:goal) Autonomously execute full public surface sanitization, complete concealment of internal admin operator consoles from unauthenticated visitors, purge of mock reseller prototypes from the developer portal, and surgical remediation of backend multi-tenant data leaks. Finish with 100% passing tests and a single atomic git commit.

---

### 📋 Actionable Deliverables & Scope

#### Task 1: Conceal Internal Admin Console from Public Surface
- **Objective:** Eliminate all visible links, buttons, and references to the Platform Operator / Admin Console across public-facing landing and auth pages.
- **Target Files:**
  1. `src/components/landing/HeroSection.tsx`:
     - Remove the secondary `<Link to="/admin/login">Operator Console</Link>` button next to "Start Free Developer Trial".
     - Keep only the primary developer CTA and runtime status indicator.
  2. `src/components/landing/LicenseSpringFooter.tsx`:
     - Remove `<li><Link to="/admin/login">Admin Console</Link></li>` from the public footer navigation.
  3. `src/components/landing/CtaFooter.tsx`:
     - Remove or decouple the `{ Id: "AdminConsole", Label: "Admin console", To: "/admin/login" }` entry from public visitor view.
  4. `src/routes/login.tsx`:
     - Remove the footer link pointing to `/admin/login` on the developer login screen so external users are not directed to internal consoles.

#### Task 2: Purge Mock Reseller Hub from Developer Portal
- **Objective:** Prevent software creators from seeing redundant B2B reseller prototypes inside their tenant workspace.
- **Target File:**
  - `src/routes/_authenticated/portal.home.tsx`:
    - Remove `{ id: 'resellers', label: 'Reseller Wholesale Hub', icon: Users }` from the `navItems` array.
    - Remove the `<ResellerWholesaleStudio />` conditional render branch from the tab switcher.
    - Keep only genuine developer modules: Overview, Products, Deals, Integration (SDK), and Analytics.

#### Task 3: Remediate Multi-Tenant Data Leak in Backend API
- **Objective:** Guarantee that freshly registered developers see strictly 0 products (`0/50`) and cannot view or query assets belonging to other tenants.
- **Target Files:**
  1. `backend/app/Http/Controllers/Developer/ProductController.php`:
     - Locate line 26: `$this->productService->listProducts(1);`.
     - Replace hardcoded `1` with the authenticated developer ID:
       `$userId = (int) ($request->user()?->UserId ?? auth()->id() ?? auth('sanctum')->id());`
     - Pass `$userId` to `$this->productService->listProducts($userId)`.
  2. `backend/app/Services/DeveloperAnalyticsService.php`:
     - In `fetchProductsForAnalytics(?int $userId, ?string $filterSlug)`:
       Ensure `Product::where('isActive', true)` strictly enforces `where('userId', $userId)` whenever `$userId` is provided.
     - When `$userId` is null or invalid, return `buildZeroStateMetrics()` so unauthenticated or fresh users never inherit global database counts.

#### Task 4: Transmit Bearer Authorization in Frontend Dashboard Fetch
- **Objective:** Ensure the overview dashboard passes valid bearer tokens so the backend identifies the tenant.
- **Target File:**
  - `src/components/developer/DeveloperOverviewDashboard.tsx`:
    - Locate the `fetch('/api/developer/overview')` call (around line 82).
    - Attach the authorization header:
      ```ts
      const token = sessionStorage.getItem('LicensingPortal.AccessToken') ?? '';
      const headers: Record<string, string> = {
        'Accept': 'application/json',
      };
      if (token) {
        headers['Authorization'] = `Bearer ${token}`;
      }
      ```
    - Pass `headers` into `fetch(url, { headers })`.

#### Task 5: Automated Verification & Regression Gate
- **Objective:** Validate that all unit tests, integration suites, and TypeScript compiles pass without any regressions.
- **Commands:**
  - `npx.cmd vitest run` (Must report all tests passing, 0 failed).
  - `npx.cmd vite build` (Must build without TypeScript or bundling errors).

---

### 🛡️ Non-Negotiable Invariants
1. Punctuation Hygiene: Absolutely zero em-dashes or en-dashes anywhere in source files, docstrings, or commit messages. Use hyphens, commas, colons, or parentheses.
2. Backward Compatibility: Do not break existing admin access for internal operators visiting `/admin/login` directly with valid admin credentials.
3. Atomic Commit: Accumulate all changes and commit in a single atomic git commit:
   `git commit -m "fix(portal): conceal admin console, purge reseller tab, and isolate multi-tenant queries"`
