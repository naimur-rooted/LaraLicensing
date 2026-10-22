# Plan: Reseller Portal Architecture, Branded Minting & UI Enhancement

- **Slug:** `24-reseller-portal-comprehensive-architecture-and-ui`
- **Status:** Completed
- **Created:** 2026-10-22
- **Author:** Antigravity Architect
- **Target Modules:** Reseller Shards, Laravel Backend API, Reseller Frontend Studio, Quota Ledger, Machine Telemetry

---

## 1. Executive Summary & Problem Context

The current Reseller Portal contains baseline CRUD routes, but lacks:
1. **Branded Serial Minting Studio:** Resellers cannot configure vanity prefixes or visually preview customized serial key strings (`ALIM-M-V1-XXXX-XXXX-XXXX`) before minting.
2. **Interactive Quota Ledger & Self-Service Approvals:** Quota allowances lack visual transaction timelines, ledger history exploration, and instant quota request modal submission.
3. **Downstream Machine Telemetry & Troubleshooting:** Resellers cannot inspect hardware fingerprint hashes, see active vs released seats, or remotely release machine bindings when an end customer changes devices.
4. **Enhanced Cryptlex-Style UI:** The current reseller surface is constrained by narrow container widths and lacks high-density telemetry KPI cards, real-time quota gauges, and full-width navigation.

This plan details a 100-step execution roadmap grouped for autonomous agent pickup to deliver the complete, production-grade Reseller Portal.

---

## 2. 100-Step Grouped Execution Breakdown

### Group 1: Database Schema, Migrations & Split-DB Shards (Steps 1–15)
*Assigned Agent Role: Database & Storage Architect*

- [x] **Step 1:** Create migration for Reseller Prefixes table.
  - **File:** `backend/database/migrations/2026_10_22_000001_create_prefixes_table.php`
  - **Root Cause:** Resellers require validated vanity prefixes for branded serial generation.
  - **State Management:** Creates `Prefixes` table in `lara_root.sqlite` with `PrefixId`, `ResellerId`, `PrefixValue`, `IsActive`, `CreatedAt`.
- [x] **Step 2:** Add unique compound index on `Prefixes(PrefixValue)`.
  - **File:** `backend/database/migrations/2026_10_22_000001_create_prefixes_table.php`
  - **Root Cause:** Prevent collision of identical prefixes across different resellers.
  - **State Management:** Enforces uniqueness constraint at database level.
- [x] **Step 3:** Create migration for `ResellerQuotas` cache table in root database.
  - **File:** `backend/database/migrations/2026_10_22_000002_create_reseller_quotas_table.php`
  - **Root Cause:** Fast lookup of granted vs consumed license seats per reseller and tier.
  - **State Management:** Columns: `ResellerId`, `LicenseCategoryId`, `LicenseTierId`, `LicensesGranted`, `LicensesConsumed`, `PeriodStart`, `PeriodEnd`.
- [x] **Step 4:** Create migration for `ResellerQuotaLedger` append-only table.
  - **File:** `backend/database/migrations/2026_10_22_000003_create_reseller_quota_ledger_table.php`
  - **Root Cause:** Double-entry ledger invariant preventing quota tampering or silent quota loss.
  - **State Management:** Append-only columns: `Id`, `ResellerId`, `LicenseCategoryId`, `LicenseTierId`, `Delta`, `Action`, `ReferenceId`, `OccurredAt`.
- [x] **Step 5:** Create migration for `QuotaRequests` state machine table.
  - **File:** `backend/database/migrations/2026_10_22_000004_create_quota_requests_table.php`
  - **Root Cause:** Structured workflow for resellers to request license allowance deltas from Admin.
  - **State Management:** Columns: `Id`, `ResellerId`, `LicenseCategoryId`, `LicenseTierId`, `RequestedDelta`, `ApprovedDelta`, `Status`, `Justification`, `DenialReason`, `DecidedByUserId`.
- [x] **Step 6:** Update `MachineBindings` table schema in product/reseller shards.
  - **File:** `backend/database/migrations/2026_10_22_000005_update_machine_bindings_table.php`
  - **Root Cause:** Prohibit storing raw MAC addresses or motherboard serials; enforce SHA-256 `FingerprintHash`.
  - **State Management:** Columns: `MachineBindingId`, `LicenseId`, `FingerprintHash`, `FirstSeenAt`, `LastSeenAt`, `ReleasedAt`, `RebindCooldownUntil`.
- [x] **Step 7:** Add index on `MachineBindings(LicenseId, ReleasedAt)`.
  - **File:** `backend/database/migrations/2026_10_22_000005_update_machine_bindings_table.php`
  - **Root Cause:** Accelerate active machine seat count queries (`COUNT(*) WHERE ReleasedAt IS NULL`).
  - **State Management:** B-tree compound index in SQLite shard.
- [x] **Step 8:** Add index on `MachineBindings(FingerprintHash, RebindCooldownUntil)`.
  - **File:** `backend/database/migrations/2026_10_22_000005_update_machine_bindings_table.php`
  - **Root Cause:** Enable sub-millisecond check for 15-minute rebind cooldowns during activation.
  - **State Management:** Shard index.
- [x] **Step 9:** Create database seeder for initial reseller vanity prefixes.
  - **File:** `backend/database/seeders/ResellerPrefixSeeder.php`
  - **Root Cause:** Provide realistic seed fixtures (`ALIM`, `NEXUS`, `CORP`) for local dev and testing.
  - **State Management:** Populates `Prefixes` table for Seed Mode.
- [x] **Step 10:** Create database seeder for default reseller quota pools.
  - **File:** `backend/database/seeders/ResellerQuotaSeeder.php`
  - **Root Cause:** Ensure resellers have active quota allowances upon bootstrapping.
  - **State Management:** Seeds `ResellerQuotas` and initial `ResellerQuotaLedger` grant rows.
- [x] **Step 11:** Create database seeder for active machine bindings.
  - **File:** `backend/database/seeders/MachineBindingSeeder.php`
  - **Root Cause:** Provide hardware diagnostic test data with known SHA-256 hashes.
  - **State Management:** Seeds active and released bindings into reseller shards.
- [x] **Step 12:** Configure Split-DB tenant connection resolver for Reseller Shards.
  - **File:** `backend/app/Support/ShardManager.php`
  - **Root Cause:** Maintain strict physical SQLite file separation per reseller (`data/resellers/{slug}/app.db`).
  - **State Management:** Dynamically binds `'shard'` connection to specific file path.
- [x] **Step 13:** Add database health check for reseller shard integrity.
  - **File:** `backend/app/Support/ShardManager.php`
  - **Root Cause:** Prevent silent corruption or missing shard files on cold boots.
  - **State Management:** Validates SQLite WAL mode and file existence.
- [x] **Step 14:** Register reseller migrations in `DatabaseServiceProvider.php`.
  - **File:** `backend/app/Providers/DatabaseServiceProvider.php`
  - **Root Cause:** Ensure automated test harnesses and artisan commands execute reseller migrations.
  - **State Management:** Service container boot sequence.
- [x] **Step 15:** Verify migration execution across root and shard environments.
  - **File:** `backend/tests/Feature/ResellerDatabaseMigrationTest.php`
  - **Root Cause:** Automated assertion of schema integrity and column constraints.
  - **State Management:** Test runner state.

---

### Group 2: Backend Models, Services & Quota Ledger Engine (Steps 16–35)
*Assigned Agent Role: Backend Domain Engineer*

- [x] **Step 16:** Implement Eloquent model `Prefix.php`.
  - **File:** `backend/app/Models/Prefix.php`
  - **Root Cause:** ORM representation of reseller-owned vanity prefixes.
  - **State Management:** Casts `IsActive` to boolean; guards `PrefixId`.
- [x] **Step 17:** Implement Eloquent model `ResellerQuota.php`.
  - **File:** `backend/app/Models/ResellerQuota.php`
  - **Root Cause:** Entity managing current period seat balance.
  - **State Management:** Computes dynamic `licensesRemaining` attribute.
- [x] **Step 18:** Implement Eloquent model `ResellerQuotaLedger.php`.
  - **File:** `backend/app/Models/ResellerQuotaLedger.php`
  - **Root Cause:** Immutable log entity for double-entry tracking.
  - **State Management:** Read-only timestamps; prohibits in-place updates.
- [x] **Step 19:** Implement Eloquent model `QuotaRequest.php`.
  - **File:** `backend/app/Models/QuotaRequest.php`
  - **Root Cause:** Entity managing self-service quota increase approval state machine.
  - **State Management:** Casts `Status` to `QuotaRequestStatusType`.
- [x] **Step 20:** Implement Eloquent model `MachineBinding.php`.
  - **File:** `backend/app/Models/MachineBinding.php`
  - **Root Cause:** Entity tracking hardware seats linked to product licenses.
  - **State Management:** Scopes `active()` where `ReleasedAt IS NULL`.
- [x] **Step 21:** Implement `QuotaService.php` core transactional methods.
  - **File:** `backend/app/Services/QuotaService.php`
  - **Root Cause:** Centralized business logic for quota consumption, restoration, and adjustment.
  - **State Management:** Coordinates `ResellerQuotas` and `ResellerQuotaLedger` transactions.
- [x] **Step 22:** Implement `QuotaService::assertAndConsumeQuota()`.
  - **File:** `backend/app/Services/QuotaService.php`
  - **Root Cause:** Executes `SELECT ... FOR UPDATE` lock and assertions before issuing license.
  - **State Management:** Throws `DomainConflictException(QuotaExhausted)` on failure.
- [x] **Step 23:** Implement `QuotaService::restoreRevokedQuota()`.
  - **File:** `backend/app/Services/QuotaService.php`
  - **Root Cause:** Return license seat (+1) to reseller allowance if revoked within 24h grace period.
  - **State Management:** Atomically appends `Restored` action to ledger.
- [x] **Step 24:** Implement `QuotaService::applyAdminApproval()`.
  - **File:** `backend/app/Services/QuotaService.php`
  - **Root Cause:** Increase reseller allowance upon admin approving quota request.
  - **State Management:** Updates `LicensesGranted` and writes `Adjusted` ledger row.
- [x] **Step 25:** Implement `PrefixService.php` for prefix verification and claiming.
  - **File:** `backend/app/Services/PrefixService.php`
  - **Root Cause:** Manage reseller prefix registration, validation, and collision checks.
  - **State Management:** Manages `Prefixes` records in Root DB.
- [x] **Step 26:** Implement `PrefixService::assertOwnership()`.
  - **File:** `backend/app/Services/PrefixService.php`
  - **Root Cause:** Enforce that only the registered reseller can generate serials with a given prefix.
  - **State Management:** Throws `AuthException` if prefix belongs to another tenant.
- [x] **Step 27:** Implement `SerialGenerationService.php`.
  - **File:** `backend/app/Services/SerialGenerationService.php`
  - **Root Cause:** Generate cryptographically secure, formatted serials.
  - **State Management:** Combines prefix, category code, version, and RNG entropy.
- [x] **Step 28:** Add collision backoff retry loop in `SerialGenerationService`.
  - **File:** `backend/app/Services/SerialGenerationService.php`
  - **Root Cause:** Protect against duplicate serial generation on high-entropy RNG collisions.
  - **State Management:** Retries up to 5 times before emitting error.
- [x] **Step 29:** Implement `SerialMetadataParser.php`.
  - **File:** `backend/app/Services/SerialMetadataParser.php`
  - **Root Cause:** Extract category and version metadata from serial strings without DB lookup.
  - **State Management:** Returns structured DTO `{ prefix, category, version }`.
- [x] **Step 30:** Implement `MachineBindingService.php`.
  - **File:** `backend/app/Services/MachineBindingService.php`
  - **Root Cause:** Handle device seat binding, verification, unbinding, and cooldown checks.
  - **State Management:** Reads and writes to `MachineBindings` in shard DB.
- [x] **Step 31:** Implement `MachineBindingService::bindDevice()`.
  - **File:** `backend/app/Services/MachineBindingService.php`
  - **Root Cause:** Verify hardware hash and enforce `MaxConcurrentBindings` limit.
  - **State Management:** Inserts active binding or performs idempotent no-op refresh.
- [x] **Step 32:** Implement `MachineBindingService::releaseDevice()`.
  - **File:** `backend/app/Services/MachineBindingService.php`
  - **Root Cause:** Release hardware lock and apply 15-minute rebind cooldown.
  - **State Management:** Sets `ReleasedAt = NOW()` and `RebindCooldownUntil = NOW() + 15m`.
- [x] **Step 33:** Implement `MachineBindingService::checkCooldown()`.
  - **File:** `backend/app/Services/MachineBindingService.php`
  - **Root Cause:** Guard against seat-swapping abuse during active cooldown window.
  - **State Management:** Throws `DomainConflictException(MachineRebindCooldownActive)`.
- [x] **Step 34:** Implement `ResellerAnalyticsService.php`.
  - **File:** `backend/app/Services/ResellerAnalyticsService.php`
  - **Root Cause:** Compute genuine, live KPI metrics for reseller dashboard from SQLite shards.
  - **State Management:** Aggregates active licenses, quota utilization percentage, and machine activations.
- [x] **Step 35:** Unit test QuotaService concurrency and row locking.
  - **File:** `backend/tests/Unit/QuotaServiceTest.php`
  - **Root Cause:** Verify that race conditions cannot over-consume quota allowances.
  - **State Management:** Simulates parallel issuance requests.

---

### Group 3: Branded Serial Minting & Verification API Endpoints (Steps 36–50)
*Assigned Agent Role: API Architect*

- [x] **Step 36:** Implement `ResellerPrefixController::index()`.
  - **File:** `backend/app/Http/Controllers/Reseller/PrefixController.php`
  - **Root Cause:** List all active prefixes owned by the authenticated reseller.
  - **State Management:** Returns JSON array of prefix objects wrapped in `ApiEnvelope`.
- [x] **Step 37:** Implement `ResellerPrefixController::store()`.
  - **File:** `backend/app/Http/Controllers/Reseller/PrefixController.php`
  - **Root Cause:** Register a new vanity prefix for the reseller.
  - **State Management:** Validates format `^[A-Z0-9]{3,12}$`, persists, returns 201 Created.
- [x] **Step 38:** Implement `ResellerPrefixController::destroy()`.
  - **File:** `backend/app/Http/Controllers/Reseller/PrefixController.php`
  - **Root Cause:** Deactivate or release a prefix if unused.
  - **State Management:** Soft-deactivates prefix record.
- [x] **Step 39:** Implement `LicenseIssueRequest` validation rules.
  - **File:** `backend/app/Http/Requests/Reseller/LicenseIssueRequest.php`
  - **Root Cause:** Validate incoming payload for license minting (PrefixId, TierId, CategoryId).
  - **State Management:** Fails with 422 `ValidationFailed` on invalid inputs.
- [x] **Step 40:** Update `Reseller\LicenseController::store()`.
  - **File:** `backend/app/Http/Controllers/Reseller/LicenseController.php`
  - **Root Cause:** Execute end-to-end branded serial minting within transactional quota guard.
  - **State Management:** Calls `QuotaService`, `SerialGenerationService`, inserts `License` and `Serial`.
- [x] **Step 41:** Implement `Reseller\LicenseController::show()`.
  - **File:** `backend/app/Http/Controllers/Reseller/LicenseController.php`
  - **Root Cause:** Retrieve detailed license record including serial string and bound machines.
  - **State Management:** Scoped to caller's shard.
- [x] **Step 42:** Implement `Reseller\LicenseController::revoke()`.
  - **File:** `backend/app/Http/Controllers/Reseller/LicenseController.php`
  - **Root Cause:** Allow reseller to revoke a compromised or cancelled license.
  - **State Management:** Updates status to `Revoked`, triggers quota restore if within grace period.
- [x] **Step 43:** Implement `Reseller\LicenseController::renew()`.
  - **File:** `backend/app/Http/Controllers/Reseller/LicenseController.php`
  - **Root Cause:** Extend expiration date of an existing active license.
  - **State Management:** Appends `LicenseRenewed` to `LicenseLedger`.
- [x] **Step 44:** Update `SerialLookupController` for reseller scope.
  - **File:** `backend/app/Http/Controllers/Reseller/SerialLookupController.php`
  - **Root Cause:** Resellers require immediate serial lookup to verify validity and customer identity.
  - **State Management:** Returns serial details and active machine bindings.
- [x] **Step 45:** Register reseller prefix routes in `backend/routes/api.php`.
  - **File:** `backend/routes/api.php`
  - **Root Cause:** Expose REST endpoints under `/api/v1/reseller/prefixes`.
  - **State Management:** Route registration with `auth:sanctum` and `role:Reseller`.
- [x] **Step 46:** Register reseller license minting routes in `backend/routes/api.php`.
  - **File:** `backend/routes/api.php`
  - **Root Cause:** Expose `POST /api/v1/reseller/licenses`.
  - **State Management:** Route group wiring.
- [x] **Step 47:** Implement API test for branded serial format compliance.
  - **File:** `backend/tests/Feature/BrandedSerialGenerationTest.php`
  - **Root Cause:** Assert that minted serials match regex `^[A-Z0-9]{3,12}-[DWMYLXK]-V\d+-[A-Z0-9]{4}-...`.
  - **State Management:** Feature test assertions.
- [x] **Step 48:** Implement API test for quota exhaustion on minting.
  - **File:** `backend/tests/Feature/QuotaExhaustionTest.php`
  - **Root Cause:** Verify 409 `QuotaExhausted` response when allowance is zero.
  - **State Management:** Feature test assertions.
- [x] **Step 49:** Implement API test for foreign prefix rejection.
  - **File:** `backend/tests/Feature/PrefixAuthorizationTest.php`
  - **Root Cause:** Assert that Reseller 1 cannot use Reseller 2's prefix.
  - **State Management:** Feature test assertions.
- [x] **Step 50:** Implement API test for metadata reversibility.
  - **File:** `backend/tests/Feature/SerialMetadataReversibilityTest.php`
  - **Root Cause:** Verify parser reconstructs category and version accurately from raw serial.
  - **State Management:** Unit test assertions.

---

### Group 4: Downstream Machine Binding, Cooldown & Troubleshooting (Steps 51–65)
*Assigned Agent Role: Hardware Security Engineer*

- [x] **Step 51:** Implement `Reseller\MachineBindingController::index()`.
  - **File:** `backend/app/Http/Controllers/Reseller/MachineBindingController.php`
  - **Root Cause:** List all machines bound to a given license key for reseller troubleshooting.
  - **State Management:** Returns array of machine binding objects with status tags.
- [x] **Step 52:** Implement `Reseller\MachineBindingController::release()`.
  - **File:** `backend/app/Http/Controllers/Reseller/MachineBindingController.php`
  - **Root Cause:** Reseller remotely unbinds a customer machine when upgrading or repairing hardware.
  - **State Management:** Invokes `MachineBindingService::releaseDevice()`, sets cooldown.
- [x] **Step 53:** Implement `Reseller\MachineBindingController::clearCooldown()`.
  - **File:** `backend/app/Http/Controllers/Reseller/MachineBindingController.php`
  - **Root Cause:** Break-glass capability to reset cooldown if customer legitimately reinstalled OS.
  - **State Management:** Sets `RebindCooldownUntil = NULL` with audit log.
- [x] **Step 54:** Implement client verification endpoint update for hardware fingerprint hashing.
  - **File:** `backend/app/Http/Controllers/Api/VerifyController.php`
  - **Root Cause:** Guarantee server derives SHA-256 fingerprint if client sends raw parameters.
  - **State Management:** Normalizes and hashes hardware identifiers before database query.
- [x] **Step 55:** Enforce `MaxConcurrentBindings` seat checks during handshake.
  - **File:** `backend/app/Services/ClientHandshakeService.php`
  - **Root Cause:** Prevent unauthorized client seat sharing across multiple machines.
  - **State Management:** Checks active seat count vs license tier limit.
- [x] **Step 56:** Register machine binding routes in `backend/routes/api.php`.
  - **File:** `backend/routes/api.php`
  - **Root Cause:** Expose endpoints under `/api/v1/reseller/licenses/{key}/bindings`.
  - **State Management:** Route registration.
- [x] **Step 57:** Implement audit logging for reseller hardware unbind operations.
  - **File:** `backend/app/Support/AuditWriter.php`
  - **Root Cause:** Traceability for device release actions (`MachineUnbound`, `ReleaseReason = 'ResellerInitiated'`).
  - **State Management:** Inserts `AuditLogs` entry.
- [x] **Step 58:** Implement audit logging for cooldown override operations.
  - **File:** `backend/app/Support/AuditWriter.php`
  - **Root Cause:** Security record for break-glass actions (`CooldownCleared`).
  - **State Management:** Inserts `AuditLogs` entry.
- [x] **Step 59:** Feature test for 15-minute rebind cooldown enforcement.
  - **File:** `backend/tests/Feature/MachineBindingCooldownTest.php`
  - **Root Cause:** Verify client cannot immediately rebind same hardware within 15 minutes.
  - **State Management:** Test runner state.
- [x] **Step 60:** Feature test for reseller remote unbind workflow.
  - **File:** `backend/tests/Feature/ResellerMachineUnbindTest.php`
  - **Root Cause:** Assert that unbinding frees a license seat for another device.
  - **State Management:** Test runner state.
- [x] **Step 61:** Feature test for seat count limit rejection (`409 MachineQuotaExceeded`).
  - **File:** `backend/tests/Feature/MachineQuotaExceededCheckTest.php`
  - **Root Cause:** Verify overflow machine activation fails gracefully.
  - **State Management:** Test runner state.
- [x] **Step 62:** Feature test for idempotent re-verification on same machine.
  - **File:** `backend/tests/Feature/MachineReverifyNoOpTest.php`
  - **Root Cause:** Assert that second handshake updates `LastSeenAt` without incrementing seats.
  - **State Management:** Test runner state.
- [x] **Step 63:** Add telemetry metric tracking for machine binding outcomes.
  - **File:** `backend/app/Services/MachineBindingService.php`
  - **Root Cause:** Observability into hardware binding errors (`Bound`, `QuotaExceeded`, `CooldownActive`).
  - **State Management:** Increments Prometheus/internal counters.
- [x] **Step 64:** Implement diagnostic hardware fingerprint validator helper.
  - **File:** `backend/app/Support/FingerprintValidator.php`
  - **Root Cause:** Validate format of incoming 64-char lowercase hex SHA-256 strings.
  - **State Management:** Static validator helper.
- [x] **Step 65:** Add unit tests for `FingerprintValidator`.
  - **File:** `backend/tests/Unit/FingerprintValidatorTest.php`
  - **Root Cause:** Test regex adherence and rejection of corrupted hashes.
  - **State Management:** Test runner state.

---

### Group 5: Universal Error Logging, Error Taxonomy & API Envelopes (Steps 66–75)
*Assigned Agent Role: Reliability & Error Management Engineer*

- [x] **Step 66:** Verify Universal Response Envelope structure across all Reseller controllers.
  - **File:** `backend/app/Support/ApiEnvelope.php`
  - **Root Cause:** Ensure all reseller endpoints return `{ Status: { IsSuccess, Code, Message }, Attributes, Results }`.
  - **State Management:** Standardizes JSON output format.
- [x] **Step 67:** Audit `Reseller\LicenseController` for zero swallowed exceptions.
  - **File:** `backend/app/Http/Controllers/Reseller/LicenseController.php`
  - **Root Cause:** Enforce that all `try/catch` blocks log context and re-throw typed exceptions.
  - **State Management:** Code hygiene audit.
- [x] **Step 68:** Audit `Reseller\QuotaRequestController` for structured logging.
  - **File:** `backend/app/Http/Controllers/Reseller/QuotaRequestController.php`
  - **Root Cause:** Guarantee quota request errors emit `Log::error` with `resellerId` and payload.
  - **State Management:** Structured error logging.
- [x] **Step 69:** Audit `Reseller\MachineBindingController` for structured error reporting.
  - **File:** `backend/app/Http/Controllers/Reseller/MachineBindingController.php`
  - **Root Cause:** Ensure machine unbind failures carry `FingerprintHash` and `LicenseId` in logs.
  - **State Management:** Structured error logging.
- [x] **Step 70:** Add typed error codes for reseller domain in `ApiErrorCodeType.ts`.
  - **File:** `src/lib/lara-api-error.ts`
  - **Root Cause:** Synchronize backend exception codes with TypeScript frontend enums.
  - **State Management:** Adds `QuotaExhausted`, `PrefixNotOwned`, `MachineQuotaExceeded`, `CooldownActive`.
- [x] **Step 71:** Implement frontend error formatting helper for quota errors.
  - **File:** `src/lib/lara-quota-error.ts`
  - **Root Cause:** Present user-friendly error banners when quota is depleted.
  - **State Management:** Returns descriptive error strings for UI.
- [x] **Step 72:** Update global error toast hook for reseller mutations.
  - **File:** `src/hooks/use-lara-error-toast.ts`
  - **Root Cause:** Automatically catch API failures and trigger sonner toast notifications.
  - **State Management:** Toast state integration.
- [x] **Step 73:** Add automated test for API envelope compliance on reseller endpoints.
  - **File:** `backend/tests/Feature/ResellerApiEnvelopeComplianceTest.php`
  - **Root Cause:** Assert all reseller routes return valid envelope schema.
  - **State Management:** Test runner state.
- [x] **Step 74:** Implement automated linter check for swallowed errors in PHP controllers.
  - **File:** `linter-scripts/check-unhandled-exceptions.py`
  - **Root Cause:** Prohibit silent `catch (\Exception $e) {}` blocks in CI.
  - **State Management:** AST linter execution.
- [x] **Step 75:** Verify error code parity between PHP backend and TypeScript frontend.
  - **File:** `scripts/check-error-code-parity.mjs`
  - **Root Cause:** Detect drift between backend exceptions and frontend error handlers.
  - **State Management:** Static analysis script.

---

### Group 6: Reseller Portal UI & Full-Width Layout Enhancement (Steps 76–90)
*Assigned Agent Role: Frontend UI/UX Engineer*

- [x] **Step 76:** Overhaul `ResellerShell.tsx` for full-width fluid responsive layout.
  - **File:** `src/components/reseller/reseller-shell.tsx`
  - **Root Cause:** Eliminate `max-w-5xl` lock; expand layout to fluid container matching Cryptlex design.
  - **State Management:** Uses Tailwind `w-full px-6 lg:px-8`.
- [x] **Step 77:** Implement modern Reseller sidebar navigation with active indicator chips.
  - **File:** `src/components/reseller/ResellerSidebarNav.tsx`
  - **Root Cause:** Collapsible navigation with links to Overview, Minting Studio, Licenses, Quotas, and Devices.
  - **State Management:** Tracks current active route via TanStack Router state.
- [x] **Step 78:** Create `ResellerOverviewMetrics.tsx` KPI card grid.
  - **File:** `src/components/reseller/ResellerOverviewMetrics.tsx`
  - **Root Cause:** Display 4 primary KPIs: Total Active Licenses, Quota Allowance Remaining, Bound Hardware Seats, Pending Quota Requests.
  - **State Management:** Sourced from live React Query hook.
- [x] **Step 79:** Create `ResellerQuotaGauge.tsx` circular progress visualization.
  - **File:** `src/components/reseller/ResellerQuotaGauge.tsx`
  - **Root Cause:** Visual representation of consumed vs granted quota percentage.
  - **State Management:** Computes percentage from `LicensesConsumed / LicensesGranted`.
- [x] **Step 80:** Implement `ResellerSerialMintingStudio.tsx`.
  - **File:** `src/components/reseller/ResellerSerialMintingStudio.tsx`
  - **Root Cause:** Interactive workbench for selecting prefix, tier, category, and previewing generated serial format.
  - **State Management:** Local form state with live string preview.
- [x] **Step 81:** Add instant copy-to-clipboard button in Serial Minting Studio.
  - **File:** `src/components/reseller/ResellerSerialMintingStudio.tsx`
  - **Root Cause:** Convenience for resellers to copy newly minted serials directly.
  - **State Management:** Clipboard API with checkmark feedback state.
- [x] **Step 82:** Implement `ResellerLicensesDataTable.tsx` enhancements.
  - **File:** `src/components/reseller/ResellerLicensesDataTable.tsx`
  - **Root Cause:** Rich data table with search filter, status badges (`Active`, `Suspended`, `Revoked`), and pagination.
  - **State Management:** Integrated with `useSuspenseQuery` and sorting state.
- [x] **Step 83:** Add Quick Action dropdown in Licenses Data Table.
  - **File:** `src/components/reseller/ResellerLicensesDataTable.tsx`
  - **Root Cause:** Actions to Inspect Hardware, Extend Expiration (Renew), or Revoke License.
  - **State Management:** Action dialog triggering.
- [x] **Step 84:** Implement `ResellerQuotaManager.tsx` view.
  - **File:** `src/components/reseller/ResellerQuotaManager.tsx`
  - **Root Cause:** Display current tier allowances alongside historical `ResellerQuotaLedger` timeline.
  - **State Management:** Tabular ledger view.
- [x] **Step 85:** Implement `QuotaRequestModal.tsx` for self-service requests.
  - **File:** `src/components/reseller/QuotaRequestModal.tsx`
  - **Root Cause:** Modal dialog allowing resellers to enter requested seat delta and justification.
  - **State Management:** React Hook Form with Zod validation.
- [x] **Step 86:** Implement `MachineBindingTroubleshooter.tsx` component.
  - **File:** `src/components/reseller/MachineBindingTroubleshooter.tsx`
  - **Root Cause:** Dedicated troubleshooting studio to look up serials and inspect bound devices.
  - **State Management:** Queries bindings by serial or license key.
- [x] **Step 87:** Implement `MachineDeviceCard.tsx` for individual hardware display.
  - **File:** `src/components/reseller/MachineDeviceCard.tsx`
  - **Root Cause:** Displays fingerprint hash snippet, IP address, last-seen time, and active/cooldown status.
  - **State Management:** Renders device metadata and action button.
- [x] **Step 88:** Implement `ReleaseDeviceConfirmDialog.tsx`.
  - **File:** `src/components/reseller/ReleaseDeviceConfirmDialog.tsx`
  - **Root Cause:** Confirmation modal warning about 15-minute cooldown before unbinding device.
  - **State Management:** AlertDialog primitive with mutation execution.
- [x] **Step 89:** Add AI Reseller Copilot drawer integration.
  - **File:** `src/components/reseller/ResellerAiCopilot.tsx`
  - **Root Cause:** Provide intelligent troubleshooting guidance and serial formatting tips powered by Gemini.
  - **State Management:** Floating chat drawer state.
- [x] **Step 90:** Update `reseller.$resellerId.index.tsx` to mount enhanced components.
  - **File:** `src/routes/_authenticated/reseller.$resellerId.index.tsx`
  - **Root Cause:** Assemble overview dashboard, metrics cards, quota gauge, and quick links into primary hub.
  - **State Management:** Primary route component layout.

---

### Group 7: End-to-End Testing, Concurrency Verification & Documentation Sync (Steps 91–100)
*Assigned Agent Role: QA & Integration Engineer*

- [x] **Step 91:** Create Playwright E2E test for Reseller Dashboard navigation.
  - **File:** `tests/e2e/specs/reseller-portal-navigation.spec.ts`
  - **Root Cause:** Assert full-width layout, sidebar tab switching, and responsiveness across screen sizes.
  - **State Management:** Browser page assertions.
- [x] **Step 92:** Create Playwright E2E test for Branded Serial Minting workflow.
  - **File:** `tests/e2e/specs/reseller-serial-minting.spec.ts`
  - **Root Cause:** Verify selecting prefix, minting serial, decrementing quota gauge, and copying key.
  - **State Management:** End-to-end user journey.
- [x] **Step 93:** Create Playwright E2E test for Quota Request submission.
  - **File:** `tests/e2e/specs/reseller-quota-request.spec.ts`
  - **Root Cause:** Submit quota increase request, verify `Pending` badge in timeline.
  - **State Management:** User interaction simulation.
- [x] **Step 94:** Create Playwright E2E test for Machine Binding troubleshooting.
  - **File:** `tests/e2e/specs/reseller-machine-troubleshoot.spec.ts`
  - **Root Cause:** Search serial, observe bound machine, trigger release dialog, assert cooldown status.
  - **State Management:** Device release simulation.
- [x] **Step 95:** Implement concurrent quota depletion stress test in PHP.
  - **File:** `backend/tests/Feature/QuotaConcurrencyStressTest.php`
  - **Root Cause:** Stress-test row-level `FOR UPDATE` locks under 20 simulated concurrent minting requests.
  - **State Management:** Parallel PHP test processes.
- [x] **Step 96:** Verify zero hardcoded mock data across all Reseller frontend components.
  - **File:** `linter-scripts/check-hardcoded-mock-data.py`
  - **Root Cause:** Guarantee 100% of rendered data originates from real database shard APIs.
  - **State Management:** Static code inspection.
- [x] **Step 97:** Verify function length limits (max 15 lines) across all new Reseller files.
  - **File:** `linter-scripts/check-function-lengths.py`
  - **Root Cause:** Strict compliance with `.ai-memory/coding-guidelines.md` Rule 2.
  - **State Management:** AST line count validator.
- [x] **Step 98:** Verify positive boolean naming conventions across new TypeScript and PHP files.
  - **File:** `linter-scripts/check-boolean-prefixes.py`
  - **Root Cause:** Strict compliance with `is`/`has` positive prefix naming standard.
  - **State Management:** Lexical inspection.
- [x] **Step 99:** Update `.ai-memory/plans/index.md` with plan registration.
  - **File:** `.ai-memory/plans/index.md`
  - **Root Cause:** Maintain accurate index of all active and pending project plans.
  - **State Management:** Documentation index update.
- [x] **Step 100:** Run complete test suite and local CI runner verification.
  - **File:** `03-ai-scripts/06-cicd-local-runner.py`
  - **Root Cause:** Validate that all unit tests, linters, and typechecks pass with zero errors.
  - **State Management:** Comprehensive CI pass.
