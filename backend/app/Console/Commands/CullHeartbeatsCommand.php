<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\HeartbeatCullService;
use Illuminate\Console\Command;

/**
 * Fraud Phase 2: release stale heartbeat seats.
 *
 *   php artisan lara:heartbeats:cull [--minutes=45] [--dry-run] [--shard=<path>]
 *
 * Default stale threshold is 45 minutes: a 30 minute heartbeat
 * window plus a 15 minute resurrection window for sleep and wake.
 * Dry-run counts without changing anything. This command is manual
 * in this phase; scheduling it is a separate decision.
 */
final class CullHeartbeatsCommand extends Command
{
    protected $signature = 'lara:heartbeats:cull {--minutes=45 : Stale threshold in minutes} {--dry-run : Count without releasing} {--shard= : Only cull one shard file}';
    protected $description = 'Release stale heartbeat seats from product shards.';

    public function handle(HeartbeatCullService $cull): int
    {
        $minutes = (int) $this->option('minutes');
        $dryRun = (bool) $this->option('dry-run');
        $shard = (string) $this->option('shard');
        if ($shard !== '') {
            $result = $cull->cullShard($shard, $minutes, $dryRun);
            $this->info("Shard {$shard}: culled={$result['culled']} dryRun=" . ($dryRun ? 'yes' : 'no'));

            return self::SUCCESS;
        }
        $results = $cull->cullAll($minutes, $dryRun);
        foreach ($results as $file => $count) {
            $this->line("{$file}: culled={$count}");
        }
        $this->info('Total culled=' . array_sum($results) . ' dryRun=' . ($dryRun ? 'yes' : 'no'));

        return self::SUCCESS;
    }
}
