<?php

declare(strict_types=1);

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Seeder populating baseline Resellers, Prefixes, and Quota balances.
 */
final class ResellerPortalSeeder extends Seeder
{
    private const CONN = 'root';

    public function run(): void
    {
        $this->seedResellers();
        $this->seedPrefixes();
        $this->seedQuotas();
    }

    private function seedResellers(): void
    {
        $resellers = [
            ['ResellerId' => 1, 'ResellerName' => 'Alim Distribution Ltd', 'ResellerSlug' => 'alim', 'ContactEmail' => 'reseller@alim.example'],
            ['ResellerId' => 2, 'ResellerName' => 'Acme Corporation', 'ResellerSlug' => 'acme-corp', 'ContactEmail' => 'ops@acme.example'],
            ['ResellerId' => 3, 'ResellerName' => 'Nexus Software Group', 'ResellerSlug' => 'nexus', 'ContactEmail' => 'contact@nexus.example'],
        ];

        foreach ($resellers as $r) {
            $existing = DB::connection(self::CONN)->table('Resellers')->where('ResellerSlug', $r['ResellerSlug'])->first();
            if ($existing === null) {
                DB::connection(self::CONN)->table('Resellers')->insert([
                    'ResellerId' => $r['ResellerId'],
                    'ResellerName' => $r['ResellerName'],
                    'ResellerSlug' => $r['ResellerSlug'],
                    'ContactEmail' => $r['ContactEmail'],
                    'IsActive' => true,
                    'CreatedAt' => Carbon::now(),
                    'UpdatedAt' => Carbon::now(),
                ]);
            }
        }
    }

    private function seedPrefixes(): void
    {
        $prefixes = [
            ['ResellerId' => 1, 'PrefixValue' => 'ALIM'],
            ['ResellerId' => 1, 'PrefixValue' => 'ALIMVIP'],
            ['ResellerId' => 2, 'PrefixValue' => 'ACME'],
            ['ResellerId' => 3, 'PrefixValue' => 'NEXUS'],
        ];

        foreach ($prefixes as $p) {
            $existing = DB::connection(self::CONN)->table('Prefixes')->where('PrefixValue', $p['PrefixValue'])->first();
            if ($existing === null) {
                DB::connection(self::CONN)->table('Prefixes')->insert([
                    'ResellerId' => $p['ResellerId'],
                    'PrefixValue' => $p['PrefixValue'],
                    'IsActive' => true,
                    'CreatedAt' => Carbon::now(),
                    'UpdatedAt' => Carbon::now(),
                ]);
            }
        }
    }

    private function seedQuotas(): void
    {
        $now = Carbon::now();
        foreach ([1, 2, 3] as $resellerId) {
            $existing = DB::connection(self::CONN)->table('ResellerQuotas')
                ->where('ResellerId', $resellerId)
                ->where('LicenseCategoryId', 3)
                ->where('LicenseTierId', 1)
                ->first();

            if ($existing === null) {
                DB::connection(self::CONN)->table('ResellerQuotas')->insert([
                    'ResellerId' => $resellerId,
                    'LicenseCategoryId' => 3,
                    'LicenseTierId' => 1,
                    'LicensesGranted' => 500,
                    'LicensesConsumed' => 12,
                    'PeriodStart' => $now,
                    'PeriodEnd' => null,
                    'CreatedAt' => $now,
                    'UpdatedAt' => $now,
                ]);

                DB::connection(self::CONN)->table('ResellerQuotaLedger')->insert([
                    'ResellerId' => $resellerId,
                    'LicenseCategoryId' => 3,
                    'LicenseTierId' => 1,
                    'TierName' => 'Tier1',
                    'Delta' => 500,
                    'Action' => 'Adjusted',
                    'LedgerAction' => 'QuotaAdjusted',
                    'ReferenceId' => 'SEED-INITIAL-GRANT',
                    'OccurredAt' => $now,
                    'CreatedAt' => $now,
                    'UpdatedAt' => $now,
                ]);
            }
        }
    }
}
