<?php

declare(strict_types=1);

namespace Tests\Support;

use App\Domain\BR\BrFlagId;
use App\Domain\BR\BrFlagValue;
use Illuminate\Support\Facades\DB;

trait SqliteTestSchema
{
    protected function ensureSqliteTestEnvironment(): void
    {
        $this->ensureSqliteFunctions('root');
        $this->ensureSqliteFunctions('shard');
        $this->createRootSqliteTables();
        $this->createShardSqliteTables();
        $this->seedRootDefaults();
    }

    private function ensureSqliteFunctions(string $connection): void
    {
        $conn = DB::connection($connection);
        if ($conn->getDriverName() !== 'sqlite') {
            return;
        }
        $pdo = $conn->getPdo();
        if (!method_exists($pdo, 'sqliteCreateFunction')) {
            return;
        }
        $pdo->sqliteCreateFunction('now', fn () => date('Y-m-d H:i:s'));
        $pdo->sqliteCreateFunction('NOW', fn () => date('Y-m-d H:i:s'));
        $pdo->sqliteCreateFunction('pg_advisory_xact_lock', fn ($key = null) => 1);
        $pdo->sqliteCreateFunction('hashtext', fn ($str = '') => crc32((string) $str));
        $pdo->sqliteCreateFunction('digest', fn ($data = '', $algo = 'sha256') => hash('sha256', (string) $data, true));
        $pdo->sqliteCreateFunction('decode', fn ($str = '', $type = 'hex') => hex2bin((string) $str));
        $pdo->sqliteCreateFunction('encode', fn ($bytes = '', $type = 'hex') => bin2hex((string) $bytes));
        $pdo->sqliteCreateFunction('pg_terminate_backend', fn ($pid = 0) => 1);
    }

    private function createRootSqliteTables(): void
    {
        $root = DB::connection('root');
        if ($root->getDriverName() !== 'sqlite') {
            return;
        }

        $root->statement('CREATE TABLE IF NOT EXISTS "Roles" ("RoleId" INTEGER PRIMARY KEY AUTOINCREMENT, "RoleName" TEXT UNIQUE, "CreatedAt" TEXT, "UpdatedAt" TEXT)');
        $root->statement('CREATE TABLE IF NOT EXISTS "Users" ("UserId" INTEGER PRIMARY KEY AUTOINCREMENT, "Email" TEXT UNIQUE, "EmailLower" TEXT, "PasswordHash" TEXT, "TenantId" INTEGER, "IsActive" INTEGER DEFAULT 1, "CreatedAt" TEXT, "UpdatedAt" TEXT, "DeletedAt" TEXT)');
        $root->statement('CREATE TABLE IF NOT EXISTS "UserRoles" ("UserRoleId" INTEGER PRIMARY KEY AUTOINCREMENT, "UserId" INTEGER, "RoleId" INTEGER, "CreatedAt" TEXT, UNIQUE ("UserId", "RoleId"))');
        $root->statement('CREATE TABLE IF NOT EXISTS "personal_access_tokens" ("id" INTEGER PRIMARY KEY AUTOINCREMENT, "tokenable_type" TEXT, "tokenable_id" INTEGER, "name" TEXT, "token" TEXT UNIQUE, "abilities" TEXT, "last_used_at" TEXT, "expires_at" TEXT, "created_at" TEXT, "updated_at" TEXT)');
        $root->statement('CREATE TABLE IF NOT EXISTS "AuthSessions" ("SessionId" TEXT PRIMARY KEY, "UserId" INTEGER, "Kind" TEXT NOT NULL DEFAULT \'Normal\', "ImpersonatorUserId" INTEGER, "ParentSessionId" TEXT, "EndedAt" TEXT, "RevokeReason" TEXT, "RefreshTokenHash" TEXT, "IpAddress" TEXT, "UserAgent" TEXT, "IsRevoked" INTEGER DEFAULT 0, "CreatedAt" TEXT, "ExpiresAt" TEXT, "RevokedAt" TEXT, "LastActivityAt" TEXT)');
        $root->statement('CREATE TABLE IF NOT EXISTS "ImpersonationIndex" ("SessionId" TEXT PRIMARY KEY, "ImpersonatorUserId" INTEGER, "TargetUserId" INTEGER, "TargetResellerId" INTEGER, "ExpiresAt" TEXT, "StartedAt" TEXT, "EndedAt" TEXT, "EndReason" TEXT)');
        try { $root->statement('ALTER TABLE "ImpersonationIndex" ADD COLUMN "ExpiresAt" TEXT'); } catch (\Throwable) {}
        $root->statement('CREATE TABLE IF NOT EXISTS "AuditLogs" ("AuditLogId" INTEGER PRIMARY KEY AUTOINCREMENT, "ActorType" TEXT, "ActorId" INTEGER, "ActorUserId" INTEGER, "Action" TEXT, "TargetType" TEXT, "TargetId" INTEGER, "SubjectId" INTEGER GENERATED ALWAYS AS (CAST("TargetId" AS INTEGER)) STORED, "ResourceType" TEXT, "ResourceId" TEXT, "RequestId" TEXT, "PayloadJson" TEXT, "Details" TEXT, "CreatedAt" TEXT)');
        try { $root->statement('ALTER TABLE "AuditLogs" ADD COLUMN "SubjectId" INTEGER'); } catch (\Throwable) {}

        $root->statement('CREATE TABLE IF NOT EXISTS "Products" ("ProductId" INTEGER PRIMARY KEY AUTOINCREMENT, "userId" INTEGER DEFAULT 1, "productSlug" TEXT UNIQUE, "ProductCode" TEXT UNIQUE, "ProductName" TEXT, "category" TEXT DEFAULT "DesktopApp", "Description" TEXT, "ed25519PublicKey" TEXT, "ed25519PrivateKey" TEXT, "webhookSecret" TEXT, "webhookUrl" TEXT, "checkIntervalDays" INTEGER DEFAULT 7, "isActive" INTEGER DEFAULT 1, "created_at" TEXT, "updated_at" TEXT, "CreatedAt" TEXT, "UpdatedAt" TEXT)');
        $root->statement('CREATE TABLE IF NOT EXISTS "ProductDeals" ("ProductDealId" INTEGER PRIMARY KEY AUTOINCREMENT, "productId" INTEGER, "dealName" TEXT, "dealCode" TEXT, "billingInterval" TEXT DEFAULT "monthly", "priceCents" INTEGER DEFAULT 0, "allowedMachineSeats" INTEGER DEFAULT 1, "allowedDomains" INTEGER DEFAULT 1, "isDecoyTarget" INTEGER DEFAULT 0, "features" TEXT, "isActive" INTEGER DEFAULT 1, "created_at" TEXT, "updated_at" TEXT, "CreatedAt" TEXT, "UpdatedAt" TEXT)');
        try { $root->statement('CREATE UNIQUE INDEX IF NOT EXISTS "UX_Products_ProductCode" ON "Products" ("ProductCode")'); } catch (\Throwable) {}
        $root->statement('CREATE TABLE IF NOT EXISTS "Resellers" ("ResellerId" INTEGER PRIMARY KEY AUTOINCREMENT, "ResellerName" TEXT, "ResellerSlug" TEXT UNIQUE, "ContactEmail" TEXT, "IsActive" INTEGER DEFAULT 1, "CreatedAt" TEXT, "UpdatedAt" TEXT, "DeletedAt" TEXT)');
        $root->statement('CREATE TABLE IF NOT EXISTS "ResellerShardRoutes" ("ResellerShardRouteId" INTEGER PRIMARY KEY AUTOINCREMENT, "RouteId" INTEGER, "ResellerId" INTEGER UNIQUE, "AppDbPath" TEXT, "ShardStatus" TEXT, "CreatedAt" TEXT, "UpdatedAt" TEXT)');
        $root->statement('CREATE TABLE IF NOT EXISTS "Prefixes" ("PrefixId" INTEGER PRIMARY KEY AUTOINCREMENT, "ResellerId" INTEGER, "PrefixValue" TEXT UNIQUE, "IsActive" INTEGER DEFAULT 1, "CreatedAt" TEXT, "UpdatedAt" TEXT)');
        $root->statement('CREATE TABLE IF NOT EXISTS "ResellerStaff" ("StaffId" INTEGER PRIMARY KEY AUTOINCREMENT, "ResellerId" INTEGER, "DisplayName" TEXT, "ContactEmail" TEXT, "IsActive" INTEGER DEFAULT 1)');
        $root->statement('CREATE TABLE IF NOT EXISTS "ResellerQuotas" ("Id" INTEGER PRIMARY KEY AUTOINCREMENT, "ResellerId" INTEGER NOT NULL, "LicenseCategoryId" INTEGER DEFAULT 3, "LicenseTierId" INTEGER DEFAULT 1, "LicensesGranted" INTEGER DEFAULT 500, "LicensesConsumed" INTEGER DEFAULT 0, "PeriodStart" TEXT, "PeriodEnd" TEXT, "CreatedAt" TEXT, "UpdatedAt" TEXT)');
        $root->statement('CREATE TABLE IF NOT EXISTS "ResellerQuotaLedger" ("Id" INTEGER PRIMARY KEY AUTOINCREMENT, "ResellerId" INTEGER NOT NULL, "LicenseCategoryId" INTEGER, "LicenseTierId" INTEGER, "TierName" TEXT, "Delta" INTEGER, "Action" TEXT, "LedgerAction" TEXT, "ReferenceId" TEXT, "LicenseId" INTEGER, "OccurredAt" TEXT, "CreatedAt" TEXT, "UpdatedAt" TEXT)');

        $root->statement('CREATE TABLE IF NOT EXISTS "Features" ("FeatureId" INTEGER PRIMARY KEY AUTOINCREMENT, "FeatureKey" TEXT UNIQUE, "Slug" TEXT, "ValueType" TEXT, "Description" TEXT, "IsActive" INTEGER DEFAULT 1, "CreatedAt" TEXT, "UpdatedAt" TEXT)');
        $root->statement('CREATE TABLE IF NOT EXISTS "TierFeatures" ("TierFeatureId" INTEGER PRIMARY KEY AUTOINCREMENT, "LicenseTierId" INTEGER, "FeatureId" INTEGER, "Value" TEXT, "ValueScalar" TEXT, "CreatedByUserId" INTEGER, "CreatedAt" TEXT, "UpdatedAt" TEXT)');
        $root->statement('CREATE TABLE IF NOT EXISTS "LicenseTiers" ("LicenseTierId" INTEGER PRIMARY KEY, "TierName" TEXT UNIQUE, "TierOrdinal" INTEGER, "CreatedAt" TEXT, "UpdatedAt" TEXT)');
        $root->statement('CREATE TABLE IF NOT EXISTS "FeatureFlags" ("FeatureFlagId" INTEGER PRIMARY KEY AUTOINCREMENT, "FlagId" TEXT NOT NULL UNIQUE, "Value" TEXT NOT NULL, "FlagKey" TEXT, "IsEnabled" INTEGER DEFAULT 0, "Description" TEXT)');
        $root->statement('CREATE TABLE IF NOT EXISTS "GlobalSettings" ("SettingId" INTEGER PRIMARY KEY AUTOINCREMENT, "SettingKey" TEXT UNIQUE, "SettingValue" TEXT)');
        $root->statement('CREATE TABLE IF NOT EXISTS "ResellerSettings" ("SettingId" INTEGER PRIMARY KEY AUTOINCREMENT, "ResellerId" INTEGER, "SettingKey" TEXT, "SettingValue" TEXT)');

        $root->statement('CREATE TABLE IF NOT EXISTS "Products" ("ProductId" INTEGER PRIMARY KEY AUTOINCREMENT, "userId" INTEGER, "ProductCode" TEXT, "ProductName" TEXT, "productSlug" TEXT UNIQUE, "Name" TEXT, "Slug" TEXT, "Description" TEXT, "category" TEXT DEFAULT \'DesktopApp\', "ed25519PublicKey" TEXT, "ed25519PrivateKey" TEXT, "webhookSecret" TEXT, "webhookUrl" TEXT, "checkIntervalDays" INTEGER DEFAULT 7, "isActive" INTEGER DEFAULT 1, "created_at" TEXT, "updated_at" TEXT, "CreatedAt" TEXT, "UpdatedAt" TEXT)');
        $root->statement('CREATE TABLE IF NOT EXISTS "ProductDeals" ("ProductDealId" INTEGER PRIMARY KEY AUTOINCREMENT, "DealId" INTEGER, "ProductId" INTEGER, "dealCode" TEXT, "dealName" TEXT, "Name" TEXT, "billingInterval" TEXT DEFAULT \'monthly\', "priceCents" INTEGER DEFAULT 0, "Price" REAL DEFAULT 0, "allowedMachineSeats" INTEGER DEFAULT 1, "allowedDomains" INTEGER DEFAULT 1, "isDecoyTarget" INTEGER DEFAULT 0, "isActive" INTEGER DEFAULT 1, "Features" TEXT, "created_at" TEXT, "updated_at" TEXT, "CreatedAt" TEXT, "UpdatedAt" TEXT)');
        $root->statement('CREATE TABLE IF NOT EXISTS "PricingPlans" ("PricingPlanId" INTEGER PRIMARY KEY AUTOINCREMENT, "PlanId" INTEGER, "planCode" TEXT UNIQUE, "planName" TEXT, "billingInterval" TEXT, "retailPriceCents" INTEGER DEFAULT 0, "discountedPriceCents" INTEGER DEFAULT 0, "allowedMachineSlots" INTEGER DEFAULT 1, "isBestValue" INTEGER DEFAULT 0, "isActive" INTEGER DEFAULT 1, "Name" TEXT, "Slug" TEXT, "Price" REAL DEFAULT 0, "Features" TEXT, "created_at" TEXT, "updated_at" TEXT, "CreatedAt" TEXT, "UpdatedAt" TEXT)');
        $root->statement('CREATE TABLE IF NOT EXISTS "PricingPlanPriceVersions" ("PricingPlanPriceVersionId" INTEGER PRIMARY KEY AUTOINCREMENT, "PricingPlanId" INTEGER, "versionNumber" INTEGER, "planCode" TEXT, "planName" TEXT, "billingInterval" TEXT, "retailPriceCents" INTEGER DEFAULT 0, "discountedPriceCents" INTEGER DEFAULT 0, "allowedMachineSlots" INTEGER DEFAULT 1, "isBestValue" INTEGER DEFAULT 0, "isActive" INTEGER DEFAULT 1, "effectiveFrom" TEXT, "created_at" TEXT, "updated_at" TEXT, UNIQUE ("PricingPlanId", "versionNumber"))');

        $root->statement('CREATE TABLE IF NOT EXISTS "IdempotencyRecords" ("IdempotencyRecordId" INTEGER PRIMARY KEY AUTOINCREMENT, "Endpoint" TEXT NOT NULL, "ActorId" TEXT NOT NULL, "IdempotencyKey" TEXT NOT NULL, "BodyHash" TEXT NOT NULL, "ResponseStatus" INTEGER NOT NULL, "ResponseHeadersJson" TEXT NOT NULL, "ResponseBody" TEXT NOT NULL, "CreatedAt" TEXT DEFAULT CURRENT_TIMESTAMP, "UpdatedAt" TEXT DEFAULT CURRENT_TIMESTAMP, "ExpiresAt" TEXT NOT NULL, CONSTRAINT "UX_IdempotencyRecords_Scope" UNIQUE ("Endpoint", "ActorId", "IdempotencyKey"))');
        $root->statement('CREATE TABLE IF NOT EXISTS "UserProfiles" ("ProfileId" INTEGER PRIMARY KEY AUTOINCREMENT, "UserId" INTEGER UNIQUE, "FullName" TEXT, "PreferencesJson" TEXT)');
        $root->statement('CREATE TABLE IF NOT EXISTS "Notifications" ("NotificationId" INTEGER PRIMARY KEY AUTOINCREMENT, "UserId" INTEGER, "Title" TEXT, "Message" TEXT, "Severity" TEXT, "IsActive" INTEGER DEFAULT 1, "CreatedAt" TEXT)');
        $root->statement('CREATE TABLE IF NOT EXISTS "Webhooks" ("WebhookId" INTEGER PRIMARY KEY AUTOINCREMENT, "ResellerId" INTEGER, "Url" TEXT, "EventMask" TEXT, "Secret" TEXT, "IsActive" INTEGER DEFAULT 1)');
        $root->statement('CREATE TABLE IF NOT EXISTS "ApiKeys" ("ApiKeyId" INTEGER PRIMARY KEY AUTOINCREMENT, "ResellerId" INTEGER, "KeyId" TEXT, "KeyHash" TEXT, "Description" TEXT, "IsActive" INTEGER DEFAULT 1)');

        $root->statement('CREATE TABLE IF NOT EXISTS "BrKekEpochs" ("Epoch" INTEGER PRIMARY KEY, "Kid" TEXT NOT NULL, "State" TEXT NOT NULL DEFAULT \'Pending\', "CreatedAt" TEXT, "UpdatedAt" TEXT, "ActivatedAt" TEXT, "RetiredAt" TEXT, "SecretsRef" TEXT, "Notes" TEXT)');
        $root->statement('CREATE TABLE IF NOT EXISTS "BackupAuditEvents" ("BackupAuditEventId" TEXT PRIMARY KEY, "OccurredAt" TEXT, "Code" TEXT NOT NULL, "ActorKind" TEXT, "ActorUserId" TEXT, "RequestId" TEXT, "Payload" TEXT, "PrevHash" BLOB, "RowHash" BLOB, "ShardId" TEXT, "SchemaVersion" INTEGER DEFAULT 1, "JobId" TEXT, "ErrorId" TEXT, "SnapshotId" TEXT, "PolicyVersion" TEXT)');
        $root->statement('CREATE TABLE IF NOT EXISTS "BackupJobs" ("BackupJobId" TEXT PRIMARY KEY, "Id" TEXT, "Kind" TEXT, "State" TEXT, "ProgressPct" INTEGER DEFAULT 0, "ActorUserId" INTEGER, "Payload" TEXT, "PayloadJson" TEXT, "ErrorJson" TEXT, "Result" TEXT, "RequestId" TEXT, "AttemptCount" INTEGER DEFAULT 0, "MaxAttempts" INTEGER DEFAULT 3, "CreatedAt" TEXT, "UpdatedAt" TEXT, "ExpiresAt" TEXT, "CompletedAt" TEXT, "ArchiveId" TEXT, "Shadow" INTEGER DEFAULT 0, "WorkerLeaseUntil" TEXT, "StartedAt" TEXT, "FinishedAt" TEXT, "ErrorId" TEXT, "ErrorCode" TEXT, "ErrorReason" TEXT)');
        $root->statement('CREATE TABLE IF NOT EXISTS "BackupSnapshots" ("SnapshotId" TEXT PRIMARY KEY, "ShardId" TEXT, "Status" TEXT, "PinCount" INTEGER DEFAULT 0, "CreatedAt" TEXT, "UpdatedAt" TEXT)');
        $root->statement('CREATE TABLE IF NOT EXISTS "CasbinRules" ("id" INTEGER PRIMARY KEY AUTOINCREMENT, "Ptype" TEXT, "V0" TEXT, "V1" TEXT, "V2" TEXT, "V3" TEXT, "V4" TEXT, "V5" TEXT)');
        $root->statement('CREATE TABLE IF NOT EXISTS "AppUpdates" ("AppUpdateId" INTEGER PRIMARY KEY AUTOINCREMENT, "Product" TEXT, "Channel" TEXT NOT NULL DEFAULT \'Stable\', "Version" TEXT NOT NULL, "MinRequiredVersion" TEXT, "ReleaseNotesUrl" TEXT, "PublishedAt" TEXT, "PublishedByUserId" INTEGER, "IsPublished" INTEGER DEFAULT 0, "IsFinalized" INTEGER DEFAULT 0, "IsYanked" INTEGER DEFAULT 0, "YankedAt" TEXT, "YankedByUserId" INTEGER, "YankReason" TEXT, "CreatedAt" TEXT, "UpdatedAt" TEXT)');
        $root->statement('CREATE TABLE IF NOT EXISTS "AppUpdateAssets" ("AppUpdateAssetId" INTEGER PRIMARY KEY AUTOINCREMENT, "AppUpdateId" INTEGER, "Product" TEXT, "Version" TEXT, "Platform" TEXT, "Architecture" TEXT, "AssetKind" TEXT, "RelativePath" TEXT, "StoragePath" TEXT, "SignatureStoragePath" TEXT, "FileSize" INTEGER, "SizeBytes" INTEGER, "Sha256" TEXT, "Signature" TEXT, "SignatureSha256" TEXT, "UploadToken" TEXT, "UploadTicketExpiresAt" TEXT, "IsFinalized" INTEGER DEFAULT 0, "FinalizedAt" TEXT, "CreatedAt" TEXT, "UpdatedAt" TEXT)');
        $root->statement('CREATE TABLE IF NOT EXISTS "pg_locks" ("locktype" TEXT, "classid" INTEGER, "objid" INTEGER, "pid" INTEGER, "mode" TEXT, "granted" INTEGER DEFAULT 1)');
        $root->statement('CREATE TABLE IF NOT EXISTS "migrations" ("id" INTEGER PRIMARY KEY AUTOINCREMENT, "migration" TEXT NOT NULL, "batch" INTEGER NOT NULL)');
    }

    private function createShardSqliteTables(): void
    {
        $shard = DB::connection('shard');
        if ($shard->getDriverName() !== 'sqlite') {
            return;
        }

        $shard->statement('CREATE TABLE IF NOT EXISTS "Licenses" ("LicenseId" INTEGER PRIMARY KEY AUTOINCREMENT, "LicenseKey" TEXT UNIQUE, "PrefixValue" TEXT, "ResellerId" INTEGER, "IssuedByUserId" INTEGER, "Status" TEXT DEFAULT \'Active\', "EnvironmentName" TEXT DEFAULT \'Production\', "LicenseCategoryId" INTEGER, "LicenseTierId" INTEGER, "TierName" TEXT, "ProductVersion" TEXT DEFAULT \'V1\', "IssuerActorType" TEXT DEFAULT \'Admin\', "RevokedAt" TEXT, "RevokedByUserId" INTEGER, "RevokeReason" TEXT, "ResellerQuotaLedgerId" INTEGER, "IssuedAt" TEXT, "ExpiresAt" TEXT, "Version" INTEGER DEFAULT 1, "CreatedAt" TEXT, "UpdatedAt" TEXT, "DeletedAt" TEXT)');
        $shard->statement('CREATE TABLE IF NOT EXISTS "Serials" ("SerialId" INTEGER PRIMARY KEY AUTOINCREMENT, "LicenseId" INTEGER, "SerialKey" TEXT UNIQUE, "Status" TEXT, "CreatedAt" TEXT)');
        $shard->statement('CREATE TABLE IF NOT EXISTS "Quotas" ("QuotaId" INTEGER PRIMARY KEY AUTOINCREMENT, "ResellerId" INTEGER, "LicenseCategoryId" INTEGER, "LicenseTierId" INTEGER, "LicensesGranted" INTEGER, "LicensesConsumed" INTEGER, "PeriodStart" TEXT, "PeriodEnd" TEXT, "CreatedAt" TEXT, "UpdatedAt" TEXT)');
        $shard->statement('CREATE TABLE IF NOT EXISTS "QuotaRequests" ("QuotaRequestId" INTEGER PRIMARY KEY AUTOINCREMENT, "RequestId" INTEGER, "ResellerId" INTEGER, "LicenseCategoryId" INTEGER, "LicenseTierId" INTEGER, "RequestedDelta" INTEGER DEFAULT 100, "RequestedCount" INTEGER DEFAULT 100, "ApprovedDelta" INTEGER, "Status" INTEGER DEFAULT 1, "Justification" TEXT, "Reason" TEXT, "CreatedAt" DATETIME, "UpdatedAt" DATETIME)');
        $shard->statement('CREATE TABLE IF NOT EXISTS "LicenseLedger" ("LicenseLedgerId" INTEGER PRIMARY KEY AUTOINCREMENT, "LedgerId" INTEGER, "ResellerId" INTEGER, "LicenseId" INTEGER, "LicenseCategoryId" INTEGER, "TierName" TEXT, "Action" TEXT, "LedgerAction" TEXT, "Delta" INTEGER, "QuotaRequestId" INTEGER, "RequestId" TEXT, "ActorUserId" INTEGER, "CreatedAt" TEXT)');
        $shard->statement('CREATE UNIQUE INDEX IF NOT EXISTS "UX_LicenseLedger_RestoreOnce" ON "LicenseLedger" ("LicenseId") WHERE "LedgerAction" = \'QuotaRestored\'');
        $shard->statement('CREATE TABLE IF NOT EXISTS "ResellerQuotaLedger" ("LedgerId" INTEGER PRIMARY KEY AUTOINCREMENT, "ResellerId" INTEGER, "Action" TEXT, "Delta" INTEGER, "BalanceAfter" INTEGER, "Reason" TEXT, "ReferenceKey" TEXT, "CreatedAt" TEXT)');

        $shard->statement('CREATE TABLE IF NOT EXISTS "MachineBindings" ("MachineBindingId" INTEGER PRIMARY KEY AUTOINCREMENT, "BindingId" INTEGER, "LicenseId" INTEGER, "LicenseKey" TEXT, "FingerprintHash" TEXT, "IpAddress" TEXT, "Hostname" TEXT, "FirstSeenAt" TEXT, "LastSeenAt" TEXT, "ReleasedAt" TEXT, "RebindCooldownUntil" TEXT, "CreatedAt" TEXT, "UpdatedAt" TEXT)');
        $shard->statement('CREATE TABLE IF NOT EXISTS "UserBindings" ("UserBindingId" INTEGER PRIMARY KEY AUTOINCREMENT, "BindingId" INTEGER, "LicenseId" INTEGER, "LicenseKey" TEXT, "UserIdentifier" TEXT, "UserEmail" TEXT, "FirstSeenAt" TEXT, "LastSeenAt" TEXT, "IsReleased" INTEGER DEFAULT 0, "ReleasedAt" TEXT, "BoundAt" TEXT, "CreatedAt" TEXT, "UpdatedAt" TEXT)');
        $shard->statement('CREATE TABLE IF NOT EXISTS "LicenseFeatures" ("LicenseFeatureId" INTEGER PRIMARY KEY AUTOINCREMENT, "LicenseId" INTEGER, "FeatureId" INTEGER, "ValueScalar" TEXT)');
        $shard->statement('CREATE TABLE IF NOT EXISTS "VerifyKeys" ("VerifyKeyId" INTEGER PRIMARY KEY AUTOINCREMENT, "KeyType" TEXT, "PublicKeyPem" TEXT, "PrivateKeyPem" TEXT, "IsActive" INTEGER DEFAULT 1, "CreatedAt" TEXT)');
        $shard->statement('CREATE TABLE IF NOT EXISTS "AuditLogs" ("AuditLogId" INTEGER PRIMARY KEY AUTOINCREMENT, "ResellerId" INTEGER, "Action" TEXT, "Details" TEXT, "CreatedAt" TEXT)');
        $shard->statement('CREATE TABLE IF NOT EXISTS "DailyMetrics" ("MetricId" INTEGER PRIMARY KEY AUTOINCREMENT, "ResellerId" INTEGER, "MetricDate" TEXT, "RequestCount" INTEGER, "SuccessCount" INTEGER, "FailureCount" INTEGER)');
        $shard->statement('CREATE TABLE IF NOT EXISTS "ServiceUsage" ("UsageId" INTEGER PRIMARY KEY AUTOINCREMENT, "ResellerId" INTEGER, "ServiceName" TEXT, "UsageCount" INTEGER, "AvgLatencyMs" INTEGER, "RecordedAt" TEXT)');
        $shard->statement('CREATE TABLE IF NOT EXISTS "PlatformStats" ("StatId" INTEGER PRIMARY KEY AUTOINCREMENT, "ResellerId" INTEGER, "PlatformName" TEXT, "ActiveUsers" INTEGER, "CreatedAt" TEXT)');
        $shard->statement('CREATE TABLE IF NOT EXISTS "EnvironmentHealth" ("HealthId" INTEGER PRIMARY KEY AUTOINCREMENT, "ResellerId" INTEGER, "EnvironmentName" TEXT, "Status" TEXT, "LastCheckedAt" TEXT)');
    }

    private function seedRootDefaults(): void
    {
        $root = DB::connection('root');
        if ($root->getDriverName() !== 'sqlite') {
            return;
        }

        $roles = [
            ['RoleId' => 1, 'RoleName' => 'SuperAdmin'],
            ['RoleId' => 2, 'RoleName' => 'Admin'],
            ['RoleId' => 3, 'RoleName' => 'Reseller'],
            ['RoleId' => 4, 'RoleName' => 'AppBuilder'],
            ['RoleId' => 5, 'RoleName' => 'EndUser'],
            ['RoleId' => 6, 'RoleName' => 'Viewer'],
        ];
        foreach ($roles as $r) {
            $root->table('Roles')->updateOrInsert(['RoleId' => $r['RoleId']], $r);
        }

        $tiers = [
            ['LicenseTierId' => 1, 'TierName' => 'Tier1', 'TierOrdinal' => 1],
            ['LicenseTierId' => 2, 'TierName' => 'Tier2', 'TierOrdinal' => 2],
            ['LicenseTierId' => 3, 'TierName' => 'Tier3', 'TierOrdinal' => 3],
            ['LicenseTierId' => 4, 'TierName' => 'Unlimited', 'TierOrdinal' => 4],
        ];
        foreach ($tiers as $t) {
            $root->table('LicenseTiers')->updateOrInsert(['LicenseTierId' => $t['LicenseTierId']], $t);
        }

        $registry = config('lara.feature_registry', []);
        if (is_array($registry) && $registry !== []) {
            $fid = 1;
            foreach ($registry as $fkey => $fmeta) {
                $root->table('Features')->updateOrInsert(
                    ['FeatureKey' => $fkey],
                    [
                        'FeatureId' => $fid++,
                        'ValueType' => is_array($fmeta) ? ($fmeta['ValueType'] ?? 'Boolean') : 'Boolean',
                        'CreatedAt' => date('Y-m-d H:i:s'),
                        'UpdatedAt' => date('Y-m-d H:i:s'),
                    ]
                );
            }
        }

        foreach (BrFlagId::cases() as $flag) {
            $root->table('FeatureFlags')->updateOrInsert(
                ['FlagId' => $flag->value],
                ['Value' => BrFlagValue::Off->value, 'FlagKey' => $flag->value, 'IsEnabled' => 0]
            );
        }
        $casbinRows = [
            ['Ptype' => 'p', 'V0' => 'super_admin', 'V1' => '/Api/V1/Admin/*', 'V2' => '.*', 'V3' => 'allow'],
            ['Ptype' => 'p', 'V0' => 'admin', 'V1' => '/Api/V1/Admin/Backup/Export', 'V2' => 'POST', 'V3' => 'allow'],
            ['Ptype' => 'p', 'V0' => 'admin', 'V1' => '/Api/V1/Admin/Backup/Export/*', 'V2' => 'GET', 'V3' => 'allow'],
            ['Ptype' => 'p', 'V0' => 'admin', 'V1' => '/Api/V1/Admin/Backup/Import', 'V2' => 'POST', 'V3' => 'allow'],
            ['Ptype' => 'p', 'V0' => 'admin', 'V1' => '/Api/V1/Admin/Backup/Import/*', 'V2' => 'GET', 'V3' => 'allow'],
            ['Ptype' => 'p', 'V0' => 'admin', 'V1' => '/Api/V1/Admin/Snapshot', 'V2' => '(GET|POST)', 'V3' => 'allow'],
            ['Ptype' => 'p', 'V0' => 'admin', 'V1' => '/Api/V1/Admin/Snapshot/*', 'V2' => '(GET|DELETE)', 'V3' => 'allow'],
            ['Ptype' => 'p', 'V0' => 'admin', 'V1' => '/Api/V1/Admin/Snapshot/*/Pin', 'V2' => 'POST', 'V3' => 'allow'],
            ['Ptype' => 'p', 'V0' => 'admin', 'V1' => '/Api/V1/Admin/Snapshot/*/Restore', 'V2' => 'POST', 'V3' => 'allow'],
            ['Ptype' => 'p', 'V0' => 'admin', 'V1' => '/Api/V1/Admin/Roles*', 'V2' => 'GET', 'V3' => 'allow'],
            ['Ptype' => 'p', 'V0' => 'admin', 'V1' => '/Api/V1/Admin/System/Config', 'V2' => 'GET', 'V3' => 'allow'],
            ['Ptype' => 'p', 'V0' => 'admin', 'V1' => '/Api/V1/Admin/System/Health', 'V2' => 'GET', 'V3' => 'allow'],
            ['Ptype' => 'p', 'V0' => 'admin', 'V1' => '/Api/V1/Admin/Audit*', 'V2' => 'GET', 'V3' => 'allow'],
            ['Ptype' => 'p', 'V0' => 'operator', 'V1' => '/Api/V1/Admin/Backup/Export', 'V2' => 'POST', 'V3' => 'allow'],
            ['Ptype' => 'p', 'V0' => 'operator', 'V1' => '/Api/V1/Admin/Backup/Export/*', 'V2' => 'GET', 'V3' => 'allow'],
            ['Ptype' => 'p', 'V0' => 'operator', 'V1' => '/Api/V1/Admin/Backup/Import/*', 'V2' => 'GET', 'V3' => 'allow'],
            ['Ptype' => 'p', 'V0' => 'operator', 'V1' => '/Api/V1/Admin/Snapshot', 'V2' => '(GET|POST)', 'V3' => 'allow'],
            ['Ptype' => 'p', 'V0' => 'operator', 'V1' => '/Api/V1/Admin/Snapshot/*', 'V2' => 'GET', 'V3' => 'allow'],
            ['Ptype' => 'p', 'V0' => 'operator', 'V1' => '/Api/V1/Admin/Snapshot/*/Pin', 'V2' => 'POST', 'V3' => 'allow'],
            ['Ptype' => 'p', 'V0' => 'operator', 'V1' => '/Api/V1/Admin/System/Config', 'V2' => 'GET', 'V3' => 'allow'],
            ['Ptype' => 'p', 'V0' => 'operator', 'V1' => '/Api/V1/Admin/System/Health', 'V2' => 'GET', 'V3' => 'allow'],
            ['Ptype' => 'p', 'V0' => 'auditor', 'V1' => '/Api/V1/Admin/Backup/Export/*', 'V2' => 'GET', 'V3' => 'allow'],
            ['Ptype' => 'p', 'V0' => 'auditor', 'V1' => '/Api/V1/Admin/Backup/Import/*', 'V2' => 'GET', 'V3' => 'allow'],
            ['Ptype' => 'p', 'V0' => 'auditor', 'V1' => '/Api/V1/Admin/Snapshot', 'V2' => 'GET', 'V3' => 'allow'],
            ['Ptype' => 'p', 'V0' => 'auditor', 'V1' => '/Api/V1/Admin/Snapshot/*', 'V2' => 'GET', 'V3' => 'allow'],
            ['Ptype' => 'p', 'V0' => 'auditor', 'V1' => '/Api/V1/Admin/Roles*', 'V2' => 'GET', 'V3' => 'allow'],
            ['Ptype' => 'p', 'V0' => 'auditor', 'V1' => '/Api/V1/Admin/System/Config', 'V2' => 'GET', 'V3' => 'allow'],
            ['Ptype' => 'p', 'V0' => 'auditor', 'V1' => '/Api/V1/Admin/System/Health', 'V2' => 'GET', 'V3' => 'allow'],
            ['Ptype' => 'p', 'V0' => 'auditor', 'V1' => '/Api/V1/Admin/Audit*', 'V2' => 'GET', 'V3' => 'allow'],
            ['Ptype' => 'p', 'V0' => 'deputy', 'V1' => '/Api/V1/Admin/Backup/Import', 'V2' => '.*', 'V3' => 'deny'],
            ['Ptype' => 'p', 'V0' => 'deputy', 'V1' => '/Api/V1/Admin/Snapshot/*/Restore', 'V2' => '.*', 'V3' => 'deny'],
            ['Ptype' => 'p', 'V0' => 'super_admin', 'V1' => 'Capability:*', 'V2' => '.*', 'V3' => 'allow'],
            ['Ptype' => 'p', 'V0' => 'admin', 'V1' => 'Capability:Backup.Export', 'V2' => '.*', 'V3' => 'allow'],
            ['Ptype' => 'p', 'V0' => 'admin', 'V1' => 'Capability:Backup.Import', 'V2' => '.*', 'V3' => 'allow'],
            ['Ptype' => 'p', 'V0' => 'admin', 'V1' => 'Capability:Snapshot.*', 'V2' => '.*', 'V3' => 'allow'],
            ['Ptype' => 'p', 'V0' => 'admin', 'V1' => 'Capability:System.Read', 'V2' => '.*', 'V3' => 'allow'],
            ['Ptype' => 'p', 'V0' => 'admin', 'V1' => 'Capability:Audit.Read', 'V2' => '.*', 'V3' => 'allow'],
            ['Ptype' => 'p', 'V0' => 'operator', 'V1' => 'Capability:Backup.Export', 'V2' => '.*', 'V3' => 'allow'],
            ['Ptype' => 'p', 'V0' => 'operator', 'V1' => 'Capability:Snapshot.Create', 'V2' => '.*', 'V3' => 'allow'],
            ['Ptype' => 'p', 'V0' => 'operator', 'V1' => 'Capability:Snapshot.Pin', 'V2' => '.*', 'V3' => 'allow'],
            ['Ptype' => 'p', 'V0' => 'auditor', 'V1' => 'Capability:Snapshot.Read', 'V2' => '.*', 'V3' => 'allow'],
            ['Ptype' => 'p', 'V0' => 'auditor', 'V1' => 'Capability:Audit.Read', 'V2' => '.*', 'V3' => 'allow'],
            ['Ptype' => 'p', 'V0' => 'auditor', 'V1' => 'Capability:Role.Read', 'V2' => '.*', 'V3' => 'allow'],
            ['Ptype' => 'p', 'V0' => 'deputy', 'V1' => 'Capability:Backup.Import', 'V2' => '.*', 'V3' => 'deny'],
            ['Ptype' => 'p', 'V0' => 'deputy', 'V1' => 'Capability:Snapshot.Restore', 'V2' => '.*', 'V3' => 'deny'],
        ];
        if ($root->table('CasbinRules')->count() === 0) {
            $root->table('CasbinRules')->insert($casbinRows);
        }
    }
}
