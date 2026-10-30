<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\QuotaBackfillService;
use Illuminate\Console\Command;

/**
 * Quota dual-write repair: backfill pre-fix shard quotas into Root.
 *
 *   php artisan lara:quotas:backfill [--reseller=<slug>] [--dry-run]
 *
 * Idempotent: Root is only raised to the shard total, never lowered, so
 * a second run reports everything unchanged. A reseller whose shard is
 * unavailable is logged and counted as a warning, not a failure, so one
 * broken tenant does not block the rest.
 */
final class QuotaBackfillCommand extends Command
{
    protected $signature = 'lara:quotas:backfill {--reseller= : Only backfill one reseller slug} {--dry-run : Report what would change without writing}';

    protected $description = 'Backfill shard quota totals into Root ResellerQuotas (idempotent).';

    public function handle(QuotaBackfillService $backfill): int
    {
        $slug = $this->option('reseller') !== null && $this->option('reseller') !== ''
            ? (string) $this->option('reseller')
            : null;
        $stats = $backfill->backfillAll($slug, (bool) $this->option('dry-run'));
        $mode = $this->option('dry-run') ? 'dry-run' : 'applied';
        $this->info("Quotas backfill ({$mode}): resellers={$stats['resellers']} examined={$stats['examined']} created={$stats['created']} raised={$stats['raised']} unchanged={$stats['unchanged']}");
        foreach ($stats['warnings'] as $warning) {
            $this->warn("Shard unavailable, skipped: {$warning}");
        }

        return self::SUCCESS;
    }
}
