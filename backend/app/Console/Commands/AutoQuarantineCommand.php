<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\AutoQuarantineService;
use Illuminate\Console\Command;

/**
 * Fraud Phase 3: auto-quarantine on server-observed live concurrency only.
 *
 *   php artisan lara:fraud:auto-quarantine [--minutes=45] [--dry-run] [--shard=<path>]
 *
 * A licence is quarantined only when the server counts more recent
 * live bindings than allowed seats. Tamper events alone never
 * trigger this command, because a client report can be spoofed.
 */
final class AutoQuarantineCommand extends Command
{
    protected $signature = 'lara:fraud:auto-quarantine {--minutes=45 : Recent live binding window in minutes} {--dry-run : List without quarantining} {--shard= : Only scan one shard file}';
    protected $description = 'Auto-quarantine licences with concurrent live bindings above allowed seats.';

    public function handle(AutoQuarantineService $service): int
    {
        $minutes = (int) $this->option('minutes');
        $dryRun = (bool) $this->option('dry-run');
        $shard = (string) $this->option('shard');
        if ($shard !== '') {
            $result = $service->quarantineShard($shard, $minutes, $dryRun);
            $this->info("Shard {$shard}: quarantined=" . implode(',', $result['quarantined']) . ' dryRun=' . ($dryRun ? 'yes' : 'no'));

            return self::SUCCESS;
        }
        $results = $service->quarantineAll($minutes, $dryRun);
        foreach ($results as $file => $serials) {
            $this->line("{$file}: quarantined=" . implode(',', $serials));
        }
        $total = array_sum(array_map('count', $results));
        $this->info("Total quarantined={$total} dryRun=" . ($dryRun ? 'yes' : 'no'));

        return self::SUCCESS;
    }
}
