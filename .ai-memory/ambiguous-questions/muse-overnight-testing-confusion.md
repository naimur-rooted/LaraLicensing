# AI Confusion Log — LaraLicensing overnight testing (Muse branch)

Purpose: log confusion instead of blocking overnight work. User will analyse later. Do not put credentials here.

## 1. Xmind not found
- Tried: searched chat attachments (none in this turn) and repo for `.xmind` files at shallow depth on 2026-10-28.
- Found: none.
- Needed later: confirm if an Xmind file should be uploaded, or if the written brief in the plan is the canonical workflow source.

## 2. Real payment gateway cannot be tested end-to-end
- Brief says LaraLicensing does not handle the developer's payment gateway.
- Gap: no developer gateway configuration was provided.
- Overnight approach: test the payment-confirmation trigger / key-generation path with a mocked confirmation. Real-gateway verification remains open.

## 3. AI blueprint, license-key format, and permission matrix unspecified in brief
- Brief describes what blueprint and keys should do, not their exact format.
- Overnight approach: locate existing implementations first. If implementation differs from brief, log the difference here, do not invent a new format without evidence.

## 4. Local run environment not yet verified
- Database, backend `.env`, frontend install tool, and PHP/Node versions are not yet confirmed on this machine.
- Overnight approach: record install/run blockers with exact commands and errors, rather than skipping a feature silently.

## 5. Git commit email not set
- Git user.name is set; commit email is not.
- Needed before first push to `muse`: choose GitHub noreply address or a user-provided email, and record the choice in the final report.

## 6. Branch name interpretation
- User said "create a different branch naming Muse branch".
- Interpretation used: branch name `muse`.
- If a different exact name was intended, rename is trivial and does not affect work on `muse` in the meantime.

## 7. Git commit email resolved in this run
- Resolved: git config user.email set locally in this repo to the GitHub noreply address 135898731+naimur-rooted@users.noreply.github.com, name Naimur Rahman Nahid.
- This resolves entry 5 for commits on muse. A user-provided email would replace it if the user prefers that later.

## 8. Tool instruction conflict in repo docs
- Found: a repo strictly-avoid file says to use one specific search and commit toolset exclusively, while the parent task for this run says do not use that toolset, it was not installed at task start, and to use normal git and grep.
- Approach used: normal git, grep, bun, and vitest, as instructed in the parent task. Repo docs were used as project reference only.
- Needed later: confirm which toolset should be canonical for future runs. A later memory note says that toolset was installed in another context, but that note did not change the task instruction for this run, so it was not adopted here.

## 9. Frontend typecheck baseline unclear
- Found: bun run typecheck uses tsgo, tsgo is not found here, exit 127. A fallback tsc --noEmit reports 44 error occurrences.
- Needed later: confirm whether tsgo reports the same errors, or whether the tsc list is a tool difference. Do not fix all 44 blindly until that baseline is confirmed.

## 10. Backend live testing blocked by missing PHP
- Found: php not found, composer not found, no backend/vendor, no backend/.env. Backend phpunit cannot bootstrap here.
- Needed later: a PHP 8.3 environment with composer install, plus a decision on whether local dev uses Postgres as in .env.example or SQLite as in phpunit.xml.

## 11. Blueprint, admin packages, and portal controls differ from brief
- Found by inspection: blueprint is on-demand only, not auto-generated on creation and not stored. No admin route for developer products, deals, or platform packages. Developer portal has delete only for licenses, no activate, deactivate, or unbind control, and no instant update.
- Needed later: decide whether the brief should change to on-demand blueprint, or whether creation should auto-generate and store a blueprint, and whether admin package management and portal controls should be added.

## 12. Flagged guideline file
- Found: a project guideline file read during learning did not pass a safety check in this run.
- Approach used: no extra steps from that file were followed, and it was not adopted as an overriding instruction set. Work continued from the parent task plan only.
- Needed later: no action, unless the parent agent directs otherwise.

## 13. tsgo baseline resolved in phase 2
- Resolved: `tsgo` package is 404 on npm; its successor is `@typescript/native-preview` (7.0.0-dev.20260707.2 via `bun x`, no repo files changed). It reports exactly 44 errors, same count as the tsc fallback, concentrated in preview-fixtures/stubs.ts (14) and other pre-existing files, none in phase 2 changed files.
- Decision: do not fix all 44 blindly in this phase; they are pre-existing and unrelated to security/validation batches. Needed later: a dedicated frontend typecheck fix batch, or a decision to accept the 44 as known debt.

## 14. Composer lock requires PHP 8.4, composer.json says ^8.3
- Found: composer install without flags fails because locked symfony 8.1 packages require php >=8.4.1, while composer.json requires php ^8.3 and the VM has PHP 8.3.6.
- Approach used: composer install with --ignore-platform-reqs, which produced a working vendor and passing Pest suites, but that pass must be reported with the caveat, not as a clean 8.4 run.
- Needed later: decide whether to install PHP 8.4 (via external PPA, not in Ubuntu noble) or regenerate the lock on 8.3, and whether CI uses 8.4.

## 15. Playwright browsers blocked by user confirmation
- Found: `npx playwright install chromium` entered pending_user_confirmation in this environment and was terminated, not bypassed. ~/.cache/ms-playwright remains absent.
- Needed later: an approved browser-install step, plus seeded backend + frontend + E2E_ADMIN_EMAIL/PASSWORD, before the 5 smoke specs can run.

## 16. webhookIssue HMAC gating is a breaking-change decision
- Found: webhookIssue is a simple trusted-caller endpoint (transactionId + customerEmail + dealCode, no HMAC). Gateway webhooks (Stripe/LemonSqueezy/WooCommerce) already have HMAC. Adding HMAC to webhookIssue would break existing tests and integrations.
- Approach used: deal validation + idempotent replay + email validation only, no HMAC. Needed later: user decision — gate webhookIssue with HMAC (breaking) or document it as trusted-caller only.

## 17. Verify read-only split is a client-migration decision
- Found: verify and activate both auto-register unknown serials and bind seats. Existing tests rely on this, and gateway serials live only in Serials until first verify, so auto-registration is the Serials -> ProductLicenses bridge.
- Approach used: seat-limit status unified to 409 only; auto-registration unchanged. Needed later: decide verify-read-only vs activate-binds, and how Serials should bridge, then update EndUserVerificationTest and AdvancedEcosystemTest in the same batch (activate first, then verify; unknown serial -> LicenseNotFound).

## 18. License expiry is a schema gap, not just a code fix
- Found: ProductLicenses has no expiry column, and no deal-interval -> expiry derivation exists. Adding expiry needs a migration plus shard-schema changes in ProductService and ClientHandshakeService, plus a rule for daily/monthly/yearly/biennial/lifetime.
- Approach used: logged, not implemented in this phase. Needed later: schema + derivation decision before any expiry check can be enforced in verify.

## 19. Blueprint persistence decision
- Found: Products has no blueprint column. Blueprint is now auto-generated on creation (returned in store response, same existing format, no Gemini required) and on demand (existing endpoint), but not persisted.
- Needed later: decide whether to add a Products blueprint column/migration, store it in a shard/file, or keep response + on-demand generation as the contract.

## 20. Full backend suite is not green, for pre-existing reasons
- Found: 27 failures in full Pest suite are Postgres connection refused (no Postgres on 127.0.0.1:5432) plus 3 pre-existing architecture tests (function-length cap with 3 untouched violations, endpoint inventory with pre-existing AiArchitect routes, TraceRedactor token-shape case).
- Approach used: fixed only the violations phase 2 introduced (cap helpers, controller references), did not add baseline entries, did not hide failures, and did not claim full-suite pass.
- Needed later: a Postgres test environment for the Seed/Admin/Auth/Reseller suites, plus fixes for the 3 pre-existing architecture failures, plus investigation of AdvancedEcosystem/DeveloperPortal test-order flakiness.
