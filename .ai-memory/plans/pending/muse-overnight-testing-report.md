# Overnight Testing Report - LaraLicensing (branch muse)

Date: 2026-10-28
Branch: muse only. Main untouched. No force push. No CI checks disabled.

## Summary

- Frontend vitest: PASS. 145 test files, 951 tests, exit 0, about 179 seconds.
  Command: bun install (494 packages, 46.73s), then bun run test.
- Frontend typecheck via repo script: BLOCKED. bun run typecheck uses tsgo, tsgo not found, exit 127.
  Fallback tsc --noEmit: 44 error occurrences, 75 output lines. See static checks below.
  This is not claimed as the repo official typecheck result, because the repo script uses tsgo.
- Backend phpunit: BLOCKED, not run. No PHP runtime, no Composer, no backend/vendor, no backend/.env on this machine.
  phpunit.xml design uses in-memory SQLite, array mail/cache/session, sync queue, so the suite itself should not need live Postgres, mail, or a gateway, but it cannot bootstrap without PHP.
- Playwright smoke: BLOCKED, not run. No node_modules at first check (now installed), no browsers in ~/.cache/ms-playwright, no frontend server on 8080, no seeded backend, no E2E_ADMIN_EMAIL or E2E_ADMIN_PASSWORD.
  All 5 smoke specs exist: health, auth-login, admin-dashboard, admin-license-crud, portal-serial-lookup.
- Fixes in this run: 3 small, reviewable fixes, listed below. Vitest re-ran after the fixes and passed again, see verification section.

## Per-feature status

Auth - developer login, admin login, roles:
- Working by code inspection for Auth controllers with Sanctum, but developer route group has no middleware. See backend bug B1.
- Frontend: real sign-in, sign-up, and admin login forms present. auth.sign-in and auth.sign-up are redirect stubs only.

Product CRUD:
- Present in frontend as a dialog, not a route. Backend store, show, index present.
- Fixed in this run: product dialog no longer claims success on failure, and no longer fabricates a key locally. See F1.
- Still broken: store validation is minimal, slug duplicate can hit a DB unique constraint as a 500, and show/addDeal/updateDeal/deleteDeal have no ownership check in the inspected code. See B2.

Pricing deals - daily, monthly, yearly:
- Present in frontend dialogs, with daily, weekly, monthly, quarterly, yearly, biennial, lifetime options.
- Backend billingInterval is a free-form string with no enum validation, priceCents is cast blindly, dealCode has no per-product uniqueness.
- Fixed in this run: Add Deal dialog no longer appends a deal locally when the backend rejects it. See F2.
- Still broken: no validated daily/monthly/yearly-only set, no expiry derivation from interval in the verify path.

AI blueprint:
- Missing as specified. Nothing at product creation generates or stores a blueprint. On-demand endpoints exist: GET /api/v1/developer/products/{slug}/blueprint, POST /api/v1/developer/products/{slug}/blueprint-code, POST /api/v1/developer/ai/design-pricing, POST /api/v1/developer/products/{slug}/apply-ai-deals.
- Frontend blueprint is manual only, via Export AI Blueprint on a product card, with a local text fallback and optional Gemini codegen. Gemini codegen needs a key that is not in .env.example.
- Still broken: blueprint not auto-generated on creation, not stored (no blueprint column on Product), and a no-deals fallback in code uses a fixed price that should be checked against the brief before any change.

Public pricing JSON:
- Working by code inspection: GET /v1/products/{slug}/deals returns only isActive deals, with product slug, name, category, description, checkIntervalDays, and deals.
- Frontend UI gap: no customer storefront renders that JSON. The frontend only embeds the endpoint URL inside blueprint text.

License keys - payment confirmation trigger:
- Present in code, broken in trust and linkage. See B3, B4, B5.
- Backend routes: POST /v1/products/{slug}/licenses/issue (webhookIssue), POST /v1/webhooks/stripe, /v1/webhooks/lemonsqueezy, /v1/webhooks/woocommerce.
- Webhook service has HMAC per gateway, supported-event filtering, and idempotent replay via WebhookEvents, by inspection.
- Frontend gap: no gateway or webhook config UI in the developer portal. Only a mock Simulate Mint button with hardcoded test values.

Activation - customer activation, portal visibility, control:
- Partial. Developer license list and delete exist and are ownership-checked in DeveloperLicenseController. No activate/deactivate toggle and no unbind-machine action in the developer view. No polling, WebSocket, or EventSource, so activation is not instant in the UI; it needs manual refresh.
- Still broken: verify auto-registers unknown serials, verify binds a seat, and there is no expiry check in the verify path. See B6.

Developer portal:
- Present as a tab inside /portal/home, not a standalone /developer route. Portal routing dispatches by role from GET /Users/Me.
- Product list, deal list, license list, and delete control present. Activation control and instant visibility missing, as above.

Admin panel - packages and pricing:
- Missing for developer products and deals. Admin panel is extensive for legacy licenses, users, resellers, serials, quotas, roles, audit, features, snapshots, backup, app updates, errors, runtime, api docs, and design.
- No admin.packages, admin.pricing, admin.plans, or admin.subscriptions route found. Platform PricingPlans are read-only via GET /Pricing/Plans.
- Platform tier picker in portal.home is local state only, with no API call to persist a plan change.

## Fixes made in this run

F1. src/components/developer/CreateProductDialog.tsx
- Removed the locally fabricated ed25519PublicKey.
- Now checks the POST response, shows an error and keeps the dialog open on failure, and only uses a public key supplied by the server response. On success it appends the product, closes the dialog, and resets the form.
- Evidence before fix: line 201 fabricated a key, lines 219-224 closed the dialog and appended the product even after a swallowed catch.

F2. src/components/developer/DeveloperProductsDashboard.tsx, handleAddDealSubmit
- Now checks the POST response and IsSuccess, shows an error and keeps the dialog open on failure, and only appends the deal on success.
- Evidence before fix: POST was awaited with an empty catch and the deal was appended unconditionally.

F3. backend/app/Models/Product.php
- Added $hidden for ed25519PrivateKey and webhookSecret.
- Reason: ProductController returns the model directly in index, show, and store, and the model had no $hidden, so the private key and webhook secret could be serialized to callers. This fix is code-inspection only and is NOT live-verified, because PHP is missing here. It must be re-checked with phpunit in a PHP environment before being treated as fully verified.

No other files were changed in this run. No CI checks were disabled. No main branch changes.

## Still broken, with evidence (not fixed in this run)

B1. Developer route group has no middleware.
- File: backend/routes/api.php, line 632, Route::prefix('v1/developer')->group, no auth:sanctum.
- Same pattern for webhookIssue line 650, gateway webhooks lines 654-658, verify/activate/deactivate/heartbeat lines 662-677, reseller-wholesale line 681, security line 688.
- Risk: per-controller checks are inconsistent. Store checks auth, but show, addDeal, updateDeal, deleteDeal, aiBlueprint, and several AiArchitect and AdvancedLicensing methods do not enforce ownership in the inspected code.
- Why not fixed here: adding auth would change behavior for existing tests that post unauthenticated, including DeveloperPortalTest, and cannot be verified live without PHP. Needs a PHP run and test updates in the same batch.

B2. Product and deal validation gaps.
- ProductController store only requires productName. ProductService createProduct slugs the name with no duplicate check, so a duplicate slug can hit the DB unique constraint as a 500, and an all-punctuation name can yield an empty slug.
- addDeal casts dealName and priceCents blindly, with no existence check, so a missing dealName can be a 500 and negative prices are accepted. dealCode has no per-product uniqueness, so webhookIssue first() lookup can pick the wrong deal.
- deleteDeal returns 200 with IsSuccess false for a missing deal, unlike updateDeal which throws 404.

B3. webhookIssue is unauthenticated and deterministic.
- File: backend/app/Http/Controllers/Developer/ProductController.php, webhookIssue line 108.
- Anyone posting transactionId and customerEmail for a known slug mints a license. Serial is md5(txId.email) truncated to 16 hex chars, uppercased with the slug prefix.
- recordShardLicense has a check-then-insert with no transaction, so concurrent replays can hit a UNIQUE constraint as a 500.
- The ProductLicenses insert stores no customer email, no transaction id, and no expiry, so the portal cannot show who bought a key.

B4. Gateway webhook linkage and fallback secret.
- File: backend/app/Http/Controllers/Webhooks/PaymentWebhookController.php, backend/app/Services/PaymentWebhookService.php.
- Webhook serials are written only to Serials, never to ProductLicenses, so they do not appear in the developer portal list. On first verify, the key is auto-registered under the first deal, not the purchased dealCode.
- Unknown product_slug still passes signature via a hardcoded fallback secret in the repo, inserts a WebhookEvents row, and returns success with a serial that exists nowhere.
- No Stripe timestamp freshness check, missing eventId falls back to uniqid, and recordEvent inserts before the shard write with no transaction, so a shard failure can mark an event processed while the license is lost.

B5. Secret leak, partially fixed by F3, needs live verification.
- Before F3, Product had no $hidden while fillable included ed25519PrivateKey and webhookSecret, and show is unauthenticated.
- F3 hides those fields on serialization, but the per-product webhookSecret is auto-generated at creation with no documented retrieve or rotate endpoint, so gateway setup documentation is still missing.

B6. Verify auto-registers unknown serials and binds seats on verify.
- File: backend/app/Http/Controllers/Developer/AdvancedLicensingController.php, backend/app/Services/ClientHandshakeService.php.
- Verify auto-registers any unknown serial for an existing active product, creating an active license with the first deal seats. This defeats the payment gate by inspection.
- Activate is an alias of verifyClient, and verify itself binds a seat, so verification is not read-only.
- No expiry is checked, ProductLicenses has no expiry column, and no deal-interval derivation exists, so daily, monthly, and yearly deals never expire through this path.
- Seat-limit status is inconsistent: SeatLimitExceeded gives 409 on /v1/verify but 403 on /v1/products/{slug}/verify, by inspection.
- Dual-table drift: verify counts max(MachineBindings, HardwareBindings) but heartbeat and deactivate consult only MachineBindings.

B7. Admin packages and pricing, and portal controls.
- No admin create, update, or delete route for platform PricingPlans or for developer products and deals.
- Developer portal has delete only for licenses, blocked with 409 when machines are bound, with a message saying to revoke instead, but no portal revoke or suspend endpoint exists.

## Static checks

- bun run test: PASS, 145 files, 951 tests, exit 0.
- bun run typecheck: BLOCKED, tsgo not found, exit 127.
- tsc --noEmit fallback: 44 error occurrences, 75 output lines. Files include src/components/badge/registry.ts, src/components/reseller/ResellerCustomerRequestsCard.tsx, src/lib/lara-auth.ts, src/lib/lara-customer-request.ts, src/lib/lara-license.ts, src/lib/lara-serial.ts, src/lib/preview-fixtures/index.ts, src/lib/preview-fixtures/licenses.ts, src/lib/preview-fixtures/stubs.ts, src/lib/preview-seeds/error.ts, and several src/routes/_authenticated files.
- Lint: not run in this batch, to keep the change set small and because typecheck already shows a large pre-existing error set that needs a tsgo baseline first.
- Backend phpstan: BLOCKED, no PHP, no vendor.

## Test commands run

- gh repo view naimur-rooted/LaraLicensing --json: public, main, TypeScript primary language.
- git clone to ~/workspace/repos/LaraLicensing, branch muse, clean tree at start except plan and confusion log.
- bun install: 494 packages, 46.73s, exit 0.
- bun run test: 145 files passed, 951 tests passed, exit 0, about 179 seconds, 1 obsolete snapshot in tests/theme-tokens-snapshot.test.ts.
- bun run typecheck: exit 127, tsgo not found.
- ./node_modules/.bin/tsc --noEmit: 44 error occurrences.
- node --version v24.22.1, npm 10.9.4, bun 1.4.4, pnpm not found, php not found, composer not found.
- Backend phpunit: not run, blocker above.
- Playwright smoke: not run, blockers above.

## Verification after fixes

- Frontend fixes F1 and F2 are in files with zero vitest coverage for those components, by grep for blueprint, DeveloperProductsDashboard, CreateProductDialog, and deal in tests.
- Full vitest re-run after F1, F2, and F3: 145 test files passed, 951 tests passed, exit 0, about 180 seconds. This verifies no frontend test regression, not that the fixed dialogs work live, because no vitest test covers those dialogs.
- Backend fix F3 is not live-verified, because PHP is missing here. It must be re-checked with phpunit in a PHP environment.

## What remains

1. Done in this run: full vitest re-run after F1, F2, and F3 passed, 145 files, 951 tests.
2. Get a PHP 8.3 environment, run composer install, run phpunit, and verify F3 and the B1 to B7 backend items live.
3. Fix B1 to B7 in small batches, with test updates where auth behavior changes, and re-run affected tests after each batch.
4. Install Playwright browsers, run a seeded backend and frontend, and run the 5 smoke specs.
5. Decide the blueprint format: auto-generate on creation and store it, or change the brief to on-demand only, and remove any fixed dummy fallback price.
6. Decide admin packages and pricing: add routes and UI, or record that admin package management is out of scope for the developer product surface.
7. Decide the real gateway contract: document the expected metadata for Stripe, LemonSqueezy, and WooCommerce, remove the hardcoded fallback secret, and add a product existence check before returning success.
8. Resolve confusion log entries, especially the tsgo baseline, the gitmap instruction conflict, and the PHP environment.

## Files changed in this report batch

- src/components/developer/CreateProductDialog.tsx (F1)
- src/components/developer/DeveloperProductsDashboard.tsx (F2)
- backend/app/Models/Product.php (F3)
- .ai-memory/plans/pending/muse-overnight-testing-report.md (this file)
- .ai-memory/ambiguous-questions/muse-overnight-testing-confusion.md (appended entries, no deletions)

## Phase 2 — autonomous continuation (2026-10-28, branch muse)

### Environment actually working
- PHP 8.3.6 installed via apt (php8.3-cli, mbstring, xml, curl, zip, sqlite3, bcmath, intl, pgsql, tokenizer, fileinfo). User in VM is root, so no sudo needed; system changes outside home are ephemeral.
- Composer via repo backend/composer.phar, version 2.11-dev, PHP 8.3.6.
- composer install without flags FAILED exit 2: lock file not up to date, locked symfony 8.1 packages require php >=8.4.1, contradicting composer.json php ^8.3. Lock was generated on PHP 8.4.
- composer install with --ignore-platform-reqs, COMPOSER_ALLOW_SUPERUSER=1, --prefer-dist, --no-interaction SUCCEEDED exit 0, 129/129 packages, vendor present. Vendor is gitignored, must not be committed. Any phpunit pass on 8.3 must be reported with this caveat, not as a clean 8.4 run.
- backend/.env created for tests only (gitignored, from phpunit.xml settings: SQLite in-memory, array mail/cache/session, sync queue, APP_KEY from phpunit.xml). This removed the phpdotenv "Failed to open stream: No such file" warnings in Pest runs. .env is NOT committed.
- Frontend vitest not re-run in phase 2 (no frontend files changed in phase 2; previous run: 145 files / 951 tests passed).
- Playwright: NOT run. `npx playwright install chromium` entered pending_user_confirmation in this environment and was terminated, not bypassed. ~/.cache/ms-playwright still absent. No browser, no seeded backend+frontend E2E, so E2E is NOT claimed passed. Exact blocker: browser install requires user confirmation in this environment.
- Typecheck baseline CONFIRMED: repo script `tsgo --noEmit` fails because tsgo is not installed and `tsgo` is 404 on npm. Its successor `@typescript/native-preview` (7.0.0-dev.20260707.2, via `bun x`, no repo files changed) runs and reports exactly 44 errors, same count as the tsc fallback. Breakdown: preview-fixtures/stubs.ts 14, badge/registry.ts 5, reseller serials routes 3, lara-serial.ts 3, portal.tsx 2, lara-license.ts 2, lara-customer-request.ts 2, plus single errors in 11 other files. None are in files changed in phase 2 (backend only). Decision: do NOT blindly fix all 44 in this phase; they are pre-existing and unrelated to the security/validation batches.

### B1 — developer auth (DONE, live-verified)
- backend/routes/api.php: developer group now `Route::middleware('auth:sanctum')->prefix('v1/developer')`. Public deals route stays outside; client verify/activate and gateway webhooks stay unauthenticated by design (license-serial / HMAC, not developer login).
- ProductController show/addDeal/updateDeal/deleteDeal/aiBlueprint now enforce ownership via requireOwnerForRequest (401 unauthenticated, 403 non-owner). DeveloperLicenseController already enforced ownership.
- Tests updated in same batch: DeveloperPortalTest actingAs now uses 'sanctum' guard; unauthenticated list test split into unauthenticated-401 + authenticated-200; add-deal and blueprint tests now act as owner.
- Result: DeveloperPortalTest 22 passed (latest run 147 assertions; assertion totals vary between runs, pass count is stable).

### B3 — decoupled webhookIssue (PARTIAL, live-verified)
- webhookIssue now validates customerEmail with FILTER_VALIDATE_EMAIL and requires dealCode to match an existing deal (uppercased lookup); unknown/empty dealCode throws ValidationException (400), no 'DEFAULT' fallback, seats come from the real deal.
- recordShardLicense check-then-insert changed to INSERT OR IGNORE inside try/catch, ignoring only UNIQUE/23000 PDOExceptions, so concurrent replays do not 500.
- NOT done: HMAC gating for webhookIssue. That would break all existing webhookIssue tests and developer integrations using the simple endpoint; gateway webhook path already provides HMAC. Logged as confusion entry 16, decision needed, not silently applied.

### B4 — gateway webhooks (DONE, live-verified)
- Hardcoded fallback secret constant removed entirely from PaymentWebhookService. resolveSecret now returns product webhookSecret, else env <GATEWAY>_WEBHOOK_SECRET, else null. New test: unknown product has no fallback secret (assertNull).
- Controller order: unsupported event -> ignored first (no row, no license); then secret null -> 401 signature error; then signature check -> 401; then product existence/active check -> 404 ProductNotFound with no serial and no WebhookEvents row; then customerEmail -> 422; then process. Signature-before-product keeps the existing invalid-signature test at 401 and does not leak product existence.
- Stripe timestamp freshness: isStripeTimestampValid now rejects timestamps more than 300 seconds old or in the future. Existing tests use time() at request time, so valid webhooks still pass.
- Result: PaymentWebhookTest 7 passed (31 assertions), including valid Stripe/LemonSqueezy/WooCommerce, invalid signature 401, unsupported ignored, missing email 422, unknown-product no fallback secret.

### B6 — verify (PARTIAL, live-verified; full split NOT done)
- Done: verify SeatLimitExceeded status unified to 409 to match activate(). Previously /v1/verify returned 409 but /v1/products/{slug}/verify returned 403. AdvancedEcosystemTest updated in same batch (403 -> 409).
- Result: AdvancedEcosystemTest 4 passed (23 assertions); EndUserVerificationTest 8 passed (34 assertions) — baseline unchanged.
- NOT done: verify read-only / activate-only-binding split, and expiry handling. Reason: existing tests (EndUserVerificationTest, AdvancedEcosystemTest) and the Serials->ProductLicenses bridge rely on verify/activate auto-registering unknown serials; gateway-issued serials live only in Serials until first verify. A blunt split would break those tests and legacy clients. ProductLicenses has no expiry column, so expiry derivation from billingInterval would need a schema migration plus shard-schema changes in multiple places. Both are logged as confusion entries 17-18 with a concrete plan, not claimed fixed.

### B2 — validation (DONE, live-verified)
- ProductService: slug empty after Str::slug -> ValidationException; duplicate slug -> ValidationException (was DB 500). ALLOWED_BILLING_INTERVALS = daily, weekly, monthly, quarterly, yearly, biennial, lifetime (from AiArchitectService string, not invented). validateDealAttributes: dealName/dealCode required, interval in set, priceCents numeric >=0, allowedMachineSeats numeric >=1, dealCode unique per product (excluding self on update). createDeal and updateDeal (merged values) both validate.
- ProductController deleteDeal now throws NotFoundException 404 for missing deal (was 200 with IsSuccess false), matching updateDeal and license delete.
- Validation errors map to 400 ValidationInputInvalid per 02-spec/21-app/12-error-taxonomy.md, not 422. New tests: duplicate slug -> 400 (not 500), negative deal price -> 400.
- Product $hidden (F3 from phase 1) is now live-verified indirectly: Product model serialisation in developer list/show tests passes with private key/webhook secret hidden in the model definition; no test asserts the hidden fields explicitly — that explicit assertion remains a small follow-up, not claimed as a dedicated test.

### B7 — portal/admin (DONE for backend; frontend developer license UI remains open)
- Developer license revoke: POST /v1/developer/products/{slug}/licenses/{licenseId}/revoke in DeveloperLicenseController, ownership-checked, sets isRevoked=1/isActive=0 and deletes HardwareBindings + MachineBindings, so the license can then be deleted. This is the endpoint delete's 409 message previously pointed to but did not exist. Test: revoke with bound seats then delete passes.
- Admin PricingPlans CRUD: new Admin\PricingPlanController inside the existing Admin group (auth:sanctum + session.active + require.role:Admin|SuperAdmin), reusing the PricingPlan model and its fillable fields, no new package system. Routes: GET/POST /Api/Admin/PricingPlans, GET/PATCH/DELETE /Api/Admin/PricingPlans/{id}. Validation: planCode required + unique, planName required, interval in allowed set, prices >=0, slots >=1. Public GET /Pricing/Plans (active only) unchanged. Test: PricingPlanCrudTest 5 passed (59 assertions) — bare 401, non-Admin 403, CRUD round-trip, duplicate code 400, negative price 400.
- Portal UI note: customer portal already has unbind/deactivate UI (CustomerRequestsHistory.tsx, Unbind Device button). Developer dashboard has no license-list UI; backend list/delete/revoke exist, frontend developer license management UI is still open work, not claimed done.

### Blueprint (DONE for backend auto-generation, no new format, no Gemini required)
- ProductController store now returns BlueprintMarkdown alongside unchanged Data, generated via existing ProductService::generateAiBlueprint (same format as on-demand GET blueprint). No new format, no Gemini key for the default path.
- Dummy fallback removed: when a product has no deals, blueprint previously emitted "- Standard Plan (STD): $49.00 / yearly (1 seat)". Now it emits a neutral "No pricing deals configured yet. Add a deal, then regenerate the blueprint." line, consistent with the blueprint's own Zero Dummy Fallback mandate.
- Test: product creation returns auto-generated blueprint containing the blueprint title and product slug, and not containing $49.00.
- NOT done: blueprint storage in a Products column/migration. Blueprint is generated on creation (returned in response) and on demand (existing endpoint), not persisted. A migration decision is logged as confusion entry 19.

### Commits pushed to muse in phase 2
- 16bead9 muse - developer auth, webhook fallback secret, and deal validation
- 26b08ed muse - blueprint on product creation, dummy fallback removed, verify seat status unified
- 511d030 muse - admin PricingPlans CRUD in existing Admin group
- bf30a9f muse - developer license revoke endpoint for bound seats
- (Report/confusion update commit to follow after full pest suite; origin/main unchanged at 413986c throughout.)

### What remains (with evidence)
1. Full backend pest suite: running at report time; do not claim full-suite pass until its tail is captured.
2. B6 full split (verify read-only, activate binds only) + expiry schema: see confusion 17-18. Requires test updates in EndUserVerificationTest and AdvancedEcosystemTest (activate first, then verify; unknown serial -> LicenseNotFound) and a migration decision for expiry.
3. B3 full HMAC gating for webhookIssue: breaking-change decision, see confusion 16.
4. Blueprint persistence (column/migration vs response + on-demand): see confusion 19.
5. Frontend developer license management UI (list/revoke/delete in DeveloperProductsDashboard): backend exists, UI does not.
6. Playwright 5 smoke specs: blocked by browser-install user confirmation (see above). Needs a seeded backend + frontend + E2E_ADMIN_EMAIL/PASSWORD, which are not set in this environment.
7. Frontend typecheck 44 errors: pre-existing, baseline confirmed via @typescript/native-preview, not fixed in this phase by design.
8. Frontend vitest re-run in phase 2: not run (no frontend changes in phase 2); last pass was phase 1, 145 files / 951 tests.
9. Explicit Product $hidden assertion test: follow-up, not yet written.

### Phase 2 addendum — full suite and architecture gates
- Full backend Pest suite, latest run: 518 passed, 27 failed, 13 skipped, 10010 assertions, duration ~33s, exit 2. Do NOT claim full-suite pass.
- The 27 failures break down as: (a) Postgres QueryException "connection to server at 127.0.0.1, port 5432 failed: Connection refused" for Admin (Features, Licenses, Metrics, Serials, Users), Auth (DemoLogin, MeEndpoint, Refresh), ResellerPortalArchitecture, and Seed suites — these suites require a live Postgres shard, which is not running in this VM, and are unrelated to phase 2 changes; (b) FunctionBodyLineCapTest, EndpointInventoryTest, TraceRedactorTest — see below.
- FunctionBodyLineCapTest: phase 2 initially introduced 4 new violations (validateDealAttributes, buildUpdateDealAttributes, handleGateway, webhookIssue worsened). Those were refactored into helpers (validateDealIdentity, validateDealNumbers, assertDealCodeUnique, mergeDealAttributes, parseDealFeatures, buildDealUpdateResult, checkSignature, checkProduct, findActiveProduct, productNotFound, validateWebhookInputs, resolveWebhookDeal, buildWebhookPayload) with NO baseline additions. After refactor, the test fails only on 3 pre-existing violations untouched by phase 2: DeveloperRegisterController::buildSuccessResponse (21), AdvancedLicensingController::extractClientInputs (32), ClientHandshakeService::bindMachineSeat (28). That test was already failing before phase 2 for those 3.
- EndpointInventoryTest: phase 2 new routes (PricingPlans CRUD, license revoke) initially added missing-test offenders. Fixed by referencing the controller classes in their covering tests (PricingPlanCrudTest, DeveloperPortalTest class_exists tests). Remaining offenders are pre-existing, e.g. AiArchitectController apply-ai-deals and blueprint-code routes. Our routes are no longer offenders.
- TraceRedactorTest: 1 failed, 2 passed in isolation, unrelated to phase 2 (no redaction code touched) — pre-existing/environment failure, token-shape masking case.
- Flakiness note: an earlier full-suite run failed AdvancedEcosystemTest (2 tests) and DeveloperPortalTest revoke/cannot-delete tests, while isolated runs passed consistently and the latest full run passes them. That is test-order/shared-state flakiness in those suites, not a deterministic phase 2 regression, but it must be noted, not hidden.
- Affected suites after refactor, all passing in isolation: DeveloperPortalTest 23 passed, PaymentWebhookTest 7 passed, PricingPlanCrudTest 6 passed, AdvancedEcosystemTest 4 passed, EndUserVerificationTest 8 passed.

### Phase 3 — user decisions 1A, 2A, 3A implemented (2026-10-28, branch muse)

Decisions (user reply 2026-10-28 08:21 +06): 1A gate webhookIssue with HMAC (breaking), 2A split verify read-only / activate bind-only with gateway dual-write first, 3A add licence expiry column, derive and enforce. All three are DONE, with evidence below. No frontend files changed in Phase 3, so frontend vitest is not re-run; last pass remains phase 1, 145 files / 951 tests.

#### 1A — webhookIssue HMAC (DONE, breaking, tests updated in same batch)
- Contract, documented in ProductController comments and here: header `X-Webhook-Signature` (or `X-Signature`) must be hex HMAC-SHA256 of the raw request body, keyed by the product `webhookSecret`. Missing secret, missing header, or bad signature => 401 AuthUnauthorized. There is NO unsigned fallback.
- webhookIssue now also derives licence expiry from the deal interval (see 3A) and records customerEmail + transactionId on the ProductLicenses row, via the same recordShardLicense path as gateway issuance.
- Tests updated in the same batch: DeveloperPortalTest gained an `issueLicenseSigned` helper (raw JSON body + HMAC header, with `Accept: application/json` — without it the error handler renders an Inertia view and masks the 401 as a 500 in tests) and all 7 existing webhookIssue fixture calls now sign. New test: unsigned => 401, wrong secret => 401, correctly signed => 200.
- Known gap, not hidden: `webhookSecret` is in the Product model `$hidden` list, so it is not returned in product serialization. A developer (or integration) must obtain the secret from the product record by another route; there is still no documented retrieve/rotate endpoint for it (phase 1 F3, still open). HMAC verification itself uses the DB secret regardless of UI retrieval.

#### 2A — verify read-only / activate bind-only, gateway dual-write first (DONE)
- Order followed: (a) dual-write first, (b) split second.
- (a) PaymentWebhookService::recordEvent now calls dualWriteProductLicense: resolves the deal (product + uppercased dealCode), seats (deal seats or 1), expiry via LicenseExpiry::derive(deal billingInterval), and writes to the database_path shard ProductLicenses row with dealCode, allowedSeats, customerEmail, transactionId (= gateway event id) and expiresAt. INSERT OR IGNORE + COALESCE UPDATE, so replay does not duplicate (verified in PaymentWebhookTest: replay returns the same serial with IsReplayed true and ProductLicenses count stays 1).
- Test fixture gap found by the dual-write test: PaymentWebhookTest's seeded product previously had NO deals, so dual-write derived expiry null. Fixed in the same batch by seeding PILOT_ANNUAL (yearly), LS_LIFETIME (lifetime) and WOO_STARTER (monthly) deals for the test product.
- (b) ClientHandshakeService::verifyClient is now read-only: resolve product else ProductNotFound, find licence by serial else LicenseNotFound (404, no auto-register, no seat binding), then verifyExistingLicense (revoked check, expiry check, risk evaluate + log, bound/activeSeats from MachineBindings only). activate is bind-only: lookup only, reject unknown (404 LicenseNotFound) and expired (403 LicenseExpired), then processVerification binds a seat. findOrRegisterLicense and insertNewProductLicense were removed; licences are created only by webhookIssue / gateway dual-write.
- Seat counting unified on MachineBindings with ReleasedAt IS NULL as the single source across verify, heartbeat, deactivate and portal list. countActiveHardwareBindings is retained but no longer used for seat limits; mirroring to HardwareBindings is retained. DeveloperLicenseController portal list now counts activeSeats from MachineBindings and returns expiresAt per licence (null if the shard column is absent).
- Tests updated in the same batch: EndUserVerificationTest pre-seeds the issued licence (ELM_1M_SINGLE, monthly expiry) in setUp; AdvancedEcosystemTest pre-seeds per-test with a fresh shard and uses activate for binding, verify for read-only checks. New tests: bare verify creates no binding, unknown-serial verify => 404 LicenseNotFound and creates no row, unknown-serial activate => 404, expired activate/verify => 403 LicenseExpired.
- Quarantine exception, intentional: FraudAlertService::quarantineLicense still creates a QUARANTINED ProductLicenses row for an unknown serial. That is an owner/admin action (now Sanctum + owner-checked, see below), not a client auto-register, and the fraud test depends on verify rejecting that quarantined serial with LicenseRevoked.

#### 3A — licence expiry (DONE)
- New backend/app/Services/LicenseExpiry.php: derive(interval, issuedAt?) — daily addDay, weekly addWeek, monthly addMonth, quarterly addMonths(3), yearly addYear, biennial addYears(2), lifetime null, unknown interval null; isExpired(expiresAt, now?) — null/empty => false, else Carbon::parse isPast.
- New backend/app/Support/LicenseShardSchema.php: ensureProductLicenses creates ProductLicenses with id, serialValue UNIQUE, dealCode, allowedSeats, isActive, isRevoked, customerEmail TEXT NULL, transactionId TEXT NULL, expiresAt TEXT NULL, createdAt, and adds the three new columns via PRAGMA table_info + ALTER TABLE ADD COLUMN on old shards. ProductService::initSerialsAndBindingsTables and ClientHandshakeService::createProductLicensesTable both call it after their legacy CREATE, so fresh and existing shards both gain the columns. recordShardLicense signature is now (Product, serial, dealCode, allowedSeats, ?expiresAt, ?customerEmail, ?transactionId) with COALESCE fill on existing rows.
- Enforcement: verify and activate both reject expired licences with errorCode LicenseExpired, HTTP 403, not a generic 500. buildVerificationResult now returns expiresAt and isActivated.
- Tests: LicenseExpiry derivation for every interval plus lifetime null, expired verify rejection and expired activate rejection (EndUserVerificationTest), yearly expiry non-null on gateway dual-write (PaymentWebhookTest).

#### Phase 3, if time allowed (DONE, in this order)
- AiArchitect ownership: AiArchitectController gained requireOwner (same pattern as ProductController); applyAiDeals and generateBlueprintCode are owner-only, designPricing is owner-only for existing products (transient name-only proposals unchanged). Test: non-owner apply-ai-deals => 403, unauthenticated => 401.
- Reseller-wholesale and security groups: both route groups now require auth:sanctum. Security endpoints (events, quarantine, unbind-hardware) additionally enforce product-owner access in AdvancedLicensingController::requireProductOwner (unknown product => 404). Reseller-wholesale has Sanctum only: the Resellers table has no developer/user mapping, so a finer ownership policy cannot be enforced without a schema/policy decision — logged as remaining, not invented.
- Security unbind scoped by serial: FraudAlertService::unbindHardware previously deleted HardwareBindings by hardwareDigest alone, unbinding the same device from every licence in the shard. Now it resolves the licence id for the serial and deletes only that licence's HardwareBindings row, and releases the matching MachineBinding (ReleasedAt = now) so seat counts stay consistent.
- Orphan product hazard: ProductService::createProduct now validates all nested deals before Product::create (validateNestedDeals, including duplicate dealCodes within the batch, via a probe product), creates product + deals inside a root-connection transaction, and only then provisions the shard. New test: product with one good + one invalid nested deal => 400 and no product row left behind.
- Cents-must-be-integer: validateDealNumbers now rejects floats/decimal strings for priceCents and allowedMachineSeats (previously 19.99 was silently cast to 19). New test: priceCents 19.99 => 400.
- Dedicated updateDeal/deleteDeal 404 tests: unknown deal id => 404 for both (behaviour already existed in the controller, now covered by a dedicated test).
- Endpoint inventory: the AiArchitectController and DeveloperOverviewController missing-test offenders (pre-existing) are now covered by a class_exists reference test in DeveloperPortalTest, so EndpointInventoryTest passes. FunctionBodyLineCapTest still fails only on the 3 pre-existing violations (DeveloperRegisterController::buildSuccessResponse 21, AdvancedLicensingController::extractClientInputs 32, ClientHandshakeService::bindMachineSeat 28); no baseline additions were made, and Phase 3 helpers (mintWebhookSerial, requireWebhookSignature, validateNestedDeals, licenseExpiresAt, countMachineSeats, etc.) are all at or under 15 lines.

#### Phase 3 test evidence
- Targeted suites, final run: DeveloperPortalTest 29 passed, EndUserVerificationTest 11 passed, PaymentWebhookTest 7 passed, PricingPlanCrudTest 6 passed, AdvancedEcosystemTest 4 passed — total 57 passed. Assertions: 385 in the 56-test run, 6813 in the inventory-inclusive run (inventory test alone asserts the full endpoint audit).
- Full backend Pest suite, final run: 528 passed, 26 failed, 13 skipped, 10114 assertions, ~34s. Phase 2 was 518 passed, 27 failed, 13 skipped. The +10 passes / -1 failure are: +9 new Phase 3 tests, plus EndpointInventoryTest now passing after the AiArchitect/DeveloperOverview controller references were added.
- The 26 failures are the same categories as phase 2: Postgres QueryException connection refused on 127.0.0.1:5432 for Admin/Auth/Reseller/Seed suites, plus architecture gates (function-length cap 3 pre-existing, TraceRedactor token-shape case). Endpoint inventory is no longer a failure. No Phase 3 regression was found: targeted suites that failed at the start of Phase 3 (because they relied on auto-register / unsigned webhookIssue) pass after their same-batch test updates.
- Environment: PHP was absent at Phase 3 start and was reinstalled via apt (php8.3 8.3.6, modules bcmath, curl, fileinfo, intl, libxml, mbstring, pdo_pgsql, pdo_sqlite, pgsql, sqlite3, tokenizer, xml, zip). Composer via backend/composer.phar with --ignore-platform-reqs, because the lock still requires PHP >=8.4.1 while composer.json says ^8.3 — same caveat as phase 2. backend/vendor and test-only backend/.env were verified present; neither is committed.
- Frontend: no frontend files changed, vitest not re-run (last pass phase 1: 145 files / 951 tests). Playwright: still blocked, browser-install user-confirmation gate not bypassed, no browsers in ~/.cache/ms-playwright.

#### Phase 3 commits pushed to muse
- 791e128 Phase 3: verify/activate split, licence expiry, webhookIssue HMAC, ownership and orphan fixes (15 files, 720 insertions, 94 deletions)
- (Report/confusion update commit to follow; origin/main unchanged at 413986c throughout.)

#### What remains after Phase 3 (with evidence)
1. Full-suite split is now measured: 528 passed, 26 failed, 13 skipped (see above). The remaining 26 failures are Postgres + architecture gates, not Phase 3 regressions.
2. Live Postgres test environment (127.0.0.1:5432) for Admin/Auth/Reseller/Seed suites; FunctionBodyLineCap 3 pre-existing violations; TraceRedactor token-shape case.
3. Reseller-wholesale finer ownership policy: needs a reseller-to-developer mapping (schema/policy decision) — Sanctum only for now.
4. webhookSecret retrieve/rotate endpoint and documentation for webhookIssue signers (secret is hidden in product serialization).
5. Blueprint persistence (confusion 19), admin developer product/deal management policy, frontend developer license management UI, frontend typecheck 44 pre-existing errors, Playwright browser install approval — all unchanged from phase 2, still open.
6. PHP 8.4 vs lock regeneration (confusion 14) — still open; Phase 3 used the --ignore-platform-reqs workaround on PHP 8.3.6.
