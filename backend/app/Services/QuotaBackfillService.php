<?php

declare(strict_types=1);

namespace App\Services;

use App\Db\ShardResolver;
use App\Models\Reseller;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Quota dual-write repair: raises Root ResellerQuotas to the shard total
 * for each (Reseller, LicenseCategory, LicenseTier) tuple, idempotently.
 *
 * Why totals, not per-period rows: issuance matches Root by that tuple
 * only and ignores PeriodStart, so Root must hold the total approved
 * amount on the row issuance actually reads. Root is never lowered:
 * a Root total above the shard total (for example from the 500 default
 * row issuance creates) is left alone, so a re-run is a no-op when
 * nothing is behind. Ledger history is not backfilled; only current
 * quota amounts are repaired, and new approvals dual-write their ledger
 * row going forward.
 *
 *   php artisan lara:quotas:backfill [--reseller=<slug>] [--dry-run]
 */
final class QuotaBackfillService
{
    public function __construct(private readonly ShardResolver $shardResolver)
    {
    }

    /** @return array{resellers:int,examined:int,created:int,raised:int,unchanged:int,warnings:list<string>} */
    public function backfillAll(?string $slug = null, bool $dryRun = false): array
    {
        $stats = ['resellers' => 0, 'examined' => 0, 'created' => 0, 'raised' => 0, 'unchanged' => 0, 'warnings' => []];
        foreach ($this->activeResellers($slug) as $reseller) {
            $stats = $this->backfillReseller($reseller, $dryRun, $stats);
        }

        return $stats;
    }

    /** @return \Illuminate\Support\Collection<int, Reseller> */
    private function activeResellers(?string $slug): \Illuminate\Support\Collection
    {
        $q = Reseller::query()->where('IsActive', true)->orderBy('ResellerSlug');
        if ($slug !== null) {
            $q->where('ResellerSlug', $slug);
        }

        return $q->get();
    }

    /** @param array{resellers:int,examined:int,created:int,raised:int,unchanged:int,warnings:list<string>} $stats */
    private function backfillReseller(Reseller $reseller, bool $dryRun, array $stats): array
    {
        if (! $this->tryBind($reseller)) {
            $stats['warnings'][] = (string) $reseller->ResellerSlug;

            return $stats;
        }
        $stats['resellers']++;
        foreach ($this->shardQuotaTotals((int) $reseller->ResellerId) as $tuple) {
            $stats = $this->reconcileTuple((int) $reseller->ResellerId, $tuple, $dryRun, $stats);
        }

        return $stats;
    }

    private function tryBind(Reseller $reseller): bool
    {
        try {
            $this->shardResolver->bind((string) $reseller->ResellerSlug);

            return true;
        } catch (Throwable $e) {
            Log::warning('quota.backfill.shard_error', [
                'resellerSlug' => (string) $reseller->ResellerSlug,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    /** @return \Illuminate\Support\Collection<int, object> */
    private function shardQuotaTotals(int $resellerId): \Illuminate\Support\Collection
    {
        return DB::connection('shard')->table('Quotas')
            ->where('ResellerId', $resellerId)
            ->selectRaw('LicenseCategoryId, LicenseTierId, SUM(LicensesGranted) as TotalGranted, MIN(PeriodStart) as MinStart, MAX(PeriodEnd) as MaxEnd')
            ->groupBy('LicenseCategoryId', 'LicenseTierId')
            ->get();
    }

    /** @param array{resellers:int,examined:int,created:int,raised:int,unchanged:int,warnings:list<string>} $stats */
    private function reconcileTuple(int $resellerId, object $tuple, bool $dryRun, array $stats): array
    {
        $rootRows = DB::connection('root')->table('ResellerQuotas')
            ->where('ResellerId', $resellerId)
            ->where('LicenseCategoryId', (int) $tuple->LicenseCategoryId)
            ->where('LicenseTierId', (int) $tuple->LicenseTierId)
            ->orderBy('Id')->get();
        $stats['examined']++;
        if ($rootRows->isEmpty()) {
            return $this->createRootQuota($resellerId, $tuple, $dryRun, $stats);
        }
        if ((int) $rootRows->sum('LicensesGranted') >= (int) $tuple->TotalGranted) {
            $stats['unchanged']++;

            return $stats;
        }

        return $this->raiseRootQuota($rootRows->first(), $tuple, (int) $rootRows->sum('LicensesGranted'), $dryRun, $stats);
    }

    /** @param array{resellers:int,examined:int,created:int,raised:int,unchanged:int,warnings:list<string>} $stats */
    private function createRootQuota(int $resellerId, object $tuple, bool $dryRun, array $stats): array
    {
        if (! $dryRun) {
            DB::connection('root')->table('ResellerQuotas')->insert([
                'ResellerId' => $resellerId, 'LicenseCategoryId' => (int) $tuple->LicenseCategoryId, 'LicenseTierId' => (int) $tuple->LicenseTierId,
                'LicensesGranted' => (int) $tuple->TotalGranted, 'LicensesConsumed' => 0, 'PeriodStart' => $tuple->MinStart, 'PeriodEnd' => $tuple->MaxEnd,
                'CreatedAt' => Carbon::now(), 'UpdatedAt' => Carbon::now(),
            ]);
        }
        $stats['created']++;

        return $stats;
    }

    /** @param array{resellers:int,examined:int,created:int,raised:int,unchanged:int,warnings:list<string>} $stats */
    private function raiseRootQuota(object $firstRow, object $tuple, int $rootTotal, bool $dryRun, array $stats): array
    {
        if (! $dryRun) {
            DB::connection('root')->table('ResellerQuotas')->where('Id', (int) $firstRow->Id)->increment('LicensesGranted', (int) $tuple->TotalGranted - $rootTotal);
        }
        $stats['raised']++;

        return $stats;
    }
}
