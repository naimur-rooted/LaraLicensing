<?php

declare(strict_types=1);

namespace App\Services;

use Illuminate\Support\Carbon;
use PDO;

/** Auto-quarantine on server-observed live concurrency only, never on a client report. */
final class AutoQuarantineService
{
    /** Quarantine licences in one shard whose recent live bindings exceed allowed seats. */
    public function quarantineShard(string $shardPath, int $recentMinutes = 45, bool $dryRun = false): array
    {
        if (file_exists($shardPath) === false) {
            return ['quarantined' => []];
        }
        $pdo = new PDO("sqlite:{$shardPath}");
        $check = $pdo->query("SELECT name FROM sqlite_master WHERE type='table' AND name='ProductLicenses'")->fetch();
        if ($check === false) {
            return ['quarantined' => []];
        }
        $licences = $pdo->query('SELECT * FROM ProductLicenses WHERE isRevoked = 0 AND isActive = 1')->fetchAll(PDO::FETCH_ASSOC);
        $quarantined = [];
        foreach ($licences as $licence) {
            $recent = $this->recentBindingCount($pdo, (int) $licence['id'], $recentMinutes);
            if ($recent <= (int) $licence['allowedSeats']) {
                continue;
            }
            if ($dryRun === false) {
                $this->quarantineLicence($pdo, $licence, $recent);
            }
            $quarantined[] = (string) $licence['serialValue'];
        }

        return ['quarantined' => $quarantined];
    }

    /** Quarantine every product shard under database_path. */
    public function quarantineAll(int $recentMinutes = 45, bool $dryRun = false): array
    {
        $results = [];
        foreach (glob(database_path('product_*.sqlite')) ?: [] as $shardPath) {
            $results[basename((string) $shardPath)] = $this->quarantineShard((string) $shardPath, $recentMinutes, $dryRun)['quarantined'];
        }

        return $results;
    }

    private function recentBindingCount(PDO $pdo, int $licenseId, int $recentMinutes): int
    {
        $check = $pdo->query("SELECT name FROM sqlite_master WHERE type='table' AND name='MachineBindings'")->fetch();
        if ($check === false) {
            return 0;
        }
        $since = Carbon::now()->subMinutes($recentMinutes)->toISOString();
        $stmt = $pdo->prepare('SELECT COUNT(*) as cnt FROM MachineBindings WHERE LicenseId = :lid AND ReleasedAt IS NULL AND LastSeenAt >= :since');
        $stmt->execute([':lid' => $licenseId, ':since' => $since]);

        return (int) ($stmt->fetch(PDO::FETCH_ASSOC)['cnt'] ?? 0);
    }

    private function quarantineLicence(PDO $pdo, array $licence, int $recent): void
    {
        $upd = $pdo->prepare('UPDATE ProductLicenses SET isRevoked = 1, isActive = 0 WHERE id = :id');
        $upd->execute([':id' => $licence['id']]);
        $pdo->exec('CREATE TABLE IF NOT EXISTS SecurityAuditEvents (id INTEGER PRIMARY KEY AUTOINCREMENT, serialValue TEXT NOT NULL, clientIp TEXT NOT NULL, hardwareDigest TEXT NOT NULL, reasons TEXT NOT NULL, createdAt TEXT NOT NULL);');
        $ins = $pdo->prepare('INSERT INTO SecurityAuditEvents (serialValue, clientIp, hardwareDigest, reasons, createdAt) VALUES (:s, :ip, :hwid, :r, :dt)');
        $ins->execute([':s' => $licence['serialValue'], ':ip' => 'SYSTEM', ':hwid' => 'AUTO_QUARANTINE', ':r' => json_encode(["Auto-quarantine: recent live bindings {$recent} above allowed seats {$licence['allowedSeats']}"]), ':dt' => Carbon::now()->toISOString()]);
    }
}
