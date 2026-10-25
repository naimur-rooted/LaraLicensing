<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Product;
use Carbon\Carbon;
use PDO;
use Throwable;

/**
 * Service calculating aggregate metrics across multi-tenant product SQLite shards.
 */
class DeveloperAnalyticsService
{
    /**
     * Get aggregate overview metrics for a developer.
     */
    public function getOverview(int $userId): array
    {
        return $this->getOverviewMetrics($userId);
    }

    /**
     * Get aggregate overview metrics for a developer.
     */
    public function getOverviewMetrics(?int $userId = null, ?string $filterSlug = null): array
    {
        if ($userId === null || $userId <= 0) {
            return $this->buildZeroStateMetrics();
        }
        $products = $this->fetchProductsForAnalytics($userId, $filterSlug);
        if ($products->isEmpty()) {
            return $this->buildZeroStateMetrics();
        }

        [$counts, $timelineMap, $countryStats, $uniqueUsers] = $this->initMetricContainers($products->count());
        foreach ($products as $prod) {
            $this->aggregateProductShard($prod, $counts, $timelineMap, $countryStats, $uniqueUsers);
        }

        $countryMap = $this->buildCountryMap($countryStats, $counts['activations']);
        return $this->buildOverviewPayload($counts, $timelineMap, $countryMap, count($uniqueUsers), $products->count());
    }

    private function initMetricContainers(int $releaseCount): array
    {
        $counts = ['activations' => 0, 'trialActivations' => 0, 'licenses' => 0, 'seatsQuota' => 0, 'releases' => $releaseCount];
        return [$counts, $this->initTimelineMap(), [], []];
    }

    private function fetchProductsForAnalytics(?int $userId, ?string $filterSlug): \Illuminate\Database\Eloquent\Collection
    {
        $query = Product::where('isActive', true);
        if ($userId !== null && $userId > 0) {
            $query->where('userId', $userId);
        }
        if (!empty($filterSlug) && $filterSlug !== 'all') {
            $query->where('productSlug', $filterSlug);
        }
        return $query->get();
    }

    private function buildZeroStateMetrics(): array
    {
        return [
            'kpis' => $this->buildZeroKpis(),
            'timeline' => [],
            'activationsByCountry' => [],
            'productsCount' => 0,
            'trialExpiresOn' => 'Active',
            'summary' => $this->buildZeroSummary(),
            'chartData' => [],
            'topCountries' => [],
        ];
    }

    private function buildZeroKpis(): array
    {
        return [
            'activations' => ['current' => 0, 'max' => 50, 'label' => 'Activations'],
            'trialActivations' => ['current' => 0, 'max' => 100, 'label' => 'Trial Activations'],
            'licenses' => ['current' => 0, 'max' => 100, 'label' => 'Licenses'],
            'users' => ['current' => 0, 'max' => null, 'label' => 'Users'],
            'releases' => ['current' => 0, 'max' => 50, 'label' => 'Releases'],
        ];
    }

    private function buildZeroSummary(): array
    {
        $zeroQuotas = [
            'activations' => ['used' => 0, 'total' => 50],
            'trialActivations' => ['used' => 0, 'total' => 100],
            'apiRequests' => ['used' => 0, 'total' => 100000],
            'users' => ['used' => 0, 'total' => 50],
            'releases' => ['used' => 0, 'total' => 50],
        ];
        return [
            'totalActivations' => 0, 'totalTrialActivations' => 0,
            'totalLicenses' => 0, 'totalUsers' => 0, 'totalReleases' => 0,
            'quotas' => $zeroQuotas,
        ];
    }

    private function initTimelineMap(): array
    {
        $timelineMap = [];
        $now = Carbon::now();
        for ($i = 29; $i >= 0; $i--) {
            $dt = $now->copy()->subDays($i);
            $timelineMap[$dt->format('M d, Y')] = [
                'date' => $dt->format('M d, Y'),
                'shortDate' => $dt->format('M d'),
                'activations' => 0,
                'trialActivations' => 0,
            ];
        }
        return $timelineMap;
    }

    private function aggregateProductShard(Product $prod, array &$counts, array &$timelineMap, array &$countryStats, array &$uniqueUsers): void
    {
        $shardPath = database_path("product_{$prod->productSlug}.sqlite");
        if (!file_exists($shardPath)) {
            return;
        }
        try {
            $pdo = new PDO("sqlite:{$shardPath}");
            $pdo->exec('PRAGMA busy_timeout = 3000;');
            $this->queryShardLicenses($pdo, $counts);
            $this->queryShardBindings($pdo, $counts, $timelineMap, $countryStats, $uniqueUsers);
        } catch (Throwable) {}
    }

    private function queryShardLicenses(PDO $pdo, array &$counts): void
    {
        $stmt = $pdo->query('SELECT id, serialValue, dealCode, allowedSeats, isActive, isRevoked, createdAt FROM ProductLicenses');
        if (!$stmt) {
            return;
        }
        $licenses = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $counts['licenses'] += count($licenses);
        foreach ($licenses as $lic) {
            $counts['seatsQuota'] += (int) ($lic['allowedSeats'] ?? 1);
            $deal = strtolower((string) ($lic['dealCode'] ?? ''));
            $isTrial = str_contains($deal, 'trial') || str_contains($deal, 'free') || str_contains($deal, 'demo');
            $counts['trialActivations'] += $isTrial ? 1 : 0;
        }
    }

    private function queryShardBindings(PDO $pdo, array &$counts, array &$timelineMap, array &$countryStats, array &$uniqueUsers): void
    {
        $stmt = $pdo->query('SELECT id, licenseId, hardwareDigest, hostname, boundAt, lastSeenAt, countryCode, countryName FROM HardwareBindings');
        if (!$stmt) {
            return;
        }
        $bindings = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $counts['activations'] += count($bindings);
        foreach ($bindings as $b) {
            $uniqueUsers[($b['licenseId'] ?? '0') . '-' . ($b['hostname'] ?? 'PC')] = true;
            $this->recordBindingTimeline($b, $timelineMap);
            $this->recordBindingCountry($b, $countryStats);
        }
    }

    private function recordBindingTimeline(array $binding, array &$timelineMap): void
    {
        $boundAt = $binding['boundAt'] ?? $binding['lastSeenAt'] ?? null;
        if ($boundAt) {
            try {
                $dateKey = Carbon::parse($boundAt)->format('M d, Y');
                if (isset($timelineMap[$dateKey])) {
                    $timelineMap[$dateKey]['activations']++;
                }
            } catch (Throwable) {}
        }
    }

    private function recordBindingCountry(array $binding, array &$countryStats): void
    {
        $cCode = strtoupper(trim((string) ($binding['countryCode'] ?? 'US')));
        $cName = trim((string) ($binding['countryName'] ?? 'United States')) ?: $cCode;
        if (!isset($countryStats[$cCode])) {
            $countryStats[$cCode] = ['country' => $cName, 'code' => $cCode, 'count' => 0];
        }
        $countryStats[$cCode]['count']++;
    }

    private function buildCountryMap(array $countryStats, int $totalActivations): array
    {
        if ($totalActivations <= 0) {
            return [];
        }
        $countryMap = array_map(fn($s) => [
            'country' => $s['country'],
            'code' => $s['code'],
            'count' => $s['count'],
            'percentage' => (int) round(($s['count'] / $totalActivations) * 100),
        ], array_values($countryStats));
        usort($countryMap, fn($a, $b) => $b['count'] <=> $a['count']);
        return $countryMap;
    }

    private function buildOverviewPayload(
        array $counts,
        array $timelineMap,
        array $countryMap,
        int $uniqueUserCount,
        int $productsCount = 1
    ): array {
        return [
            'summary' => $this->buildSummaryPayload($counts, $uniqueUserCount),
            'chartData' => array_values($timelineMap),
            'topCountries' => array_slice($countryMap, 0, 5),
            'productsCount' => $productsCount,
            'trialExpiresOn' => 'Nov 20, 2026',
        ];
    }

    private function buildSummaryPayload(array $counts, int $uniqueUserCount): array
    {
        return [
            'totalActivations' => $counts['activations'],
            'totalTrialActivations' => $counts['trialActivations'],
            'totalLicenses' => $counts['licenses'],
            'totalUsers' => $uniqueUserCount,
            'totalReleases' => $counts['releases'],
            'quotas' => $this->buildQuotasPayload($counts, $uniqueUserCount),
        ];
    }

    private function buildQuotasPayload(array $counts, int $uniqueUserCount): array
    {
        return [
            'activations' => ['used' => $counts['activations'], 'total' => max(50, $counts['seatsQuota'])],
            'trialActivations' => ['used' => $counts['trialActivations'], 'total' => 20000],
            'apiRequests' => ['used' => $counts['activations'] * 14, 'total' => 100000],
            'users' => ['used' => $uniqueUserCount, 'total' => max(50, $uniqueUserCount + 10)],
            'releases' => ['used' => $counts['releases'], 'total' => max(50, $counts['releases'] + 5)],
        ];
    }
}
