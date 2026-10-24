<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Product;
use App\Models\ProductDeal;
use Illuminate\Support\Carbon;
use PDO;

/**
 * Service managing client license handshakes, node-locking, and seat limits.
 */
class ClientHandshakeService
{
    public function __construct(
        private readonly JwtTokenService $jwtService,
        private readonly RiskAssessmentService $riskService,
    ) {}

    /** Open or initialize shard SQLite PDO connection. */
    private function openShardPdo(string $productSlug): PDO
    {
        $shardPath = database_path("product_{$productSlug}.sqlite");
        $this->ensureShardSchema($shardPath);

        return new PDO("sqlite:{$shardPath}");
    }

    /** Verify client license and mint periodic Ed25519 offline token. */
    public function verifyClient(string $productSlug, string $serial, string $hwid, string $ip, string $h = 'Client PC', ?string $cc = null, ?string $cn = null): array
    {
        $product = $this->resolveActiveProduct($productSlug);
        if (!$product) {
            return ['isValid' => false, 'errorCode' => 'ProductNotFound', 'message' => "Product '{$productSlug}' not found."];
        }
        $pdo = $this->openShardPdo($productSlug);
        $license = $this->findOrRegisterLicense($pdo, $serial, $product);
        if (!$license['isActive'] || $license['isRevoked']) {
            return ['isValid' => false, 'errorCode' => 'LicenseRevoked', 'message' => 'License revoked/suspended.'];
        }

        return $this->processVerification($pdo, $product, $license, $serial, $hwid, $ip, $h, $cc, $cn);
    }

    /** Activate license on a hardware device seat. */
    public function activate(string $productSlug, string $serial, string $hwid, string $ip, string $h = 'Client PC'): array
    {
        return $this->verifyClient($productSlug, $serial, $hwid, $ip, $h);
    }

    /** Deactivate license and release hardware binding seat. */
    public function deactivate(string $productSlug, string $serialValue, string $hardwareDigest): array
    {
        $product = $this->resolveActiveProduct($productSlug);
        if (!$product) {
            return ['isSuccess' => false, 'errorCode' => 'ProductNotFound', 'message' => "Product '{$productSlug}' not found."];
        }
        $pdo = $this->openShardPdo($productSlug);
        $license = $this->findLicenseBySerial($pdo, $serialValue);
        if (!$license) {
            return ['isSuccess' => false, 'errorCode' => 'LicenseNotFound', 'message' => 'License not found.'];
        }

        return $this->releaseMachineBinding($pdo, (int) $license['id'], $hardwareDigest, (int) $license['allowedSeats']);
    }

    /** Send periodic heartbeat to refresh LastSeenAt and Ed25519 token. */
    public function heartbeat(string $productSlug, string $serialValue, string $hardwareDigest): array
    {
        $product = $this->resolveActiveProduct($productSlug);
        if (!$product) {
            return ['isSuccess' => false, 'errorCode' => 'ProductNotFound', 'message' => "Product '{$productSlug}' not found."];
        }
        $pdo = $this->openShardPdo($productSlug);
        $license = $this->findLicenseBySerial($pdo, $serialValue);
        if (!$license) {
            return ['isSuccess' => false, 'errorCode' => 'LicenseNotFound', 'message' => 'License not found.'];
        }

        return $this->touchHeartbeatBinding($pdo, $license, $hardwareDigest, $product);
    }

    /** Resolve active product by slug or default active product. */
    public function resolveActiveProduct(string $slug): ?Product
    {
        $product = Product::with('deals')->where('productSlug', $slug)->where('isActive', true)->first();

        return $product ?: Product::with('deals')->where('isActive', true)->first();
    }

    /** Process verification pipeline after license is validated. */
    private function processVerification(PDO $pdo, Product $product, array $license, string $s, string $hw, string $ip, string $h, ?string $cc, ?string $cn): array
    {
        $risk = $this->riskService->evaluateAttempt($s, $ip, $hw);
        if (!$risk['isPermitted']) {
            $this->logSecurityEvent($pdo, $s, $ip, $hw, $risk['reasons']);
            return ['isValid' => false, 'errorCode' => 'SecurityRiskDetected', 'message' => 'Anomaly detected.', 'reasons' => $risk['reasons']];
        }
        $rCc = strtoupper(trim((string) ($cc ?: 'US')));
        $rCn = trim((string) ($cn ?: $this->resolveCountryName($rCc)));
        $res = $this->bindMachineSeat($pdo, (int) $license['id'], $hw, $h, (int) $license['allowedSeats'], $ip, $rCc, $rCn);

        return $res['isBound'] ? $this->buildVerificationResult($product, $license, $s, $hw, $res['activeCount']) : $res;
    }

    /** Build successful verification and activation payload. */
    private function buildVerificationResult(Product $product, array $license, string $serial, string $hwid, int $activeSeats): array
    {
        $product = $this->ensureProductEd25519Keys($product);
        $days = (int) $product->checkIntervalDays;
        $token = $this->jwtService->issueEd25519Token($serial, $hwid, (string) $license['dealCode'], (int) $license['allowedSeats'], (string) $product->ed25519PrivateKey);

        return [
            'isValid' => true, 'isActivated' => true, 'serialValue' => $serial, 'productSlug' => (string) $product->productSlug,
            'dealCode' => (string) $license['dealCode'], 'checkIntervalDays' => $days, 'nextCheckBefore' => Carbon::now()->addDays($days)->toISOString(),
            'allowedSeats' => (int) $license['allowedSeats'], 'activeSeats' => $activeSeats, 'offlineToken' => $token, 'ed25519PublicKey' => (string) $product->ed25519PublicKey,
        ];
    }

    /** Find license row by serial value. */
    private function findLicenseBySerial(PDO $pdo, string $serial): ?array
    {
        $stmt = $pdo->prepare('SELECT * FROM ProductLicenses WHERE serialValue = :serial LIMIT 1');
        $stmt->execute([':serial' => $serial]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ?: null;
    }

    /** Touch heartbeat binding timestamp without incrementing seats. */
    private function touchHeartbeatBinding(PDO $pdo, array $license, string $hwid, Product $product): array
    {
        $binding = $this->findActiveMachineBinding($pdo, (int) $license['id'], $hwid);
        if ($binding === null) {
            return ['isSuccess' => false, 'errorCode' => 'MachineNotBound', 'message' => 'Machine is not actively bound. Please activate first.'];
        }
        $now = Carbon::now();
        $upd = $pdo->prepare('UPDATE MachineBindings SET LastSeenAt = :dt, UpdatedAt = :dt WHERE MachineBindingId = :id');
        $upd->execute([':dt' => $now->toISOString(), ':id' => $binding['MachineBindingId']]);
        $product = $this->ensureProductEd25519Keys($product);
        $checkInterval = (int) $product->checkIntervalDays;
        $token = $this->jwtService->issueEd25519Token((string) $license['serialValue'], $hwid, (string) $license['dealCode'], (int) $license['allowedSeats'], (string) $product->ed25519PrivateKey);

        return ['isSuccess' => true, 'lastSeenAt' => $now->toISOString(), 'checkIntervalDays' => $checkInterval, 'nextCheckBefore' => $now->copy()->addDays($checkInterval)->toISOString(), 'offlineToken' => $token];
    }

    /** Release machine binding and restore available seat. */
    private function releaseMachineBinding(PDO $pdo, int $licenseId, string $hwid, int $allowedSeats): array
    {
        $binding = $this->findActiveMachineBinding($pdo, $licenseId, $hwid);
        if ($binding === null) {
            return ['isSuccess' => false, 'errorCode' => 'MachineNotBound', 'message' => 'No active machine binding found for this hardware fingerprint.'];
        }
        $now = Carbon::now();
        $upd = $pdo->prepare('UPDATE MachineBindings SET ReleasedAt = :rel, RebindCooldownUntil = :cool, UpdatedAt = :upd WHERE MachineBindingId = :id');
        $upd->execute([':rel' => $now->toISOString(), ':cool' => $now->copy()->addMinutes(15)->toISOString(), ':upd' => $now->toISOString(), ':id' => $binding['MachineBindingId']]);
        $del = $pdo->prepare('DELETE FROM HardwareBindings WHERE licenseId = :lid AND hardwareDigest = :hwid');
        $del->execute([':lid' => $licenseId, ':hwid' => $hwid]);
        $remaining = $this->countActiveMachineBindings($pdo, $licenseId);

        return ['isSuccess' => true, 'isDeactivated' => true, 'releasedAt' => $now->toISOString(), 'allowedSeats' => $allowedSeats, 'activeSeats' => $remaining, 'availableSeats' => max(0, $allowedSeats - $remaining)];
    }

    /** Bind or refresh machine seat in MachineBindings. */
    private function bindMachineSeat(PDO $pdo, int $licenseId, string $hwid, string $hostname, int $maxSeats, string $clientIp, string $countryCode, string $countryName): array
    {
        $existing = $this->findActiveMachineBinding($pdo, $licenseId, $hwid);
        if ($existing !== null) {
            $this->updateMachineLastSeen($pdo, (int) $existing['MachineBindingId'], $hostname, $clientIp, $countryCode, $countryName);
            $activeCount = $this->countActiveMachineBindings($pdo, $licenseId);
            return ['isBound' => true, 'activeCount' => $activeCount];
        }

        return $this->attemptNewMachineBinding($pdo, $licenseId, $hwid, $hostname, $maxSeats, $clientIp, $countryCode, $countryName);
    }

    /** Attempt to bind a new machine seat subject to limits. */
    private function attemptNewMachineBinding(PDO $pdo, int $licenseId, string $hwid, string $hostname, int $maxSeats, string $clientIp, string $countryCode, string $countryName): array
    {
        $activeCount = $this->countActiveMachineBindings($pdo, $licenseId);
        if ($activeCount >= $maxSeats) {
            return $this->seatLimitPayload($pdo, $licenseId, $maxSeats, $activeCount);
        }
        $this->insertMachineBinding($pdo, $licenseId, $hwid, $hostname, $clientIp, $countryCode, $countryName);

        return ['isBound' => true, 'activeCount' => $activeCount + 1];
    }

    /** Return payload for seat limit exceeded state. */
    private function seatLimitPayload(PDO $pdo, int $licenseId, int $maxSeats, int $activeCount): array
    {
        return [
            'isValid' => false, 'isBound' => false, 'errorCode' => 'SeatLimitExceeded',
            'message' => "Maximum machine seat limit ({$maxSeats}) reached for this license.",
            'boundSeats' => $this->listActiveMachineBindings($pdo, $licenseId),
            'activeCount' => $activeCount,
        ];
    }

    /** Find active binding for given license and hardware digest. */
    private function findActiveMachineBinding(PDO $pdo, int $licenseId, string $hwid): ?array
    {
        $stmt = $pdo->prepare('SELECT * FROM MachineBindings WHERE LicenseId = :lid AND FingerprintHash = :hwid AND ReleasedAt IS NULL LIMIT 1');
        $stmt->execute([':lid' => $licenseId, ':hwid' => $hwid]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ?: null;
    }

    /** Update LastSeenAt timestamp on existing machine binding. */
    private function updateMachineLastSeen(PDO $pdo, int $bindingId, string $hostname, string $clientIp, string $countryCode, string $countryName): void
    {
        $upd = $pdo->prepare('UPDATE MachineBindings SET LastSeenAt = :dt, Hostname = :h, ClientIp = :ip, CountryCode = :cc, CountryName = :cn, UpdatedAt = :dt WHERE MachineBindingId = :id');
        $upd->execute([':dt' => Carbon::now()->toISOString(), ':h' => $hostname, ':ip' => $clientIp, ':cc' => $countryCode, ':cn' => $countryName, ':id' => $bindingId]);
    }

    /** Count active machine bindings for a license. */
    private function countActiveMachineBindings(PDO $pdo, int $licenseId): int
    {
        $stmt = $pdo->prepare('SELECT COUNT(*) as cnt FROM MachineBindings WHERE LicenseId = :lid AND ReleasedAt IS NULL');
        $stmt->execute([':lid' => $licenseId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return (int) ($row['cnt'] ?? 0);
    }

    /** List all active machine bindings for a license. */
    private function listActiveMachineBindings(PDO $pdo, int $licenseId): array
    {
        $stmt = $pdo->prepare('SELECT * FROM MachineBindings WHERE LicenseId = :lid AND ReleasedAt IS NULL');
        $stmt->execute([':lid' => $licenseId]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /** Insert a new machine binding row into MachineBindings. */
    private function insertMachineBinding(PDO $pdo, int $licenseId, string $hwid, string $hostname, string $clientIp, string $countryCode, string $countryName): void
    {
        $now = Carbon::now()->toISOString();
        $ins = $pdo->prepare('INSERT INTO MachineBindings (LicenseId, FingerprintHash, Hostname, ClientIp, CountryCode, CountryName, FirstSeenAt, LastSeenAt, ReleasedAt, RebindCooldownUntil, CreatedAt, UpdatedAt) VALUES (:lid, :hwid, :h, :ip, :cc, :cn, :dt, :dt, NULL, NULL, :dt, :dt)');
        $ins->execute([':lid' => $licenseId, ':hwid' => $hwid, ':h' => $hostname, ':ip' => $clientIp, ':cc' => $countryCode, ':cn' => $countryName, ':dt' => $now]);
        $this->mirrorToHardwareBindings($pdo, $licenseId, $hwid, $hostname, $clientIp, $countryCode, $countryName, $now);
    }

    /** Mirror to legacy HardwareBindings table for backwards compatibility. */
    private function mirrorToHardwareBindings(PDO $pdo, int $licenseId, string $hwid, string $hostname, string $clientIp, string $countryCode, string $countryName, string $now): void
    {
        $ins = $pdo->prepare('INSERT INTO HardwareBindings (licenseId, hardwareDigest, hostname, boundAt, lastSeenAt, clientIp, countryCode, countryName) VALUES (:lid, :hwid, :h, :dt, :dt, :ip, :cc, :cn)');
        $ins->execute([':lid' => $licenseId, ':hwid' => $hwid, ':h' => $hostname, ':dt' => $now, ':ip' => $clientIp, ':cc' => $countryCode, ':cn' => $countryName]);
    }

    /** Find or auto-register license row. */
    private function findOrRegisterLicense(PDO $pdo, string $serial, Product $product): array
    {
        $stmt = $pdo->prepare('SELECT * FROM ProductLicenses WHERE serialValue = :serial LIMIT 1');
        $stmt->execute([':serial' => $serial]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ?: $this->insertNewProductLicense($pdo, $serial, $product);
    }

    /** Insert new license record into ProductLicenses. */
    private function insertNewProductLicense(PDO $pdo, string $serial, Product $product): array
    {
        $deal = $product->deals->first();
        $seats = $deal ? (int) $deal->allowedMachineSeats : 1;
        $dealCode = $deal ? $deal->dealCode : 'DEFAULT';
        $ins = $pdo->prepare('INSERT INTO ProductLicenses (serialValue, dealCode, allowedSeats, isActive, isRevoked, createdAt) VALUES (:s, :d, :seats, 1, 0, :dt)');
        $ins->execute([':s' => $serial, ':d' => $dealCode, ':seats' => $seats, ':dt' => Carbon::now()->toISOString()]);

        return ['id' => (int) $pdo->lastInsertId(), 'serialValue' => $serial, 'dealCode' => $dealCode, 'allowedSeats' => $seats, 'isActive' => 1, 'isRevoked' => 0];
    }

    /** Ensure Product has valid Ed25519 signing keys. */
    public function ensureProductEd25519Keys(Product $product): Product
    {
        $rawPrivate = base64_decode((string) $product->ed25519PrivateKey, true);
        if ($rawPrivate !== false && strlen($rawPrivate) === 64) {
            return $product;
        }
        $keyPair = $this->jwtService->generateEd25519KeyPair();
        $product->update([
            'ed25519PublicKey' => $keyPair['public'],
            'ed25519PrivateKey' => $keyPair['private'],
        ]);

        return $product;
    }

    /** Log security audit anomaly event. */
    private function logSecurityEvent(PDO $pdo, string $serial, string $ip, string $hwid, array $reasons): void
    {
        $ins = $pdo->prepare('INSERT INTO SecurityAuditEvents (serialValue, clientIp, hardwareDigest, reasons, createdAt) VALUES (:s, :ip, :hwid, :r, :dt)');
        $ins->execute([':s' => $serial, ':ip' => $ip, ':hwid' => $hwid, ':r' => json_encode($reasons), ':dt' => Carbon::now()->toISOString()]);
    }

    /** Ensure shard database tables exist. */
    private function ensureShardSchema(string $shardPath): void
    {
        if (!file_exists($shardPath)) {
            touch($shardPath);
        }
        $pdo = new PDO("sqlite:{$shardPath}");
        $this->createProductLicensesTable($pdo);
        $this->createMachineBindingsTable($pdo);
        $this->createHardwareBindingsTable($pdo);
        $this->createSecurityAuditTable($pdo);
    }

    /** Create ProductLicenses table in shard. */
    private function createProductLicensesTable(PDO $pdo): void
    {
        $pdo->exec('CREATE TABLE IF NOT EXISTS ProductLicenses (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            serialValue TEXT UNIQUE NOT NULL,
            dealCode TEXT NOT NULL,
            allowedSeats INTEGER DEFAULT 1,
            isActive INTEGER DEFAULT 1,
            isRevoked INTEGER DEFAULT 0,
            createdAt TEXT NOT NULL
        );');
    }

    /** Create MachineBindings table in shard. */
    private function createMachineBindingsTable(PDO $pdo): void
    {
        $sql = "CREATE TABLE IF NOT EXISTS MachineBindings (
            MachineBindingId INTEGER PRIMARY KEY AUTOINCREMENT, LicenseId INTEGER NOT NULL,
            FingerprintHash TEXT NOT NULL, Hostname TEXT DEFAULT 'Client PC', ClientIp TEXT DEFAULT '127.0.0.1',
            CountryCode TEXT DEFAULT 'US', CountryName TEXT DEFAULT 'United States', FirstSeenAt TEXT NOT NULL,
            LastSeenAt TEXT NOT NULL, ReleasedAt TEXT NULL, RebindCooldownUntil TEXT NULL, CreatedAt TEXT NOT NULL, UpdatedAt TEXT NOT NULL
        );";
        $pdo->exec($sql);
    }

    /** Create legacy HardwareBindings table in shard. */
    private function createHardwareBindingsTable(PDO $pdo): void
    {
        $pdo->exec("CREATE TABLE IF NOT EXISTS HardwareBindings (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            licenseId INTEGER NOT NULL,
            hardwareDigest TEXT NOT NULL,
            hostname TEXT DEFAULT 'Client PC',
            boundAt TEXT NOT NULL,
            lastSeenAt TEXT NOT NULL,
            clientIp TEXT DEFAULT '127.0.0.1',
            countryCode TEXT DEFAULT 'US',
            countryName TEXT DEFAULT 'United States'
        );");
    }

    /** Create SecurityAuditEvents table in shard. */
    private function createSecurityAuditTable(PDO $pdo): void
    {
        $pdo->exec('CREATE TABLE IF NOT EXISTS SecurityAuditEvents (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            serialValue TEXT NOT NULL,
            clientIp TEXT NOT NULL,
            hardwareDigest TEXT NOT NULL,
            reasons TEXT NOT NULL,
            createdAt TEXT NOT NULL
        );');
    }

    /** Resolve country code to display name. */
    private function resolveCountryName(string $code): string
    {
        $map = [
            'US' => 'United States', 'GB' => 'United Kingdom', 'DE' => 'Germany',
            'BD' => 'Bangladesh', 'CA' => 'Canada', 'FR' => 'France', 'AU' => 'Australia',
        ];

        return $map[$code] ?? "Country ({$code})";
    }
}
