# LaraLicensing High-Level Test Audit: Final Report (DRAFT)

Branch tested: `muse` at 7404ea8. `main` untouched at 413986c. Date: 2026-10-30, overnight run.
Status: DRAFT. Frontend vitest at 7404ea8 is now included. Live Postgres-dependent tests are still blocked by a missing database, and adversarial attack cases are still not run (see below). Update those two sections before delivery to the user, or deliver with those gaps stated plainly as not tested.

## Verdict (draft, do not soften)

NOT DEPLOYABLE to the real market as-is, without fixing or explicitly scoping the red flags below.

This is not a claim that everything is broken. The core licence, developer portal, mint, gateway webhook, quota submit/approve, pricing versioning, fraud manifest, and security unit/API tests pass. The blockers are: a whole user-facing flow that is simulated, a developer plan that is not stored or enforced, dashboard numbers that come from different stores, a missing Postgres test database that masks 24 tests, new gate failures from the latest remote commit, and no live proof for price propagation, gateway delivery to the end customer, or dashboard refresh.

## What was tested, with numbers

- Full backend suite at 7404ea8, 50.83s: 558 passed, 27 failed, 13 skipped, 10,819 assertions. Log: `testing/audit/full-backend-7404ea8.log`, JUnit: `testing/audit/full-backend-7404ea8-junit.xml`.
  - 24 failures are Postgres connection refused to 127.0.0.1:5432. Cluster 16/main was down at run time; after starting it, only postgres/template databases exist, no lara or lara_root. These are environment failures, not proof of code logic failure, and they mask reseller shard tests (mint 500 and revoke 404 in that run both trace to the same Postgres PDOException).
  - 1 FunctionBodyLineCap, now 8 violations: 3 pre-existing plus 5 new from 7404ea8 (ResellerQuotaController index 27, ledger 30, quotaRequests 29; MachineBindingController index 38; RateLimitAuthMiddleware handle 22).
  - 1 EndpointInventory, new at 7404ea8: ResellerQuotaController and SandboxCheckoutController routes have no test file referencing them.
  - 1 TraceRedactor, pre-existing.
- Targeted backend at 7404ea8, 90 passed / 0 failed / 590 assertions: TamperManifest, FraudPhase2, FraudPhase3, DeveloperPortal 29, EndUserVerification 11, PaymentWebhook 7, PricingPlanCrud 6, PricingPlanPriceVersion 5, Reseller QuotaRequestSubmit 11, Admin QuotaRequests 10.
- Security targeted at 7404ea8, 28 passed / 0 failed / 145 assertions: rate limiting, HMAC, idempotency, RBAC, role gate, impersonation, captcha envelope, login bad/happy paths.
- Developer extra targeted at 7404ea8, 19 passed / 0 failed / 83 assertions: AdvancedEcosystem, AdvancedLicensing, DeveloperLicenseMint 8, LicenseIssuePreflight, LicenseLedgerRead.
- Frontend vitest at 7404ea8, 201.18s: 144 test files passed, 1 failed, 950 tests passed, 1 failed, 1 obsolete snapshot. The failure is `tests/laravel-blade-root-csp.test.ts` ("mints the nonce before Inertia renders and skips JSON API responses"), expecting `$request->is('Api/*', 'App/*')` in backend CSP code changed by 7404ea8. Previous run at 984b576 was 145 files / 951 tests passed, 0 failed, so this is a new failure at 7404ea8, not a pre-existing one.

## Admin confirmation question: panel, email, or both?

Answer, with research: BOTH, with a strict split. Approve in the admin panel only, with Sanctum auth, role, anti-self-approval, and audit. Email is notification only (admin on new application, applicant on receipt/approval/denial with reason), linking to the panel or a status page, never executing the decision. Do not build approve-by-email magic links: they are not phishing-resistant, mailbox compromise or forwarding would grant reseller creation, scanners can click them, and they would have to reimplement RBAC and audit that the panel already has. Suggested SLA for user decision: 2 to 3 business day review, reminder at 48 hours, stale closure with reason after 30 days. Proportionate checks: domain/website match, prefix uniqueness, duplicate flagging, manual review before any quota. Full document KYB is a later decision.

## Red flags (full detail in testing/audit/red-flags.md)

- RF-01 CRITICAL: Reseller apply from the landing page is simulated. Modal waits 800ms, shows "Application Received!" and "Confirmation Sent To", with no API call, no record, no admin queue, no email. No backend reseller-application endpoint exists. Any "apply then admin approves" test fails by design today.
- RF-02: Quota approve writes shard Quotas/LicenseLedger; reseller dashboard gauge and shared quota endpoints read Root ResellerQuotas/ResellerQuotaLedger. No approve-path write to Root was found. Dashboard agreement after approve is unproven.
- RF-03: Developer plan is collected at sign-up, echoed in the response, not stored on the user, not linked to a PricingPlan, and not enforced. No product-limit field exists. Plan upgrade/change has no persistence to test.
- RF-04: Gateway keys missing from Serials (webhookIssue writes ProductLicenses only; PaymentWebhookService fresh-shard skip), and no key delivery email in either gateway path. Key is returned in the webhook response only.
- RF-05: Two reseller tenant-binding paths (strict vs loose preview with header/query override and default id 1 / slug `alim`) can disagree, with silent fallbacks. Candidate cause for generic errors, not proven causally.
- RF-06: Dashboard drift: developer activations from HardwareBindings vs reseller bindings from MachineBindings; developer overview from database_path only; trial by dealCode substring; admin totals hide unreachable shards behind Warnings.
- RF-07: Reseller Wholesale Studio shows hardcoded mock quotas (ESSENTIAL 500/435, PRO_ANNUAL 200/65) and offers, with no live query. Frontend /pricing is static (Starter $0, Pro $39/$49, Enterprise $159/$199) and does not reflect admin DB price changes.
- New gates from 7404ea8: EndpointInventory failure and 5 new function-length violations (above).
- Environment blocker: Postgres dev/test database is gone (cluster online, no lara/lara_root). 24 tests cannot pass until it is recreated, and live shard tests cannot run until then. Real Postgres migrations for backup/restore tables were already known broken and were not fixed in this run.

## What works, on evidence

- Developer product/deal CRUD, blueprint on creation, mint via Sanctum owner endpoint without exposing the webhook secret, verify/activate split, licence expiry, gateway HMAC gating, signed Ed25519 manifests with minVersion, LicenceGate, heartbeat cull and auto-quarantine commands (dry-run only, live runs not done), fraud scoring for review, quota submit with idempotency and admin approve in one transaction (at API level), versioned platform price changes with audit and versions endpoint, admin isolation (platform price change does not rewrite developer deal prices, by code search, not by a live isolation test).

## What was NOT tested

- Playwright UI click-through (needs user-approved browser install).
- Real payment through a developer's own gateway.
- Live Postgres migrations, live cull, live auto-quarantine, host cron for schedule:run, real code signing, store submission.
- Cracked-client fraud attacks, replay, shared-key, and load/stress tests (cases written in testing/plan/attack-and-security-test-cases.md, not run).
- Dependency vulnerability scan.
- Live dashboard refresh after quota approve (needs a live frontend + backend, blocked by Postgres + browser limits on this VM).

## Open user decisions that block closing items

1. Reseller application: build the panel-plus-notification flow, or scope it out and remove the misleading success UI?
2. Initial quota on reseller approval: zero or explicit starter grant?
3. Published SLA: 2 to 3 business days, or another target? 30-day stale closure: denial or closure?
4. KYC depth: domain/website only, or document KYB before branded serial minting?
5. Catalog tiers: Xmind tiers vs Starter/Pro/Enterprise (blocks pricing page wiring and plan-limit tests).
6. Postgres rebuild + migration fixes: authorise that as a fix batch, or accept 24 tests staying red in this environment?
7. Earlier unanswered decisions (not re-decided here): Sanctum mint+enforcement, revoke-to-Serials sync, webhook-to-Serials fix, Xmind units/roles/lifetime contents. Note: 7404ea8 claims cross-shard serial lookup and seat binding fixes, but its shard tests are still Postgres-masked, so it does not by itself close the Serials proposals.

## Fix order recommended

1. Reseller application real flow (or remove the fake success UI) and Postgres database recreation.
2. Quota dual-store reconciliation and dashboard live wiring (Wholesale Studio, /pricing).
3. Developer plan persistence + enforcement, or explicit scoping that plans are not enforced.
4. Gateway Serials dual-write (both paths) + backfill, and a defined key-delivery channel.
5. EndpointInventory test coverage for the new controllers, and function-length refactoring for the 8 violations.
6. TraceRedactor, then the remaining Postgres migration fixes, then Playwright on the user's own machine.
