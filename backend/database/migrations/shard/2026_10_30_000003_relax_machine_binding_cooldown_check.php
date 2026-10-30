<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * MachineBindings cooldown check parity.
 *
 * Root cause in one sentence: the Postgres CHECK required a released
 * binding to always carry a cooldown, but clear-cooldown legitimately
 * leaves a binding released with no cooldown so it can be rebound
 * immediately, a state the sqlite schema and the controller allow.
 *
 * New rule: an active binding has no cooldown, a released binding
 * may have a cooldown or a cleared (null) cooldown. Down() restores
 * the old pair rule, which fails only if cleared rows exist.
 */
return new class extends Migration
{
    private const CONN = 'shard';

    public function up(): void
    {
        DB::connection(self::CONN)->statement('ALTER TABLE "MachineBindings" DROP CONSTRAINT IF EXISTS "CkMachineBindingReleasedCooldownPair"');
        DB::connection(self::CONN)->statement('
            ALTER TABLE "MachineBindings" ADD CONSTRAINT "CkMachineBindingReleasedCooldownPair"
                CHECK (
                    ("ReleasedAt" IS NULL AND "RebindCooldownUntil" IS NULL)
                    OR ("ReleasedAt" IS NOT NULL)
                )
        ');
    }

    public function down(): void
    {
        DB::connection(self::CONN)->statement('ALTER TABLE "MachineBindings" DROP CONSTRAINT IF EXISTS "CkMachineBindingReleasedCooldownPair"');
        DB::connection(self::CONN)->statement('
            ALTER TABLE "MachineBindings" ADD CONSTRAINT "CkMachineBindingReleasedCooldownPair"
                CHECK (
                    ("ReleasedAt" IS NULL AND "RebindCooldownUntil" IS NULL)
                    OR ("ReleasedAt" IS NOT NULL AND "RebindCooldownUntil" IS NOT NULL)
                )
        ');
    }
};
