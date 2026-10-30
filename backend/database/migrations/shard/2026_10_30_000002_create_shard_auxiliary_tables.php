<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Postgres shard parity for auxiliary tables.
 *
 * Root cause in one sentence: the Postgres shard migrations never
 * created the auxiliary tables that the sqlite shard schema creates
 * on bind, so seeders and metrics code that pass on sqlite fail on
 * Postgres with a missing relation.
 *
 * Tables mirror the sqlite definitions in App\Db\ShardResolver, with
 * Postgres types. Down() drops them in reverse order.
 */
return new class extends Migration
{
    private const CONN = 'shard';

    public function up(): void
    {
        $this->createDailyMetrics();
        $this->createShardAuditLogs();
        $this->createServiceUsage();
        $this->createPlatformStats();
        $this->createEnvironmentHealth();
    }

    public function down(): void
    {
        DB::connection(self::CONN)->statement('DROP TABLE IF EXISTS "EnvironmentHealth"');
        DB::connection(self::CONN)->statement('DROP TABLE IF EXISTS "PlatformStats"');
        DB::connection(self::CONN)->statement('DROP TABLE IF EXISTS "ServiceUsage"');
        DB::connection(self::CONN)->statement('DROP TABLE IF EXISTS "AuditLogs"');
        DB::connection(self::CONN)->statement('DROP TABLE IF EXISTS "DailyMetrics"');
    }

    private function createDailyMetrics(): void
    {
        DB::connection(self::CONN)->statement('
            CREATE TABLE IF NOT EXISTS "DailyMetrics" (
                "MetricId" BIGSERIAL PRIMARY KEY,
                "ResellerId" BIGINT NULL,
                "MetricDate" VARCHAR(10) NOT NULL,
                "RequestCount" INTEGER NOT NULL DEFAULT 0,
                "SuccessCount" INTEGER NOT NULL DEFAULT 0,
                "FailureCount" INTEGER NOT NULL DEFAULT 0
            )
        ');
    }

    private function createShardAuditLogs(): void
    {
        DB::connection(self::CONN)->statement('
            CREATE TABLE IF NOT EXISTS "AuditLogs" (
                "AuditLogId" BIGSERIAL PRIMARY KEY,
                "ResellerId" BIGINT NULL,
                "Action" VARCHAR(64) NOT NULL,
                "Details" TEXT NULL,
                "CreatedAt" TIMESTAMPTZ NULL
            )
        ');
    }

    private function createServiceUsage(): void
    {
        DB::connection(self::CONN)->statement('
            CREATE TABLE IF NOT EXISTS "ServiceUsage" (
                "UsageId" BIGSERIAL PRIMARY KEY,
                "ResellerId" BIGINT NULL,
                "ServiceName" VARCHAR(64) NOT NULL,
                "UsageCount" INTEGER NOT NULL DEFAULT 0,
                "AvgLatencyMs" INTEGER NOT NULL DEFAULT 0,
                "RecordedAt" TIMESTAMPTZ NULL
            )
        ');
    }

    private function createPlatformStats(): void
    {
        DB::connection(self::CONN)->statement('
            CREATE TABLE IF NOT EXISTS "PlatformStats" (
                "StatId" BIGSERIAL PRIMARY KEY,
                "ResellerId" BIGINT NULL,
                "PlatformName" VARCHAR(64) NOT NULL,
                "ActiveUsers" INTEGER NOT NULL DEFAULT 0,
                "CreatedAt" TIMESTAMPTZ NULL
            )
        ');
    }

    private function createEnvironmentHealth(): void
    {
        DB::connection(self::CONN)->statement('
            CREATE TABLE IF NOT EXISTS "EnvironmentHealth" (
                "HealthId" BIGSERIAL PRIMARY KEY,
                "ResellerId" BIGINT NULL,
                "EnvironmentName" VARCHAR(32) NOT NULL,
                "Status" VARCHAR(32) NOT NULL DEFAULT \'Healthy\',
                "LastCheckedAt" TIMESTAMPTZ NULL
            )
        ');
    }
};
