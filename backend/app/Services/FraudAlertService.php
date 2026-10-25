<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Product;
use Illuminate\Support\Carbon;
use PDO;

/**
 * Service managing real-time security anomalies, geo-velocity impossible travel flags,
 * and 1-click quarantine operations.
 */
class FraudAlertService
{
    /** Retrieve security events and flagged attempts for a product. */
    public function getSecurityEvents(string $productSlug): array
    {
        $shardPath = database_path("product_{$productSlug}.sqlite");
        if (!file_exists($shardPath)) {
            return ['isSuccess' => true, 'events' => [], 'quarantinedCount' => 0];
        }

        $pdo = new PDO("sqlite:{$shardPath}");
        $this->ensureSecurityTables($pdo);

        $stmt = $pdo->query('SELECT * FROM SecurityAuditEvents ORDER BY id DESC LIMIT 50');
        $events = $stmt ? $stmt->fetchAll(PDO::FETCH_ASSOC) : [];

        $qStmt = $pdo->query('SELECT COUNT(*) as cnt FROM ProductLicenses WHERE isRevoked = 1');
        $qCount = $qStmt ? (int) $qStmt->fetch(PDO::FETCH_ASSOC)['cnt'] : 0;

        $formatted = array_map(function ($ev) {
            return [
                'id' => (int) $ev['id'],
                'serialValue' => $ev['serialValue'],
                'clientIp' => $ev['clientIp'],
                'hardwareDigest' => $ev['hardwareDigest'],
                'reasons' => json_decode((string) $ev['reasons'], true) ?: [],
                'createdAt' => $ev['createdAt'],
                'severity' => 'HIGH',
            ];
        }, $events);

        return [
            'isSuccess' => true,
            'events' => $formatted,
            'quarantinedCount' => $qCount,
        ];
    }

    /** 1-click quarantine a suspicious license or force revoke it. */
    public function quarantineLicense(string $productSlug, string $serialValue, string $reason = 'Suspicious activity'): array
    {
        $shardPath = database_path("product_{$productSlug}.sqlite");
        if (!file_exists($shardPath)) {
            touch($shardPath);
        }

        $pdo = new PDO("sqlite:{$shardPath}");
        $this->ensureSecurityTables($pdo);

        $upd = $pdo->prepare('UPDATE ProductLicenses SET isRevoked = 1, isActive = 0 WHERE serialValue = :s');
        $upd->execute([':s' => $serialValue]);

        if ($upd->rowCount() === 0) {
            $insLic = $pdo->prepare('INSERT INTO ProductLicenses (serialValue, dealCode, allowedSeats, isActive, isRevoked, createdAt) VALUES (:s, "QUARANTINED", 1, 0, 1, :dt)');
            $insLic->execute([':s' => $serialValue, ':dt' => Carbon::now()->toISOString()]);
        }

        $ins = $pdo->prepare('INSERT INTO SecurityAuditEvents (serialValue, clientIp, hardwareDigest, reasons, createdAt) VALUES (:s, "SYSTEM", "QUARANTINE_ACTION", :r, :dt)');
        $ins->execute([
            ':s' => $serialValue,
            ':r' => json_encode(["Quarantined manually: {$reason}"]),
            ':dt' => Carbon::now()->toISOString(),
        ]);

        return [
            'isSuccess' => true,
            'message' => "License '{$serialValue}' has been quarantined and revoked.",
            'serialValue' => $serialValue,
        ];
    }

    /** Unbind a specific machine hardware fingerprint from a license. */
    public function unbindHardware(string $productSlug, string $serialValue, string $hardwareDigest): array
    {
        $shardPath = database_path("product_{$productSlug}.sqlite");
        if (!file_exists($shardPath)) {
            touch($shardPath);
        }

        $pdo = new PDO("sqlite:{$shardPath}");
        $this->ensureSecurityTables($pdo);

        $del = $pdo->prepare('DELETE FROM HardwareBindings WHERE hardwareDigest = :hwid');
        $del->execute([':hwid' => $hardwareDigest]);

        return [
            'isSuccess' => true,
            'message' => "Hardware digest '{$hardwareDigest}' successfully released.",
        ];
    }

    private function ensureSecurityTables(PDO $pdo): void
    {
        $pdo->exec('CREATE TABLE IF NOT EXISTS SecurityAuditEvents (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            serialValue TEXT NOT NULL,
            clientIp TEXT NOT NULL,
            hardwareDigest TEXT NOT NULL,
            reasons TEXT NOT NULL,
            createdAt TEXT NOT NULL
        );');
        $pdo->exec('CREATE TABLE IF NOT EXISTS ProductLicenses (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            serialValue TEXT UNIQUE NOT NULL,
            dealCode TEXT NOT NULL,
            allowedSeats INTEGER DEFAULT 1,
            isActive INTEGER DEFAULT 1,
            isRevoked INTEGER DEFAULT 0,
            createdAt TEXT NOT NULL
        );');
        $pdo->exec('CREATE TABLE IF NOT EXISTS HardwareBindings (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            licenseId INTEGER NOT NULL,
            hardwareDigest TEXT NOT NULL,
            hostname TEXT DEFAULT "Client PC",
            boundAt TEXT NOT NULL,
            lastSeenAt TEXT NOT NULL
        );');
    }
}
