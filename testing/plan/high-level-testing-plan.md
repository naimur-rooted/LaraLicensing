# LaraLicensing High-Level Testing Plan

Status: DRAFT, updated with both research waves (2026-10-30). Research 01 (architecture and blast radius) and Research 02 (specs, coverage, admin confirmation research) are both complete. No test verdict in this file is final yet, and nothing here claims the current code is deployable. Research findings are code findings, not test results, until the test runs in section 5 prove them.

Branch under test: `muse` at 7404ea8 (remote commit "Bug: reseller - resolve cross-shard serial lookup and machine seat binding", 27 files, pulled 2026-10-30, not yet tested). `main` stays at 413986c and is never touched. Test data only. No real customer data, no production system, no real payment gateway.

## 1. Scope the user asked for

- Reseller/partner applies from the landing page, admin approves. User question: panel, email, or both?
- Developer portal: product creation within subscription, plan change/upgrade any time, dynamic price section and decoy effect, price change automatically updating the developer's customer software.
- Developer customer buys through the developer's own payment gateway, successful payment automatically delivers a licence key.
- Reseller B2B for companies/organisations.
- Reseller portal serial lookup and seat binding: user reports occasional failures and a generic "something failed on our side try again in a moment" message, plus unclear request-key errors.
- Reseller quota request: must reach the admin portal accurately, and admin approval must sync in real time.
- Admin can modify pricing and everything the business logic requires, without rewriting a developer's own customer deal prices.
- Admin, wholesaler/reseller, and user dashboards must sync the same information.
- Fraud detection: actually try to fraud LaraLicensing, do not only assert server logic.
- Security: rate limiting and other protections against hackers/cyber threats.
- Deliverables: this plan in `testing/plan/`, findings and red flags in `testing/audit/`, final report with a deployability verdict that does not hide failures.

## 2. Admin confirmation: recommended scenario (Research 02, Part B)

Recommendation: BOTH, with a strict split of roles.

- Approve in the admin panel only. The panel decision is the authority, with Sanctum auth, role middleware, anti-self-approval, and an audit row.
- Email is notification only, never the approval action. Email to admin on new application, and email to applicant on receipt, approval, and denial with reason. Emails link to the panel (admin, authenticated) or to a status/login page (applicant). They do not execute a decision.
- Do NOT build approve-by-email / magic-link approval. Reasons found in research: email magic links are not phishing-resistant, mailbox compromise or forwarding would grant reseller creation, link scanners/prefetchers can click before a human, a forwarded email keeps working after an admin leaves, and an email link would have to reimplement RBAC, anti-self-approval, and audit context that the panel already has.
- Reuse the existing quota-request pattern: Pending record, panel decision, transactional activation, audit row, retained denial reason (quota rule: at least 10 characters).
- SLA to decide with the user, not yet adopted: research found no universal SaaS reseller SLA. Examples only: HubSpot affiliate review in 2 to 3 business days, and a HubSpot partner rule treating no notice within 30 days as rejection. Suggested LaraLicensing target for decision: 2 to 3 business day initial review, queue age visible, reminder at 48 hours, stale applications closed with a reason after 30 days, never left silently Pending.
- Proportionate fraud/KYC checks for decision: corporate email domain matching company website, website reachable and not throwaway, desiredPrefix unique and non-colliding, duplicate application by email/company/prefix flagged not duplicated, volume band sane against any later quota grant, mandatory manual admin review before any quota. Full document KYB is a later decision if resellers handle money directly.
- On approve, in one transaction: Pending to Approved, reseller account created/activated, shard provisioned, desiredPrefix reserved, quota granted only if approval explicitly sets it (default zero or explicit starter grant, to decide; the requested volume band must NOT auto-become quota), audit row, welcome email with login instructions (no live API key in email, no magic link).
- On deny, in one transaction: Pending to Denied, reason retained, no account, no quota, no prefix, audit row, denial email with reason and reapplication path.
- On cancel/expiry: applicant can withdraw a pending application; admin can close stale ones with a reason; neither creates an account.

Open questions for the user (do not assume):
1. Initial quota on approval: zero, or an explicit starter grant?
2. Published SLA: 2 to 3 business days, or another target? Is 30-day stale closure a denial or a closure?
3. KYC depth: domain/website checks only for now, or document KYB before minting branded serials?
4. Catalog tiers: Xmind tiers (Free, Essential, Pro, Max, Max 2x, Lifetime $897) versus existing Starter/Pro/Enterprise. This blocks wiring frontend `/pricing` to the API and plan-limit enforcement tests.
5. Reseller application inbox: reuse the quota-request admin inbox pattern (`GET /Api/Admin/QuotaRequests/All` fanout) or a dedicated inbox endpoint?

## 3. Test areas and what will be tested (not yet run)

### Task-03 Reseller portal
- Apply flow: currently BLOCKED as a real flow, see red flag RF-01. Test cases are written for the recommended implementation, not for the current simulated modal.
- Serial lookup: cross-shard serial, serial in neither shard (must stay LicenseNotFound, no row created), prefix ownership mismatch (403), exhausted seats (409), binding after revoke, 15-minute rebind cooldown, stale binding cull.
- Seat login failures: reproduce the generic error, map each failure to endpoint, status, and redacted log line, instead of accepting a generic message.
- Quota: submit with 32-character Idempotency-Key, duplicate key returns existing row, Pending row in the reseller shard only, admin inbox `GET /Api/Admin/QuotaRequests/All`, approve in one transaction (LicensesGranted += ApprovedDelta plus LicenseLedger QuotaAdjusted), deny with reason, cancel by reseller, double-approve must not double-grant.
- Real-time definition for this test: same-transaction DB update plus a fresh read is real-time at API level. A dashboard that does not refresh on its own is NOT real-time, and will be reported as a dashboard gap, not hidden by a manual refresh in the test.

### Task-04 Developer portal
- Product creation within plan limits, plan upgrade/downgrade, what happens to existing products on downgrade.
- Dynamic pricing and decoy: deal create/update/delete validation (400, 404), decoy flag isDecoyTarget in deals and blueprint, and live propagation proof that a price/decoy change updates the end-user storefront. The dialog claim about a live storefront at :5050 is unverified and must be proved, not quoted.
- Gateway to licence: payment confirmation through the developer's external gateway to automatic licence generation and delivery. Real payment through a developer's own gateway is out of scope for this VM run; the webhook path will be tested with signed test webhooks on test data.

### Task-05 Dashboards and admin pricing
- Dashboard data sources for admin, reseller, and user dashboards, and where counts can drift.
- Admin pricing: versioned price changes (PricingPlanPriceVersion, versions endpoint, audit PricingPlanPriceChanged, future effectiveFrom rejected 400, same-price/no-op creates no version). Frontend `/pricing` still uses static getPricingTiers, so an admin DB change does not change the public page today. True grandfathering enforcement is not possible yet: no subscription/billing record exists, history plus audit only.
- Admin change must not rewrite a developer's own customer deal prices.

### Task-06 Fraud and security attack tests
- Fraud, adversarial (not only unit assertions): remove the verify condition in a test client, tamper a release file, submit a manifest with an old version below minVersion, replay a token/heartbeat, share one key across machines, spoof HWID, exceed seats concurrently.
- Existing fraud code to exercise: signed Ed25519 manifests, tamper-verify (alg/kid pinning, manifest hash, file hash, minVersion floor), LicenceGate (read-only), heartbeat cull (45 min default, dry-run found 2 stale bindings in the old testapp-e2e shard, no live cull run yet), fraud scoring (tamper +30 capped 90, concurrent +40, 70+ quarantine_review, client tamper report never auto-revokes), auto-quarantine (only recent live bindings within 45 min, dry-run quarantined 0), scheduling (cull every 15 min, auto-quarantine hourly, host must run schedule:run every minute, not yet proved on a host).
- Security: unauthenticated access (401), non-admin access (403), revoked/expired session, webhook HMAC abuse (missing/bad signature 401), rate limiting (spec 14-rate-limiting: auth, verify, licence management, self-update, global floor ip 60:600, 429 contract), brute force, replay, idempotency, captcha, impersonation lifecycle.
- Not in scope: real code signing and store submission (guidance doc only), extension thin client implementation (guidance only), load/stress at scale, dependency vulnerability scan (to record as missing evidence if not run), prompt-injection as a security feature (rejected by design, guidance only).

## 4. Existing coverage (Research 02 inventory, by listing, not by running)

Backend `backend/tests/Feature/` includes Admin (PricingPlanCrud 6, PricingPlanPriceVersion 5, QuotaRequests, ResellerCrud, Metrics, Audit, Sessions), Reseller (LicenseIndex, LicenseRenew, QuotaRequestSubmit, ResellerPortalArchitecture), Developer/EndUser (DeveloperPortal 29, DeveloperLicenseMint, EndUserVerification 11, PaymentWebhook 7), Fraud (TamperManifest 4, FraudPhase2 4, FraudPhase3 3), Security/Auth (RateLimitAuth, HmacSignature, Idempotency, RbacEnforcement, RoleGate, Impersonation, Captcha, Login, Me, Refresh, PasswordReset), and gates (EndpointInventory, FunctionBodyLineCap).
Frontend: last reported vitest run 145 files, 951 tests passed. Frontend e2e specs exist for admin-dashboard, admin-quota-approval, reseller-dashboard, portal-serial-lookup, auth, and others, but no Playwright UI click-through has been run here, and Playwright browsers still need user-approved install.

Spec gaps found:
- No reseller-application spec was found. Largest spec gap.
- No websocket/polling spec for real-time dashboard sync was found.
- No subscription/billing record spec for grandfathering enforcement.

## 5. Execution order

1. Research 01 lands, unified plan updated.
2. Full backend suite at 7404ea8 (previous full numbers at 984b576 do NOT cover the new remote commit).
3. Targeted reseller, quota, developer, pricing, fraud, and security suites.
4. Live API tests on test data: quota submit to admin approve sync, serial lookup and seat binding (including the 7404ea8 cross-shard fix), fraud attack cases, rate-limit cases.
5. Frontend vitest regression, typecheck baseline (44 pre-existing errors at last measure, to re-measure at 7404ea8).
6. Red flags written to `testing/audit/` as they are found, with endpoint, status, expected vs actual, and reproduction steps. Final report with deployability verdict last, not first.

## 6. Research 01 findings to test (code findings, not yet test results)

- Reseller apply: confirmed simulated, no backend endpoint, no application model fields. Only real creation is admin `POST /Api/Admin/Resellers` (Root `Resellers`, `IsActive` only).
- Serial lookup order (`GET /Serials/{serialValue}`, route closure, ThrottleLicenseLookups): registered_serials.json, then reseller shard by prefix, then product shards glob `product_*.sqlite` querying `ProductLicenses`, then Root `Serial`, else `SerialNotFound`. Separate admin endpoint `GET /Api/Admin/Serials/{SerialId}` is numeric-id only, do not confuse them.
- Seat binding: strict reseller routes under `ShardBindingMiddleware` plus loose lowercase preview routes under `ResellerAuthOrPreview`. `MachineBindingController::index` reads `MachineBindings` first, else globs product shards. Release sets `ReleasedAt` and rebind cooldown, audit `MachineUnbound`; clear cooldown audit `MachineCooldownCleared`. Ownership check defaults `ResellerId` to 1 if missing, and `resolveConnection` falls back shard to root on any throwable, silently.
- Tenant binding drift (priority test): strict `ShardBindingMiddleware` requires authenticated user, `Users.TenantId > 0`, resolves to `ResellerSlug`, 401/403/404 on failure. Loose `ResellerAuthOrPreview` resolves from `TenantId`, else `X-Reseller-Id` header, else `?ResellerId` query, else defaults to id 1 / slug `alim`, swallowing bind errors. Same action can behave differently by route group.
- Generic error located: "Something failed on our side. Try again in a moment." is frontend generic `ServerError`/`InternalError` in `src/lib/error-copy.ts`, `src/lib/copy.ts`, `src/lib/error-messages.ts`, not a reseller-specific code. Reproduction must capture backend error code and request id. Related copy: `IdempotencyKeyRequired` ("This action needs an idempotency key. Refresh and try again."), `QuotaLedgerConflict`, `RateLimited`, `PreconditionFailed`.
- Quota dual stores (priority test): approve writes shard `Quotas` + shard `LicenseLedger` in one transaction, no job/queue/websocket. But reseller dashboard gauge (`src/lib/lara-quota.ts`) reads Root `GET /Resellers/{ResellerId}/Quotas` / `QuotaLedger`, and `Reseller\LicenseController::quotas` reads Root `ResellerQuotas` / `ResellerQuotaLedger`. No approve-path call into Root `ResellerQuotas` was found. Root ledger mapping hardcodes `QuotaRequestId = null` and `ActorUserId = 1`. After every approve, read both stores and compare. UI refetch is separate: TanStack Query options exist, but a screen that does not refetch will show stale data after a commit.
- Developer subscription: no enforcement found. No subscription/plan/upgrade route in developer routes, no `PricingPlan`/`planCode`/`subscription` hits in Developer controllers or `ProductService`. Test product creation beyond any expected tier limit and record actual behaviour, do not assume a limit exists.
- Developer pricing propagation: price/deal change updates Root `ProductDeal`, `publicDeals` returns it on next fetch (no caching found), so a refetching storefront sees it. No push/webhook/notification to customer software was found. Blueprint is generated text at generation time and is stale until regenerated. `syncShardLicensesWithDeal` updates `allowedSeats` in the `database_path` shard only in the snippet read; check `repodb` mirror separately.
- Gateway delivery gap: webhookIssue returns the key in the webhook response only; no `Mail`/`Mailable`/`Notification` found in `PaymentWebhookService` or Webhooks controllers. Delivery to the end customer depends on the developer's gateway/software forwarding it. Multi-gateway path: Stripe 5-minute timestamp window, LemonSqueezy hex HMAC, WooCommerce base64 HMAC, supported events only, deduplication via Root `WebhookEvents` (`EventId`, `Gateway`), serial includes `microtime` so replay protection depends on deduplication catching it first. Sandbox checkout (`GET /checkout/{slug}`, `POST /checkout/{slug}/process`) is test mode with hardcoded defaults (email `hotel-admin@reservations.local`, name `Front Desk Manager`) and is not evidence a real gateway works.
- Dashboard drift sources: admin metrics fanout with `Warnings` on unreachable shards (totals alone hide a missing tenant); developer overview reads only `database_path` shards (a `repodb`-only product shows zero) and counts activations from `HardwareBindings`, while reseller bindings read `MachineBindings`; trial detection is a substring match on `dealCode` containing trial/free/demo; developer zero state uses hardcoded maxima; `ResellerWholesaleStudio` initialises quota in component state and was not proven live, check it against the `lara-quota` query. End-user side is public/handshake endpoints, not a single dashboard.
- Admin pricing isolation: no code found rewriting developer `ProductDeal` prices on a platform `PricingPlan` change, and no admin route for developer products/deals was found in the Admin group. Other admin surfaces (Resellers, Users/roles/impersonation, Prefixes, Licences/bindings, QuotaRequests, Features read-only, AppUpdates, RuntimeConfig SuperAdmin with ETag, Backup Exports/Imports where apply is rejected 400 until an apply-phase job lands, AuditLogs read-only, Errors tail, Sessions revoke) must be tested separately, not assumed under PricingPlans.

## 7. Constraints carried forward

- Work on `muse` only, never main, never force-push.
- Plan first, then tests, then report. Confusion logged, not blocking, except where a choice would break integrations or shared behaviour/schema, where the user decides.
- The test ban in the execute-parent-task skill is overridden by the user's explicit testing instruction in the same message; the skill itself ranks user instructions highest.
- Playwright browser install needs separate user approval and must not be bypassed.
- Dev Postgres database was emptied by the overnight reset on 2026-10-29; a live dev test needs Postgres re-setup first. Sqlite shards survived, including product_testapp-e2e-20261028.sqlite.
