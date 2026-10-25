<?php

declare(strict_types=1);

namespace Tests\Feature\Support;

use Illuminate\Support\Facades\DB;

/**
 * Shared seed helpers for Pest tests.
 * Ensures deterministic schema setup and seeding across test runs.
 */
trait SeedHelpers
{
    protected function seedWithProfile(string $profile = 'default'): void
    {
        $this->cleanShardFiles();
        $this->ensureSqliteFunctions('root');
        $this->ensureSqliteFunctions('shard');
        $this->createRootIdentitySchema();
        $this->createRootCatalogSchema();
        $this->createRootResellerSchema();
        $this->createRootProductSchema();
        $this->createRootAuxSchema();
        $this->resetShardTables();
        $this->createShardLicenseSchema();
        $this->createShardAuxSchema();
        $this->dispatchSeedProfile($profile);
    }

    private function cleanShardFiles(): void
    {
        $files = glob(database_path('lara_shard_*.sqlite'));
        if (is_array($files)) {
            foreach ($files as $file) {
                @unlink($file);
            }
        }
    }

    private function resetShardTables(): void
    {
        $shard = DB::connection('shard');
        if ($shard->getDriverName() !== 'sqlite') {
            return;
        }
        $tables = ['Licenses', 'Serials', 'Quotas', 'QuotaRequests', 'LicenseLedger'];
        foreach ($tables as $table) {
            $shard->statement("DROP TABLE IF EXISTS \"{$table}\"");
        }
    }

    private function ensureSqliteFunctions(string $connection): void
    {
        $conn = DB::connection($connection);
        if ($conn->getDriverName() !== 'sqlite') {
            return;
        }
        $pdo = $conn->getPdo();
        if (method_exists($pdo, 'sqliteCreateFunction')) {
            $pdo->sqliteCreateFunction('now', fn() => date('Y-m-d H:i:s'));
        }
    }

    private function dispatchSeedProfile(string $profile): void
    {
        $originalEnv = $_ENV['SEED_PROFILE'] ?? null;
        $originalServer = $_SERVER['SEED_PROFILE'] ?? null;
        putenv("SEED_PROFILE={$profile}");
        $_ENV['SEED_PROFILE'] = $profile;
        $_SERVER['SEED_PROFILE'] = $profile;
        $this->artisan('db:seed');
        $this->restoreEnv('SEED_PROFILE', $originalEnv, $originalServer);
    }

    private function restoreEnv(string $key, ?string $env, ?string $server): void
    {
        if ($env !== null) {
            putenv("{$key}={$env}");
            $_ENV[$key] = $env;
        } else {
            putenv($key);
            unset($_ENV[$key]);
        }
        if ($server !== null) {
            $_SERVER[$key] = $server;
        } else {
            unset($_SERVER[$key]);
        }
    }

    private function createRootIdentitySchema(): void
    {
        $root = DB::connection('root');
        if ($root->getDriverName() !== 'sqlite') {
            return;
        }
        $root->statement('CREATE TABLE IF NOT EXISTS "Roles" ("RoleId" INTEGER PRIMARY KEY AUTOINCREMENT, "RoleName" TEXT UNIQUE, "CreatedAt" TEXT, "UpdatedAt" TEXT)');
        $root->statement('CREATE TABLE IF NOT EXISTS "Users" ("UserId" INTEGER PRIMARY KEY AUTOINCREMENT, "Email" TEXT UNIQUE, "EmailLower" TEXT, "PasswordHash" TEXT, "TenantId" INTEGER, "IsActive" INTEGER DEFAULT 1, "CreatedAt" TEXT, "UpdatedAt" TEXT, "DeletedAt" TEXT)');
        $root->statement('CREATE TABLE IF NOT EXISTS "UserRoles" ("UserId" INTEGER, "RoleId" INTEGER, PRIMARY KEY ("UserId", "RoleId"))');
        $root->statement('CREATE TABLE IF NOT EXISTS "personal_access_tokens" ("id" INTEGER PRIMARY KEY AUTOINCREMENT, "tokenable_type" TEXT, "tokenable_id" INTEGER, "name" TEXT, "token" TEXT UNIQUE, "abilities" TEXT, "last_used_at" TEXT, "expires_at" TEXT, "created_at" TEXT, "updated_at" TEXT)');
        $root->statement('CREATE TABLE IF NOT EXISTS "AuthSessions" ("SessionId" TEXT PRIMARY KEY, "UserId" INTEGER, "Kind" TEXT NOT NULL DEFAULT \'Normal\', "ImpersonatorUserId" INTEGER, "ParentSessionId" TEXT, "EndedAt" TEXT, "RevokeReason" TEXT, "RefreshTokenHash" TEXT, "IpAddress" TEXT, "UserAgent" TEXT, "IsRevoked" INTEGER DEFAULT 0, "CreatedAt" TEXT, "ExpiresAt" TEXT, "RevokedAt" TEXT, "LastActivityAt" TEXT)');
        $root->statement('CREATE TABLE IF NOT EXISTS "ImpersonationIndex" ("SessionId" TEXT PRIMARY KEY, "ImpersonatorUserId" INTEGER, "TargetUserId" INTEGER, "TargetResellerId" INTEGER, "StartedAt" TEXT, "EndedAt" TEXT, "EndReason" TEXT)');
        $root->statement('CREATE TABLE IF NOT EXISTS "AuditLogs" ("AuditLogId" INTEGER PRIMARY KEY AUTOINCREMENT, "ActorType" TEXT, "ActorId" INTEGER, "Action" TEXT, "TargetType" TEXT, "TargetId" INTEGER, "RequestId" TEXT, "PayloadJson" TEXT, "CreatedAt" TEXT)');
    }

    private function createRootCatalogSchema(): void
    {
        $root = DB::connection('root');
        if ($root->getDriverName() !== 'sqlite') {
            return;
        }
        $root->statement('CREATE TABLE IF NOT EXISTS "Features" ("FeatureId" INTEGER PRIMARY KEY AUTOINCREMENT, "FeatureKey" TEXT UNIQUE, "ValueType" TEXT)');
        $root->statement('CREATE TABLE IF NOT EXISTS "TierFeatures" ("TierFeatureId" INTEGER PRIMARY KEY AUTOINCREMENT, "LicenseTierId" INTEGER, "FeatureId" INTEGER, "ValueScalar" TEXT)');
        $root->statement('CREATE TABLE IF NOT EXISTS "LicenseTiers" ("LicenseTierId" INTEGER PRIMARY KEY, "TierName" TEXT UNIQUE, "TierOrdinal" INTEGER)');
        $root->statement('CREATE TABLE IF NOT EXISTS "FeatureFlags" ("FlagId" INTEGER PRIMARY KEY AUTOINCREMENT, "FlagKey" TEXT UNIQUE, "IsEnabled" INTEGER DEFAULT 0, "Description" TEXT)');
        $root->statement('CREATE TABLE IF NOT EXISTS "GlobalSettings" ("SettingId" INTEGER PRIMARY KEY AUTOINCREMENT, "SettingKey" TEXT UNIQUE, "SettingValue" TEXT)');
        $root->statement('CREATE TABLE IF NOT EXISTS "ResellerSettings" ("SettingId" INTEGER PRIMARY KEY AUTOINCREMENT, "ResellerId" INTEGER, "SettingKey" TEXT, "SettingValue" TEXT)');
    }

    private function createRootResellerSchema(): void
    {
        $root = DB::connection('root');
        if ($root->getDriverName() !== 'sqlite') {
            return;
        }
        $root->statement('CREATE TABLE IF NOT EXISTS "Resellers" ("ResellerId" INTEGER PRIMARY KEY AUTOINCREMENT, "ResellerName" TEXT, "ResellerSlug" TEXT UNIQUE, "ContactEmail" TEXT, "IsActive" INTEGER DEFAULT 1, "CreatedAt" TEXT, "UpdatedAt" TEXT)');
        $root->statement('CREATE TABLE IF NOT EXISTS "ResellerShardRoutes" ("RouteId" INTEGER PRIMARY KEY AUTOINCREMENT, "ResellerId" INTEGER UNIQUE, "AppDbPath" TEXT, "ShardStatus" TEXT, "CreatedAt" TEXT, "UpdatedAt" TEXT)');
        $root->statement('CREATE TABLE IF NOT EXISTS "Prefixes" ("PrefixId" INTEGER PRIMARY KEY AUTOINCREMENT, "ResellerId" INTEGER, "PrefixValue" TEXT UNIQUE, "IsActive" INTEGER DEFAULT 1, "CreatedAt" TEXT)');
        $root->statement('CREATE TABLE IF NOT EXISTS "ResellerStaff" ("StaffId" INTEGER PRIMARY KEY AUTOINCREMENT, "ResellerId" INTEGER, "DisplayName" TEXT, "ContactEmail" TEXT, "IsActive" INTEGER DEFAULT 1)');
    }

    private function createRootProductSchema(): void
    {
        $root = DB::connection('root');
        if ($root->getDriverName() !== 'sqlite') {
            return;
        }
        $root->statement('CREATE TABLE IF NOT EXISTS "Products" ("ProductId" INTEGER PRIMARY KEY AUTOINCREMENT, "ProductCode" TEXT UNIQUE, "ProductName" TEXT, "productSlug" TEXT UNIQUE, "Name" TEXT, "Slug" TEXT, "Description" TEXT, "category" TEXT, "ed25519PublicKey" TEXT, "ed25519PrivateKey" TEXT, "webhookSecret" TEXT, "checkIntervalDays" INTEGER DEFAULT 7, "isActive" INTEGER DEFAULT 1, "created_at" TEXT, "updated_at" TEXT, "CreatedAt" TEXT, "UpdatedAt" TEXT)');
        $root->statement('CREATE TABLE IF NOT EXISTS "ProductDeals" ("DealId" INTEGER PRIMARY KEY AUTOINCREMENT, "ProductDealId" INTEGER, "ProductId" INTEGER, "productId" INTEGER, "dealCode" TEXT, "dealName" TEXT, "Name" TEXT, "billingInterval" TEXT, "priceCents" INTEGER DEFAULT 0, "Price" REAL DEFAULT 0, "allowedMachineSeats" INTEGER DEFAULT 1, "allowedDomains" INTEGER DEFAULT 1, "isDecoyTarget" INTEGER DEFAULT 0, "Features" TEXT, "features" TEXT, "isActive" INTEGER DEFAULT 1, "created_at" TEXT, "updated_at" TEXT, "CreatedAt" TEXT, "UpdatedAt" TEXT)');
        $root->statement('CREATE TABLE IF NOT EXISTS "PricingPlans" ("PlanId" INTEGER PRIMARY KEY AUTOINCREMENT, "Name" TEXT, "Slug" TEXT, "Price" REAL DEFAULT 0, "Features" TEXT, "CreatedAt" TEXT, "UpdatedAt" TEXT)');
    }

    private function createRootAuxSchema(): void
    {
        $root = DB::connection('root');
        if ($root->getDriverName() !== 'sqlite') {
            return;
        }
        $root->statement('CREATE TABLE IF NOT EXISTS "IdempotencyRecords" ("IdempotencyRecordId" INTEGER PRIMARY KEY AUTOINCREMENT, "Endpoint" TEXT NOT NULL, "ActorId" TEXT NOT NULL, "IdempotencyKey" TEXT NOT NULL, "BodyHash" TEXT NOT NULL, "ResponseStatus" INTEGER NOT NULL, "ResponseHeadersJson" TEXT NOT NULL, "ResponseBody" TEXT NOT NULL, "CreatedAt" TEXT DEFAULT CURRENT_TIMESTAMP, "ExpiresAt" TEXT NOT NULL, CONSTRAINT "UX_IdempotencyRecords_Scope" UNIQUE ("Endpoint", "ActorId", "IdempotencyKey"))');
        $root->statement('CREATE TABLE IF NOT EXISTS "AuditLogs" ("AuditLogId" INTEGER PRIMARY KEY AUTOINCREMENT, "ActorUserId" INTEGER, "Action" TEXT, "ResourceType" TEXT, "ResourceId" TEXT, "Details" TEXT, "CreatedAt" TEXT)');
        $root->statement('CREATE TABLE IF NOT EXISTS "UserProfiles" ("ProfileId" INTEGER PRIMARY KEY AUTOINCREMENT, "UserId" INTEGER UNIQUE, "FullName" TEXT, "PreferencesJson" TEXT)');
        $root->statement('CREATE TABLE IF NOT EXISTS "Notifications" ("NotificationId" INTEGER PRIMARY KEY AUTOINCREMENT, "UserId" INTEGER, "Title" TEXT, "Message" TEXT, "Severity" TEXT, "IsActive" INTEGER DEFAULT 1, "CreatedAt" TEXT)');
        $root->statement('CREATE TABLE IF NOT EXISTS "Webhooks" ("WebhookId" INTEGER PRIMARY KEY AUTOINCREMENT, "ResellerId" INTEGER, "Url" TEXT, "EventMask" TEXT, "Secret" TEXT, "IsActive" INTEGER DEFAULT 1)');
        $root->statement('CREATE TABLE IF NOT EXISTS "ApiKeys" ("ApiKeyId" INTEGER PRIMARY KEY AUTOINCREMENT, "ResellerId" INTEGER, "KeyId" TEXT, "KeyHash" TEXT, "Description" TEXT, "IsActive" INTEGER DEFAULT 1)');
    }

    private function createShardLicenseSchema(): void
    {
        $shard = DB::connection('shard');
        if ($shard->getDriverName() !== 'sqlite') {
            return;
        }
        $shard->statement('CREATE TABLE IF NOT EXISTS "Licenses" ("LicenseId" INTEGER PRIMARY KEY AUTOINCREMENT, "LicenseKey" TEXT UNIQUE, "PrefixValue" TEXT, "ResellerId" INTEGER, "IssuedByUserId" INTEGER, "Status" TEXT, "EnvironmentName" TEXT, "LicenseCategoryId" INTEGER, "LicenseTierId" INTEGER, "TierName" TEXT, "ProductVersion" TEXT, "ResellerQuotaLedgerId" INTEGER, "Version" INTEGER DEFAULT 1, "CreatedAt" TEXT, "UpdatedAt" TEXT)');
        $shard->statement('CREATE TABLE IF NOT EXISTS "Serials" ("SerialId" INTEGER PRIMARY KEY AUTOINCREMENT, "LicenseId" INTEGER, "SerialKey" TEXT UNIQUE, "Status" TEXT, "CreatedAt" TEXT)');
        $shard->statement('CREATE TABLE IF NOT EXISTS "Quotas" ("QuotaId" INTEGER PRIMARY KEY AUTOINCREMENT, "ResellerId" INTEGER, "LicenseCategoryId" INTEGER, "LicenseTierId" INTEGER, "LicensesGranted" INTEGER, "LicensesConsumed" INTEGER, "PeriodStart" TEXT, "PeriodEnd" TEXT)');
        $shard->statement('CREATE TABLE IF NOT EXISTS "QuotaRequests" ("QuotaRequestId" INTEGER PRIMARY KEY AUTOINCREMENT, "RequestId" INTEGER, "ResellerId" INTEGER, "LicenseCategoryId" INTEGER, "LicenseTierId" INTEGER, "RequestedDelta" INTEGER DEFAULT 100, "RequestedCount" INTEGER DEFAULT 100, "ApprovedDelta" INTEGER, "Status" INTEGER DEFAULT 1, "Justification" TEXT, "Reason" TEXT, "CreatedAt" DATETIME, "UpdatedAt" DATETIME)');
        $shard->statement('CREATE TABLE IF NOT EXISTS "LicenseLedger" ("LedgerId" INTEGER PRIMARY KEY AUTOINCREMENT, "ResellerId" INTEGER, "LicenseId" INTEGER, "LicenseCategoryId" INTEGER, "TierName" TEXT, "Action" TEXT, "Delta" INTEGER, "RequestId" TEXT, "ActorUserId" INTEGER, "CreatedAt" TEXT)');
        $shard->statement('CREATE TABLE IF NOT EXISTS "ResellerQuotaLedger" ("LedgerId" INTEGER PRIMARY KEY AUTOINCREMENT, "ResellerId" INTEGER, "Action" TEXT, "Delta" INTEGER, "BalanceAfter" INTEGER, "Reason" TEXT, "ReferenceKey" TEXT, "CreatedAt" TEXT)');
    }

    private function createShardAuxSchema(): void
    {
        $shard = DB::connection('shard');
        if ($shard->getDriverName() !== 'sqlite') {
            return;
        }
        $shard->statement('CREATE TABLE IF NOT EXISTS "MachineBindings" ("BindingId" INTEGER PRIMARY KEY AUTOINCREMENT, "LicenseKey" TEXT, "FingerprintHash" TEXT, "IpAddress" TEXT, "Hostname" TEXT, "FirstSeenAt" TEXT, "LastSeenAt" TEXT, "ReleasedAt" TEXT, "RebindCooldownUntil" TEXT)');
        $shard->statement('CREATE TABLE IF NOT EXISTS "UserBindings" ("BindingId" INTEGER PRIMARY KEY AUTOINCREMENT, "LicenseKey" TEXT, "UserEmail" TEXT, "BoundAt" TEXT)');
        $shard->statement('CREATE TABLE IF NOT EXISTS "LicenseFeatures" ("LicenseFeatureId" INTEGER PRIMARY KEY AUTOINCREMENT, "LicenseId" INTEGER, "FeatureId" INTEGER, "ValueScalar" TEXT)');
        $shard->statement('CREATE TABLE IF NOT EXISTS "VerifyKeys" ("VerifyKeyId" INTEGER PRIMARY KEY AUTOINCREMENT, "KeyType" TEXT, "PublicKeyPem" TEXT, "PrivateKeyPem" TEXT, "IsActive" INTEGER DEFAULT 1, "CreatedAt" TEXT)');
        $shard->statement('CREATE TABLE IF NOT EXISTS "AuditLogs" ("AuditLogId" INTEGER PRIMARY KEY AUTOINCREMENT, "ResellerId" INTEGER, "Action" TEXT, "Details" TEXT, "CreatedAt" TEXT)');
        $shard->statement('CREATE TABLE IF NOT EXISTS "DailyMetrics" ("MetricId" INTEGER PRIMARY KEY AUTOINCREMENT, "ResellerId" INTEGER, "MetricDate" TEXT, "RequestCount" INTEGER, "SuccessCount" INTEGER, "FailureCount" INTEGER)');
        $shard->statement('CREATE TABLE IF NOT EXISTS "ServiceUsage" ("UsageId" INTEGER PRIMARY KEY AUTOINCREMENT, "ResellerId" INTEGER, "ServiceName" TEXT, "UsageCount" INTEGER, "AvgLatencyMs" INTEGER, "RecordedAt" TEXT)');
        $shard->statement('CREATE TABLE IF NOT EXISTS "PlatformStats" ("StatId" INTEGER PRIMARY KEY AUTOINCREMENT, "ResellerId" INTEGER, "PlatformName" TEXT, "ActiveUsers" INTEGER, "CreatedAt" TEXT)');
        $shard->statement('CREATE TABLE IF NOT EXISTS "EnvironmentHealth" ("HealthId" INTEGER PRIMARY KEY AUTOINCREMENT, "ResellerId" INTEGER, "EnvironmentName" TEXT, "Status" TEXT, "LastCheckedAt" TEXT)');
    }
}
