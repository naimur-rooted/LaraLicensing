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
