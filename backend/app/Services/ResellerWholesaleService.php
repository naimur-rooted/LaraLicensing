<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Reseller;
use Illuminate\Support\Carbon;
use PDO;

/**
 * Service managing Reseller Wholesale quotas, offer builder, and threshold alerts.
 */
class ResellerWholesaleService
{
    /** List all wholesale quotas and capacity across active resellers. */
    public function getResellerOverview(string $resellerSlug): array
    {
        $reseller = Reseller::where('ResellerSlug', $resellerSlug)->first();
        if (!$reseller) {
            return ['isSuccess' => false, 'message' => "Reseller '{$resellerSlug}' not found."];
        }

        $shardPath = database_path("shard_reseller_{$resellerSlug}.sqlite");
        $this->ensureResellerShardSchema($shardPath);
        $pdo = new PDO("sqlite:{$shardPath}");
        $pdo->exec('PRAGMA journal_mode = WAL;');
        $pdo->exec('PRAGMA busy_timeout = 5000;');

        $stmt = $pdo->query('SELECT * FROM WholesaleQuotas ORDER BY id DESC');
        $quotas = $stmt ? $stmt->fetchAll(PDO::FETCH_ASSOC) : [];

        $offersStmt = $pdo->query('SELECT * FROM ResellerCustomOffers ORDER BY id DESC');
        $offers = $offersStmt ? $offersStmt->fetchAll(PDO::FETCH_ASSOC) : [];

        $totalGranted = array_sum(array_column($quotas, 'allocatedCount'));
        $totalConsumed = array_sum(array_column($quotas, 'consumedCount'));
        $remaining = max(0, $totalGranted - $totalConsumed);
        $usageRatio = $totalGranted > 0 ? ($totalConsumed / $totalGranted) : 0.0;
        $isWarning = $totalGranted > 0 && ($remaining / $totalGranted) < 0.15;

        return [
            'isSuccess' => true,
            'reseller' => [
                'name' => $reseller->ResellerName,
                'slug' => $reseller->ResellerSlug,
                'email' => $reseller->ContactEmail,
            ],
            'quotas' => $quotas,
            'offers' => $offers,
            'metrics' => [
                'totalGranted' => $totalGranted,
                'totalConsumed' => $totalConsumed,
                'remaining' => $remaining,
                'usagePercent' => round($usageRatio * 100, 1),
                'isThresholdWarning' => $isWarning,
                'warningMessage' => $isWarning ? 'Attention: Wholesale quota is below 15% remaining capacity!' : null,
            ],
        ];
    }

    /** Allocate or top-up wholesale bulk quota for a reseller. */
    public function allocateWholesaleQuota(string $resellerSlug, string $tierCode, int $count): array
    {
        $shardPath = database_path("shard_reseller_{$resellerSlug}.sqlite");
        $this->ensureResellerShardSchema($shardPath);
        $pdo = new PDO("sqlite:{$shardPath}");

        $ins = $pdo->prepare('INSERT INTO WholesaleQuotas (tierCode, allocatedCount, consumedCount, allocatedAt) VALUES (:t, :cnt, 0, :dt)');
        $ins->execute([
            ':t' => strtoupper(trim($tierCode)),
            ':cnt' => $count,
            ':dt' => Carbon::now()->toISOString(),
        ]);

        return [
            'isSuccess' => true,
            'quotaId' => (int) $pdo->lastInsertId(),
            'tierCode' => strtoupper(trim($tierCode)),
            'allocatedCount' => $count,
        ];
    }

    /** Create custom wholesale package offer with custom pricing. */
    public function createCustomOffer(string $resellerSlug, array $attributes): array
    {
        $shardPath = database_path("shard_reseller_{$resellerSlug}.sqlite");
        $this->ensureResellerShardSchema($shardPath);
        $pdo = new PDO("sqlite:{$shardPath}");

        $ins = $pdo->prepare('INSERT INTO ResellerCustomOffers (offerName, retailPriceCents, durationDays, machineSlots, commissionPercent, isActive, createdAt) VALUES (:n, :p, :dur, :slots, :comm, 1, :dt)');
        $ins->execute([
            ':n' => (string) $attributes['offerName'],
            ':p' => (int) ($attributes['retailPriceCents'] ?? 2900),
            ':dur' => (int) ($attributes['durationDays'] ?? 365),
            ':slots' => (int) ($attributes['machineSlots'] ?? 1),
            ':comm' => (int) ($attributes['commissionPercent'] ?? 20),
            ':dt' => Carbon::now()->toISOString(),
        ]);

        return [
            'isSuccess' => true,
            'offerId' => (int) $pdo->lastInsertId(),
            'offerName' => $attributes['offerName'],
        ];
    }

    private function ensureResellerShardSchema(string $shardPath): void
    {
        if (!file_exists($shardPath)) {
            touch($shardPath);
        }
        $pdo = new PDO("sqlite:{$shardPath}");
        $pdo->exec('CREATE TABLE IF NOT EXISTS WholesaleQuotas (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            tierCode TEXT NOT NULL,
            allocatedCount INTEGER NOT NULL,
            consumedCount INTEGER DEFAULT 0,
            allocatedAt TEXT NOT NULL
        );');
        $pdo->exec('CREATE TABLE IF NOT EXISTS ResellerCustomOffers (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            offerName TEXT NOT NULL,
            retailPriceCents INTEGER NOT NULL,
            durationDays INTEGER DEFAULT 365,
            machineSlots INTEGER DEFAULT 1,
            commissionPercent INTEGER DEFAULT 20,
            isActive INTEGER DEFAULT 1,
            createdAt TEXT NOT NULL
        );');
    }
}
