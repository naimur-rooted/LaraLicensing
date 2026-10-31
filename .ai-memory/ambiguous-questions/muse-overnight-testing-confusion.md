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

## 11 & 19 (resolved). Blueprint generation and persistence contract
- Resolved: On-demand dynamic generation is the permanent canonical contract. Products model synthesizes the AI integration blueprint on the fly from current cryptographic keys, endpoints, and pricing deals. No stale blueprint column or file sync required.

## 14 (resolved). Composer platform requirements and PHP parity
- Resolved: Production/local runs on PHP 8.2/8.3. Lockfile platform constraints are handled via `--ignore-platform-reqs`, and core test suites run consistently.

## 15 (resolved). Playwright Chromium browser binaries
- Resolved: Playwright Chromium browser (v1248), FFmpeg (v1013), and headless shell successfully downloaded and installed to `%LOCALAPPDATA%\ms-playwright`. End-to-end browser smoke test suites are fully unblocked.

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

## 16 (resolved in Phase 3). webhookIssue HMAC gating is a breaking-change decision
- Resolved: user decided 1A on 2026-10-28 08:21 +06. webhookIssue now requires HMAC-SHA256 (X-Webhook-Signature / X-Signature) keyed by product webhookSecret, 401 on missing/bad signature, no unsigned fallback. All 7 existing webhookIssue test calls were updated to sign in the same batch, plus unsigned/bad-signature tests. Original entry above is kept unchanged.

## 17 (resolved in Phase 3). Verify read-only split is a client-migration decision
- Resolved: user decided 2A on 2026-10-28 08:21 +06, with gateway dual-write first. Gateway serials now dual-write to Serials AND ProductLicenses (deal, seats, email, event id, expiry, idempotent on replay). Verify is read-only (unknown => LicenseNotFound 404, no auto-register, no binding); activate is bind-only. EndUserVerificationTest, AdvancedEcosystemTest and DeveloperPortalTest were updated in the same batches (pre-seeded fixtures, bare verify creates no binding). Original entry above is kept unchanged.

## 18 (resolved in Phase 3). License expiry is a schema gap, not just a code fix
- Resolved: user decided 3A on 2026-10-28 08:21 +06. ProductLicenses gained expiresAt (plus customerEmail, transactionId) via LicenseShardSchema with CREATE for new shards and ALTER for old shards. Derivation: daily +1d, weekly +7d, monthly +1mo, quarterly +3mo, yearly +1y, biennial +2y, lifetime null. Verify and activate reject expired licences with LicenseExpired 403. Tests cover every interval, lifetime null, and expired rejection. Original entry above is kept unchanged.

## 21. Phase 4 backfill + JIT — implemented, with logged fallbacks
- Implemented: LicenseBackfillService + lara:licenses:backfill command + JIT promotion in verify/activate only. Unknown keys still 404 with no row created. Revoked keys promote as revoked, not active. repodb/ is not a live read path; backfill defaults to database_path/, --include-repodb optional.
- Remaining: deactivate/heartbeat do not JIT-promote (out of scope); fallback keys (DEFAULT deal, null expiry) are logged per key but have no admin UI surfacing.

## 22. Phase 4 Sanctum mint — implemented, secret not exposed
- Implemented: POST /api/v1/developer/products/{productSlug}/licenses/mint in the Sanctum developer group, owner-checked, writes directly to ProductLicenses + Serials, new serial per call, response isSimulated true (endpoint-derived, not a stored column).
- Remaining: no stored simulated marker column — tx_mock_ pattern is the marker; a caller supplying a non-tx_mock_ transactionId is indistinguishable from a gateway licence. Mint checks deal existence only, not isActive. Both are follow-up schema/policy decisions, deliberately not assumed.

## 23. Phase 4 coordinator refactor — function-length cap restored
- Worker backfill service initially introduced 3 new FunctionBodyLineCap violations (doPromote, backfillShard, derive). Coordinator refactored into helpers with no baseline additions; after refactor the test fails only on the 3 pre-existing violations, same as Phase 3.
