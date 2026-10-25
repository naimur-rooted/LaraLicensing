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
    public function getOverviewMetrics(?string $filterSlug = null): array
    {
        $products = $this->fetchProductsForAnalytics($filterSlug);
        $counts = ['activations' => 0, 'trialActivations' => 0, 'licenses' => 0, 'seatsQuota' => 0, 'releases' => Product::count()];
        $timelineMap = $this->initTimelineMap();
        $countryStats = [];
        $uniqueUsers = [];

        foreach ($products as $prod) {
            $this->aggregateProductShard($prod, $counts, $timelineMap, $countryStats, $uniqueUsers);
        }

        $countryMap = $this->buildCountryMap($countryStats, $counts['activations']);

        return $this->buildOverviewPayload($counts, $timelineMap, $countryMap, count($uniqueUsers));
    }

    private function fetchProductsForAnalytics(?string $filterSlug): \Illuminate\Database\Eloquent\Collection
    {
        $query = Product::where('isActive', true);
        if (!empty($filterSlug) && $filterSlug !== 'all') {
            $query->where('productSlug', $filterSlug);
        }

        return $query->get();
    }

    private function initTimelineMap(): array
    {
        $timelineMap = [];
        $now = Carbon::now();
        for ($i = 29; $i >= 0; $i--) {
            $dt = $now->copy()->subDays($i);
            $key = $dt->format('M d, Y');
            $timelineMap[$key] = [
                'date' => $key,
                'shortDate' => $dt->format('M d'),
                'activations' => 0,
                'trialActivations' => 0,
            ];
        }

        return $timelineMap;
    }

    private function aggregateProductShard(
        Product $prod,
        array &$counts,
        array &$timelineMap,
        array &$countryStats,
        array &$uniqueUsers
    ): void {
        $shardPath = database_path("product_{$prod->productSlug}.sqlite");
        if (!file_exists($shardPath)) {
            return;
        }

        try {
            $pdo = new PDO("sqlite:{$shardPath}");
            $pdo->exec('PRAGMA busy_timeout = 3000;');
            $this->queryShardLicenses($pdo, $counts);
            $this->queryShardBindings($pdo, $counts, $timelineMap, $countryStats, $uniqueUsers);
        } catch (Throwable) {
            // Ignore corrupted or empty shards
        }
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
            if (str_contains($deal, 'trial') || str_contains($deal, 'free') || str_contains($deal, 'demo')) {
                $counts['trialActivations']++;
            }
        }
    }

    private function queryShardBindings(
        PDO $pdo,
        array &$counts,
        array &$timelineMap,
        array &$countryStats,
        array &$uniqueUsers
    ): void {
        $stmt = $pdo->query('SELECT id, licenseId, hardwareDigest, hostname, boundAt, lastSeenAt, countryCode, countryName FROM HardwareBindings');
        if (!$stmt) {
            return;
        }
        $bindings = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $counts['activations'] += count($bindings);
        foreach ($bindings as $b) {
            $userKey = ($b['licenseId'] ?? '0') . '-' . ($b['hostname'] ?? 'PC');
            $uniqueUsers[$userKey] = true;
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
        $countryMap = [];
        if ($totalActivations > 0) {
            foreach ($countryStats as $stat) {
                $countryMap[] = [
                    'country' => $stat['country'],
                    'code' => $stat['code'],
                    'count' => $stat['count'],
                    'percentage' => (int) round(($stat['count'] / $totalActivations) * 100),
                ];
            }
            usort($countryMap, fn($a, $b) => $b['count'] <=> $a['count']);
        }

        return $countryMap;
    }

    private function buildOverviewPayload(array $counts, array $timelineMap, array $countryMap, int $uniqueUserCount): array
    {
        return [
            'summary' => [
                'totalActivations' => $counts['activations'],
                'totalTrialActivations' => $counts['trialActivations'],
                'totalLicenses' => $counts['licenses'],
                'totalUsers' => $uniqueUserCount,
                'totalReleases' => $counts['releases'],
                'quotas' => [
                    'activations' => ['used' => $counts['activations'], 'total' => max(50, $counts['seatsQuota'])],
                    'trialActivations' => ['used' => $counts['trialActivations'], 'total' => 20000],
                    'apiRequests' => ['used' => $counts['activations'] * 14, 'total' => 100000],
                    'users' => ['used' => $uniqueUserCount, 'total' => max(50, $uniqueUserCount + 10)],
                    'releases' => ['used' => $counts['releases'], 'total' => max(50, $counts['releases'] + 5)],
                ],
            ],
            'chartData' => array_values($timelineMap),
            'topCountries' => array_slice($countryMap, 0, 5),
        ];
    }
}
