<?php

declare(strict_types=1);

namespace App\Services;

use Illuminate\Support\Carbon;
use PDO;

/** Release stale heartbeat seats, per shard, with an audit row per cull. */
final class HeartbeatCullService
{
    /** Cull one shard. Dry-run counts without changing anything. */
    public function cullShard(string $shardPath, int $staleMinutes = 45, bool $dryRun = false): array
    {
        if (file_exists($shardPath) === false) {
            return ['culled' => 0, 'bindings' => []];
        }
        $pdo = new PDO("sqlite:{$shardPath}");
        $check = $pdo->query("SELECT name FROM sqlite_master WHERE type='table' AND name='MachineBindings'")->fetch();
        if ($check === false) {
            return ['culled' => 0, 'bindings' => []];
        }
        $threshold = Carbon::now()->subMinutes($staleMinutes)->toISOString();
        $stmt = $pdo->prepare('SELECT * FROM MachineBindings WHERE ReleasedAt IS NULL AND LastSeenAt < :threshold');
        $stmt->execute([':threshold' => $threshold]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        if ($dryRun) {
            return ['culled' => count($rows), 'bindings' => $rows];
        }

        return $this->releaseRows($pdo, $rows);
    }

    /** Cull every product shard under database_path. */
    public function cullAll(int $staleMinutes = 45, bool $dryRun = false): array
    {
        $results = [];
        foreach (glob(database_path('product_*.sqlite')) ?: [] as $shardPath) {
            $results[basename((string) $shardPath)] = $this->cullShard((string) $shardPath, $staleMinutes, $dryRun)['culled'];
        }

        return $results;
    }

    private function releaseRows(PDO $pdo, array $rows): array
    {
        $now = Carbon::now()->toISOString();
        $pdo->exec('CREATE TABLE IF NOT EXISTS SecurityAuditEvents (id INTEGER PRIMARY KEY AUTOINCREMENT, serialValue TEXT NOT NULL, clientIp TEXT NOT NULL, hardwareDigest TEXT NOT NULL, reasons TEXT NOT NULL, createdAt TEXT NOT NULL);');
        $culled = 0;
        foreach ($rows as $row) {
            $upd = $pdo->prepare('UPDATE MachineBindings SET ReleasedAt = :now, UpdatedAt = :now WHERE MachineBindingId = :id');
            $upd->execute([':now' => $now, ':id' => $row['MachineBindingId']]);
            $hwCheck = $pdo->query("SELECT name FROM sqlite_master WHERE type='table' AND name='HardwareBindings'")->fetch();
            if ($hwCheck !== false) {
                $del = $pdo->prepare('DELETE FROM HardwareBindings WHERE licenseId = :lid AND hardwareDigest = :hwid');
                $del->execute([':lid' => $row['LicenseId'], ':hwid' => $row['FingerprintHash']]);
            }
            $serial = $this->serialForLicense($pdo, (int) $row['LicenseId']);
            $ins = $pdo->prepare('INSERT INTO SecurityAuditEvents (serialValue, clientIp, hardwareDigest, reasons, createdAt) VALUES (:s, :ip, :hwid, :r, :dt)');
            $ins->execute([':s' => $serial, ':ip' => 'SYSTEM', ':hwid' => $row['FingerprintHash'], ':r' => json_encode(['Heartbeat cull: stale seat released']), ':dt' => $now]);
            $culled++;
        }

        return ['culled' => $culled, 'bindings' => $rows];
    }

    private function serialForLicense(PDO $pdo, int $licenseId): string
    {
        $stmt = $pdo->prepare('SELECT serialValue FROM ProductLicenses WHERE id = :id LIMIT 1');
        $stmt->execute([':id' => $licenseId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return (string) ($row['serialValue'] ?? '');
    }
}
