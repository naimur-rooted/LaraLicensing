<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Product;
use App\Services\LicenseBackfillService;
use Illuminate\Console\Command;

/**
 * Phase 4 item 1: backfill pre-Phase-3 gateway keys from Serials into
 * ProductLicenses, idempotently (re-runs never duplicate).
 *
 *   php artisan lara:licenses:backfill [--product=<slug>] [--include-repodb]
 *
 * Defaults to the live database_path() shards only. repodb/ is not read
 * by any live path (portal, handshake, analytics, fraud all read
 * database_path()); --include-repodb backfills the mirror copy too when
 * the file exists, to keep it consistent. No shard file is created for a
 * product that has none — there is nothing to backfill there.
 * Revoked Serials rows are promoted as isRevoked=1 / isActive=0, never
 * as active. Fallback derivations (no matched deal, no single deal) are
 * logged per key by LicenseBackfillService and counted in the output.
 */
final class LicenseBackfillCommand extends Command
{
    protected $signature = 'lara:licenses:backfill {--product= : Only backfill one product slug} {--include-repodb : Also backfill the legacy repodb mirror shard}';
    protected $description = 'Backfill Serials-only gateway keys into ProductLicenses (idempotent).';

    public function handle(LicenseBackfillService $backfill): int
    {
        $slug = $this->option('product') !== null && $this->option('product') !== ''
            ? (string) $this->option('product')
            : null;
        if ($slug !== null && !Product::query()->where('productSlug', $slug)->exists()) {
            $this->error("No product with productSlug={$slug}.");

            return self::FAILURE;
        }
        $result = $backfill->backfillAll($slug, (bool) $this->option('include-repodb'));
        foreach ($result['products'] as $productSlug => $stats) {
            $this->line("{$productSlug}: serials={$stats['totalSerials']} promoted={$stats['promoted']} alreadyPresent={$stats['alreadyPresent']} revoked={$stats['revoked']} fallback={$stats['fallback']}");
        }
        $t = $result['totals'];
        $this->info("Totals: serials={$t['totalSerials']} promoted={$t['promoted']} alreadyPresent={$t['alreadyPresent']} revoked={$t['revoked']} fallback={$t['fallback']}");

        return self::SUCCESS;
    }
}
