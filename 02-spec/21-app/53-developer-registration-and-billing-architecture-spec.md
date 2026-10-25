# Developer Registration Flow & Global Billing Architecture Specification

**Document ID:** `02-spec/21-app/53-developer-registration-and-billing-architecture-spec.md`  
**Version:** 1.0.0  
**Status:** Normative Specification  
**Target Actor:** Developers, Software Creators, Agencies, and SaaS Vendors  

---

## 1. Executive Summary & Problem Definition

### 1.1 The Operational Bottleneck
Currently, the public marketing page (`/`) and pricing tiers (`/pricing`) present call-to-actions ("Start Free", "Get Pro Access", "Sign In") that either route directly to `/admin/login` or point to `/_authenticated/portal/home`, which forcibly redirects unauthenticated traffic to `/admin/login`. 

Furthermore, `POST /Api/Auth/Register` in `RegisterController.php` is strictly configured as a one-time bootstrap mechanism that only provisions the initial SuperAdmin account and subsequently returns `AuthRegistrationClosed` (HTTP 403) for all future sign-up requests. 

This creates a dead end: prospective software developers, Chrome extension creators, and WordPress plugin authors cannot create an account, pick a subscription plan, or access the developer portal without manual database intervention by an internal platform operator.

### 1.2 The Architectural Remedy
1. **Public Self-Service Developer Registration:** Deploy a dedicated, unauthenticated public registration workflow at `/auth/sign-up` (and `/register`), powered by `POST /api/v1/auth/developer-register`.
2. **Strict Operator Console Isolation:** Decouple `/admin/login` so it remains exclusively reserved for platform operators, while developers and end users authenticate through the public authentication gateway or sign-up.
3. **Plan-Bound Account Provisioning:** Allow prospective developers to select their intended platform tier (`Starter`, `Essential`, `Pro`, `Max`) during registration, initializing their account with the correct capability matrix, daily API request limits, and SQLite shard limits.
4. **First-Class Global Billing & Subscription Tab:** Elevate the platform subscription and plan management modal from being buried inside the "Products & Deals" sub-tab into a dedicated, global **"Billing & Subscription"** sidebar tab in the developer portal (`/portal/home`).

---

## 2. Three-Actor Identity & Authentication Boundary

```mermaid
flowchart TD
    Visitor["Public Visitor / Developer / Customer"] --> Choice{"Arriving Route"}
    
    Choice -- "Marketing / Pricing CTA" --> SignUp["/auth/sign-up (/register)\nDeveloper Sign-Up & Plan Selection"]
    Choice -- "Header 'Sign In'" --> DevSignIn["/auth/sign-in\nDeveloper & Customer Sign-In"]
    Choice -- "Direct URL: /admin/login" --> AdminLogin["/admin/login\nPlatform Operator Console ONLY"]
    
    SignUp -->|POST /api/v1/auth/developer-register| RegAPI["Developer Registration API"]
    RegAPI -->|Creates User (Role: Developer)| RootDB[("Root DB: Users, UserRoles, Capabilities")]
    RegAPI -->|Initializes Plan & Quota| PlanMgr["Platform Tier Provisioner"]
    PlanMgr -->|Returns Sanctum Bearer Token| DevSession["Authenticated Session"]
    
    DevSignIn -->|POST /Api/Auth/Login| AuthAPI["Universal Auth API"]
    AuthAPI --> DevSession
    
    DevSession -->|Redirect| Portal["/portal/home\nDeveloper Studio & Global Billing"]
    
    AdminLogin -->|POST /Api/Auth/Login (Requires SuperAdmin/Admin)| AdminConsole["/admin\nPlatform Operator Hub"]
```

### 2.1 Role & Entry Point Separation

| Actor | Public Entry / Registration | Sign-In Route | Post-Authentication Destination | Allowed Surfaces |
| :--- | :--- | :--- | :--- | :--- |
| **Platform Operator (`SuperAdmin` / `Admin`)** | Seeded / CLI bootstrap | `/admin/login` | `/admin` | Full platform governance, audit logs, reseller approvals |
| **Software Developer (`Developer` / `AppBuilder`)** | `/auth/sign-up`, `/register` | `/auth/sign-in` (or fallback) | `/portal/home` | Products & deals, SQLite shards, API keys, Global Billing |
| **Wholesale Reseller (`Reseller`)** | Seeded by Admin | `/auth/sign-in` | `/reseller/{tenantId}` | Branded vanity minting, quota requests, machine unbinding |
| **Downstream End User (`EndUser`)** | Purchase checkout webhook | `/portal/home` | `/portal/home` (Client tab) | License activation, bound machine slots, offline tokens |

---

## 3. Backend API Contract: Developer Registration

### 3.1 Endpoint Definition
- **Route:** `POST /api/v1/auth/developer-register` (with alias `POST /Api/Auth/DeveloperRegister`)
- **Middleware:** `rate.auth:register`, `throttle:10,1`
- **Authentication:** Unauthenticated (Public)
- **Response Format:** Universal Envelope (`Status`, `Attributes`, `Results`)

### 3.2 Request Schema

```json
{
  "Email": "creator@acmesoftware.io",
  "Password": "ValidPassword123#",
  "DisplayName": "Acme Software Labs",
  "CompanyName": "Acme Labs Inc.",
  "SelectedPlan": "PRO",
  "BillingInterval": "annual"
}
```

#### Validation Rules:
- `Email`: required, string, valid email format, max 254 chars, unique in `Users` table.
- `Password`: required, string, minimum 12 characters, maximum 128 characters.
- `DisplayName`: optional, string, max 100 characters.
- `CompanyName`: optional, string, max 150 characters.
- `SelectedPlan`: required, string, in: `["STARTER", "ESSENTIAL", "PRO", "MAX"]`.
- `BillingInterval`: optional, string, in: `["monthly", "annual", "biennial"]`, default: `"annual"`.

### 3.3 Response Schema (HTTP 201 Created)

```json
{
  "Status": {
    "IsSuccess": true,
    "Code": 201,
    "Message": "Created"
  },
  "Attributes": {
    "RequestId": "d98e721a-45c2-491a-96da-01e4a6431980",
    "RequestedAt": "2026-10-25T12:00:00Z"
  },
  "Results": [
    {
      "UserId": 42,
      "Email": "creator@acmesoftware.io",
      "DisplayName": "Acme Software Labs",
      "Role": "Developer",
      "Token": "1|sanctum_bearer_token_string...",
      "ExpiresAt": "2026-11-24T12:00:00Z",
      "Subscription": {
        "PlanCode": "PRO",
        "PlanName": "Pro Developer",
        "BillingInterval": "annual",
        "MaxProducts": 15,
        "DailyQuota": 1000,
        "HourlyQuota": 120,
        "MaxMachineSeats": 15,
        "Status": "active"
      }
    }
  ]
}
```

### 3.4 Conflict Handling (HTTP 409 Conflict)
If the email already exists in the `Users` table:
```json
{
  "Status": {
    "IsSuccess": false,
    "Code": 409,
    "Message": "An account with this email address already exists."
  },
  "Attributes": {
    "RequestId": "d98e721a-45c2-491a-96da-01e4a6431980",
    "Error": {
      "ErrorCode": "AuthUserConflict",
      "Details": "Email already registered"
    }
  },
  "Results": []
}
```

---

## 4. Platform Tier Entitlements & Capability Mapping

Upon registration, the developer account is assigned the `Developer` role and provisioned with tier-specific capabilities:

| Plan Code | Display Name | Allowed Products | Daily API Quota | Hourly Burst Limit | Max Machine Seats | Support Tier |
| :--- | :--- | :--- | :--- | :--- | :--- | :--- |
| `STARTER` | Starter / Free | 1 Product Shard | 100 req / day | 15 req / hr | 2 seats / license | Community |
| `ESSENTIAL` | Essential | 3 Product Shards | 200 req / day | 25 req / hr | 5 seats / license | Standard Email |
| `PRO` | Pro Developer | 15 Product Shards | 1,000 req / day | 120 req / hr | 15 seats / license | Priority (24h SLA) |
| `MAX` | Max Enterprise | 50 Product Shards | 5,000 req / day | 500 req / hr | Unlimited Floating | Dedicated VIP |

---

## 5. Global Billing & Subscription Architecture

### 5.1 Portal Navigation & Workspace Layout
The Developer Portal (`/portal/home`) workspace sidebar is updated to feature 6 dedicated navigation destinations:

1. **Overview:** High-level metrics, active key volume, 30-day activation velocity chart, top countries.
2. **Products & Deals:** Catalog management, sharded database inspector, AI pricing architect.
3. **Billing & Subscription:** (*NEW FIRST-CLASS GLOBAL TAB*)
   - Current subscription status banner with active plan badge.
   - Resource utilization meters (Products used vs allowed, daily quota usage, seat allowances).
   - "Change / Upgrade Plan" trigger button invoking the full `PlatformSubscriptionModal`.
   - Payment method & billing history summary (invoice receipts, renewal date).
4. **Reseller Wholesale Hub:** Vanity prefix registration, quota ledger, branded serial minting.
5. **Fraud Guard:** Impossible travel alerts, velocity anomalies, machine quarantine.
6. **Issued Licenses & Serials:** Downstream customer serial lookup, hardware release.

### 5.2 Global Access to Plan Upgrade
In addition to the dedicated Billing tab, a persistent "Plan Badge" (e.g. `[Pro Tier] Upgrade`) is rendered in the top header navbar of the portal. Clicking it opens the plan switcher modal from **any** screen without navigating away from current work.

---

## 6. Public Marketing & Pricing Alignment

### 6.1 Link Routing Specifications

| Element | Old Destination | New Canonical Destination | Description |
| :--- | :--- | :--- | :--- |
| **Header "Sign In"** | `/admin/login` | `/auth/sign-in` (or modal / auth entry) | Directs public users to developer/customer sign-in |
| **Header "Get Started Free"** | `/pricing` | `/register?plan=STARTER` | Pre-selects Starter plan on registration form |
| **Hero "Start Free Developer Trial"** | `/portal/home` (401 redirect) | `/register?plan=STARTER` | Smooth onboarding without dead ends |
| **Pricing "Start Free"** | `/portal/home` | `/register?plan=STARTER` | Directs to registration with Starter selected |
| **Pricing "Get Pro Access"** | `/portal/home` | `/register?plan=PRO` | Directs to registration with Pro selected |
| **Footer "Admin Console"** | `/admin/login` | `/admin/login` | Preserved for platform operators |

---

## 7. Acceptance Criteria

- **AC-DEV-001:** Public visitors can register a new developer account at `/register` or `/auth/sign-up` without encountering `AuthRegistrationClosed`.
- **AC-DEV-002:** Registration accepts an optional `plan` query parameter (`?plan=STARTER`, `?plan=PRO`) and pre-selects that tier on the sign-up form.
- **AC-DEV-003:** Successful registration automatically logs in the developer, stores the Sanctum bearer token, and redirects to `/portal/home`.
- **AC-DEV-004:** `/admin/login` is styled and labeled strictly as "Platform Operator Console" and does not serve as the catch-all redirect for public users.
- **AC-DEV-005:** `/portal/home` contains a first-class "Billing & Subscription" sidebar item and header plan badge.
- **AC-DEV-006:** Upgrading or selecting a plan in the modal updates the developer's capability quotas across all tabs.
- **AC-DEV-007:** Unauthenticated attempts to access `/portal/home` redirect to `/register` or `/auth/sign-in`, preserving the redirect query parameter.
