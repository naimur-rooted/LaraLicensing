<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Product;
use App\Models\ProductDeal;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use PDO;

/**
 * Service managing client license handshakes, periodic check cadences,
 * and multi-seat hardware fingerprint bindings.
 */
class ClientHandshakeService
{
    public function __construct(
        private readonly JwtTokenService $jwtService,
        private readonly RiskAssessmentService $riskService,
    ) {}

    /** Verify client license and mint periodic offline token. */
    public function verifyClient(
        string $productSlug,
        string $serialValue,
        string $hardwareDigest,
        string $clientIp,
        string $hostname = 'Client PC',
        ?string $countryCode = null,
        ?string $countryName = null
    ): array {
        $product = Product::with('deals')->where('productSlug', $productSlug)->where('isActive', true)->first();
        if (!$product) {
            return ['isValid' => false, 'errorCode' => 'ProductNotFound', 'message' => "Product '{$productSlug}' not found."];
        }

        $shardPath = database_path("product_{$productSlug}.sqlite");
        $this->ensureShardSchema($shardPath);
        $pdo = new PDO("sqlite:{$shardPath}");
        $pdo->exec('PRAGMA journal_mode = WAL;');
        $pdo->exec('PRAGMA busy_timeout = 5000;');

        $license = $this->findOrRegisterLicense($pdo, $serialValue, $product);
        if (!$license['isActive'] || $license['isRevoked']) {
            return ['isValid' => false, 'errorCode' => 'LicenseRevoked', 'message' => 'License has been revoked or suspended.'];
        }

        $risk = $this->riskService->evaluateAttempt($serialValue, $clientIp, $hardwareDigest);
        if (!$risk['isPermitted']) {
            $this->logSecurityEvent($pdo, $serialValue, $clientIp, $hardwareDigest, $risk['reasons']);
            return ['isValid' => false, 'errorCode' => 'SecurityRiskDetected', 'message' => 'Anomaly detected. Attempt quarantined.', 'reasons' => $risk['reasons']];
        }

        $resolvedCountryCode = strtoupper(trim((string) ($countryCode ?: 'US')));
        $resolvedCountryName = trim((string) ($countryName ?: $this->resolveCountryName($resolvedCountryCode)));

        $bindingResult = $this->bindHardwareSeat(
            $pdo,
            (int) $license['id'],
            $hardwareDigest,
            $hostname,
            (int) $license['allowedSeats'],
            $clientIp,
            $resolvedCountryCode,
            $resolvedCountryName
        );
        if (!$bindingResult['isBound']) {
            return ['isValid' => false, 'errorCode' => 'SeatLimitExceeded', 'message' => $bindingResult['message'], 'boundSeats' => $bindingResult['boundSeats']];
        }

        $checkInterval = (int) $product->checkIntervalDays;
        $offlineToken = $this->jwtService->issueOfflineToken($serialValue, $hardwareDigest, $license['dealCode'], (int) $license['allowedSeats']);

        return [
            'isValid' => true,
            'serialValue' => $serialValue,
            'productSlug' => $productSlug,
            'dealCode' => $license['dealCode'],
            'checkIntervalDays' => $checkInterval,
            'nextCheckBefore' => Carbon::now()->addDays($checkInterval)->toISOString(),
            'allowedSeats' => (int) $license['allowedSeats'],
            'activeSeats' => $bindingResult['activeCount'],
            'offlineToken' => $offlineToken,
        ];
    }

    private function findOrRegisterLicense(PDO $pdo, string $serial, Product $product): array
    {
        $stmt = $pdo->prepare('SELECT * FROM ProductLicenses WHERE serialValue = :serial LIMIT 1');
        $stmt->execute([':serial' => $serial]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($row) {
            return $row;
        }

        $deal = $product->deals->first();
        $seats = $deal ? (int) $deal->allowedMachineSeats : 1;
        $dealCode = $deal ? $deal->dealCode : 'DEFAULT';

        $ins = $pdo->prepare('INSERT INTO ProductLicenses (serialValue, dealCode, allowedSeats, isActive, isRevoked, createdAt) VALUES (:s, :d, :seats, 1, 0, :dt)');
        $ins->execute([
            ':s' => $serial,
            ':d' => $dealCode,
            ':seats' => $seats,
            ':dt' => Carbon::now()->toISOString(),
        ]);

        return [
            'id' => (int) $pdo->lastInsertId(),
            'serialValue' => $serial,
            'dealCode' => $dealCode,
            'allowedSeats' => $seats,
            'isActive' => 1,
            'isRevoked' => 0,
        ];
    }

    private function bindHardwareSeat(
        PDO $pdo,
        int $licenseId,
        string $hwid,
        string $hostname,
        int $maxSeats,
        string $clientIp = '127.0.0.1',
        string $countryCode = 'US',
        string $countryName = 'United States'
    ): array {
        $stmt = $pdo->prepare('SELECT * FROM HardwareBindings WHERE licenseId = :lid');
        $stmt->execute([':lid' => $licenseId]);
        $bindings = $stmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($bindings as $b) {
            if ($b['hardwareDigest'] === $hwid) {
                $upd = $pdo->prepare('UPDATE HardwareBindings SET lastSeenAt = :dt, hostname = :h, clientIp = :ip, countryCode = :cc, countryName = :cn WHERE id = :id');
                $upd->execute([
                    ':dt' => Carbon::now()->toISOString(),
                    ':h' => $hostname,
                    ':ip' => $clientIp,
                    ':cc' => $countryCode,
                    ':cn' => $countryName,
                    ':id' => $b['id'],
                ]);
                return ['isBound' => true, 'activeCount' => count($bindings)];
            }
        }

        if (count($bindings) >= $maxSeats) {
            return [
                'isBound' => false,
                'message' => "Maximum machine seat limit ({$maxSeats}) reached for this license.",
                'boundSeats' => $bindings,
                'activeCount' => count($bindings),
            ];
        }

        $ins = $pdo->prepare('INSERT INTO HardwareBindings (licenseId, hardwareDigest, hostname, boundAt, lastSeenAt, clientIp, countryCode, countryName) VALUES (:lid, :hwid, :h, :dt, :dt, :ip, :cc, :cn)');
        $ins->execute([
            ':lid' => $licenseId,
            ':hwid' => $hwid,
            ':h' => $hostname,
            ':dt' => Carbon::now()->toISOString(),
            ':ip' => $clientIp,
            ':cc' => $countryCode,
            ':cn' => $countryName,
        ]);

        return ['isBound' => true, 'activeCount' => count($bindings) + 1];
    }

    private function resolveCountryName(string $code): string
    {
        $map = [
            'US' => 'United States',
            'GB' => 'United Kingdom',
            'DE' => 'Germany',
            'BD' => 'Bangladesh',
            'CA' => 'Canada',
            'FR' => 'France',
            'AU' => 'Australia',
            'IN' => 'India',
            'JP' => 'Japan',
            'SG' => 'Singapore',
            'NL' => 'Netherlands',
            'BR' => 'Brazil',
        ];
        return $map[$code] ?? "Country ({$code})";
    }

    private function logSecurityEvent(PDO $pdo, string $serial, string $ip, string $hwid, array $reasons): void
    {
        $ins = $pdo->prepare('INSERT INTO SecurityAuditEvents (serialValue, clientIp, hardwareDigest, reasons, createdAt) VALUES (:s, :ip, :hwid, :r, :dt)');
        $ins->execute([
            ':s' => $serial,
            ':ip' => $ip,
            ':hwid' => $hwid,
            ':r' => json_encode($reasons),
            ':dt' => Carbon::now()->toISOString(),
        ]);
    }

    private function ensureShardSchema(string $shardPath): void
    {
        if (!file_exists($shardPath)) {
            touch($shardPath);
        }
        $pdo = new PDO("sqlite:{$shardPath}");
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
            lastSeenAt TEXT NOT NULL,
            clientIp TEXT DEFAULT "127.0.0.1",
            countryCode TEXT DEFAULT "US",
            countryName TEXT DEFAULT "United States"
        );');
        // Gracefully migrate older schemas if columns are missing
        try {
            $pdo->exec("ALTER TABLE HardwareBindings ADD COLUMN clientIp TEXT DEFAULT '127.0.0.1';");
        } catch (\Throwable) {}
        try {
            $pdo->exec("ALTER TABLE HardwareBindings ADD COLUMN countryCode TEXT DEFAULT 'US';");
        } catch (\Throwable) {}
        try {
            $pdo->exec("ALTER TABLE HardwareBindings ADD COLUMN countryName TEXT DEFAULT 'United States';");
        } catch (\Throwable) {}
        $pdo->exec('CREATE TABLE IF NOT EXISTS SecurityAuditEvents (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            serialValue TEXT NOT NULL,
            clientIp TEXT NOT NULL,
            hardwareDigest TEXT NOT NULL,
            reasons TEXT NOT NULL,
            createdAt TEXT NOT NULL
        );');
    }
}
