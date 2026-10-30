<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Postgres shard parity with the sqlite/app schema.
 *
 * Root cause in one sentence: the Postgres shard schema diverged from
 * the schema the app and tests were written against, so code that
 * passes on sqlite fails on Postgres.
 *
 * Gaps closed here:
 *   - MachineBindings lacked IpAddress and Hostname, which the
 *     reseller binding controller reads and tests insert.
 *   - Serials required DeviceIdHash and IdempotencyKey even for
 *     reseller-minted serials that are not yet device-bound, while
 *     the sqlite schema allows null and Portal issuance still
 *     validates DeviceIdHash at the app layer.
 *
 * Serials loosening is forward-only: down() drops the MachineBindings
 * columns but does not re-impose NOT NULL on Serials, since rows
 * minted while relaxed may legitimately hold nulls.
 */
return new class extends Migration
{
    private const CONN = 'shard';

    public function up(): void
    {
        $this->addMachineBindingColumns();
        $this->relaxSerialColumns();
    }

    public function down(): void
    {
        DB::connection(self::CONN)->statement('ALTER TABLE "MachineBindings" DROP COLUMN IF EXISTS "IpAddress"');
        DB::connection(self::CONN)->statement('ALTER TABLE "MachineBindings" DROP COLUMN IF EXISTS "Hostname"');
    }

    private function addMachineBindingColumns(): void
    {
        DB::connection(self::CONN)->statement('ALTER TABLE "MachineBindings" ADD COLUMN IF NOT EXISTS "IpAddress" VARCHAR(45) NULL');
        DB::connection(self::CONN)->statement('ALTER TABLE "MachineBindings" ADD COLUMN IF NOT EXISTS "Hostname" VARCHAR(128) NULL');
    }

    private function relaxSerialColumns(): void
    {
        DB::connection(self::CONN)->statement('ALTER TABLE "Serials" ALTER COLUMN "DeviceIdHash" DROP NOT NULL');
        DB::connection(self::CONN)->statement('ALTER TABLE "Serials" ALTER COLUMN "IdempotencyKey" DROP NOT NULL');
    }
};
