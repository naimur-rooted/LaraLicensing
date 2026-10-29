<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Http\Controllers\Developer\FraudScoreController;
use App\Models\Product;
use App\Services\FraudScoringService;
use App\Services\HeartbeatCullService;
use App\Services\JwtTokenService;
use App\Services\LicenceGateService;
use Illuminate\Support\Carbon;
use PDO;
use Tests\TestCase;

/** Fraud Phase 2: gate, heartbeat cull, and fraud scoring. */
final class FraudPhase2Test extends TestCase
{
    private string $slug = 'fraud-phase2-test';
    private string $serial = 'FRAUD-PHASE2-TEST-001';
    private ?string $shardPath = null;

    protected function setUp(): void
    {
        parent::setUp();
        $this->shardPath = database_path("product_{$this->slug}.sqlite");
        if (file_exists((string) $this->shardPath)) {
            unlink((string) $this->shardPath);
        }
        $keys = (new JwtTokenService())->generateEd25519KeyPair();
        Product::create(['userId' => 1, 'productSlug' => $this->slug, 'productName' => 'Fraud Phase 2', 'category' => 'DesktopApp', 'ed25519PublicKey' => $keys['public'], 'ed25519PrivateKey' => $keys['private'], 'checkIntervalDays' => 7, 'isActive' => true]);
        $this->createShard();
    }

    protected function tearDown(): void
    {
        if ($this->shardPath !== null && file_exists($this->shardPath)) {
            unlink($this->shardPath);
        }
        parent::tearDown();
    }

    public function test_fraud_score_controller_exists(): void
    {
        $this->assertTrue(class_exists(FraudScoreController::class));
    }

    public function test_gate_valid_revoked_expired_and_not_found(): void
    {
        $gate = new LicenceGateService();
        $this->assertSame('Valid', $gate->check($this->slug, $this->serial)['status']);
        $this->assertSame('LicenseNotFound', $gate->check($this->slug, 'UNKNOWN-SERIAL')['status']);
        $this->shard()->exec("UPDATE ProductLicenses SET isRevoked = 1 WHERE serialValue = '{$this->serial}'");
        $this->assertSame('LicenseRevoked', $gate->check($this->slug, $this->serial)['status']);
        $past = Carbon::now()->subDay()->toISOString();
        $this->shard()->exec("UPDATE ProductLicenses SET isRevoked = 0, expiresAt = '{$past}' WHERE serialValue = '{$this->serial}'");
        $this->assertSame('LicenseExpired', $gate->check($this->slug, $this->serial)['status']);
    }

    public function test_cull_releases_stale_and_keeps_live_and_dry_run_changes_nothing(): void
    {
        $cull = new HeartbeatCullService();
        $this->insertBinding('hw-stale', Carbon::now()->subHours(2)->toISOString());
        $this->insertBinding('hw-live', Carbon::now()->toISOString());
        $dry = $cull->cullShard((string) $this->shardPath, 45, true);
        $this->assertSame(1, $dry['culled']);
        $this->assertSame(2, $this->activeBindingCount());
        $result = $cull->cullShard((string) $this->shardPath, 45, false);
        $this->assertSame(1, $result['culled']);
        $this->assertSame(1, $this->activeBindingCount());
        $this->assertSame(1, $this->auditCount());
    }

    public function test_scoring_recommends_quarantine_review_on_tamper_and_concurrency(): void
    {
        $scoring = new FraudScoringService(new LicenceGateService());
        $clean = $scoring->score($this->slug, $this->serial);
        $this->assertSame(0, $clean['score']);
        $this->assertSame('none', $clean['recommendation']);
        $this->insertTamperEvent();
        $this->insertTamperEvent();
        $this->insertTamperEvent();
        $this->insertBinding('hw-a', Carbon::now()->toISOString());
        $this->insertBinding('hw-b', Carbon::now()->toISOString());
        $scored = $scoring->score($this->slug, $this->serial);
        $this->assertSame(3, $scored['tamperCount']);
        $this->assertSame(2, $scored['activeSeats']);
        $this->assertSame('quarantine_review', $scored['recommendation']);
        $this->assertTrue($scored['score'] >= 70);
    }

    private function createShard(): void
    {
        $pdo = $this->shard();
        $pdo->exec('CREATE TABLE ProductLicenses (id INTEGER PRIMARY KEY AUTOINCREMENT, serialValue TEXT UNIQUE NOT NULL, dealCode TEXT NOT NULL, allowedSeats INTEGER DEFAULT 1, isActive INTEGER DEFAULT 1, isRevoked INTEGER DEFAULT 0, expiresAt TEXT NULL, createdAt TEXT NOT NULL);');
        $pdo->exec('CREATE TABLE MachineBindings (MachineBindingId INTEGER PRIMARY KEY AUTOINCREMENT, LicenseId INTEGER NOT NULL, FingerprintHash TEXT NOT NULL, Hostname TEXT DEFAULT "Client PC", ClientIp TEXT DEFAULT "127.0.0.1", CountryCode TEXT DEFAULT "US", CountryName TEXT DEFAULT "United States", FirstSeenAt TEXT NOT NULL, LastSeenAt TEXT NOT NULL, ReleasedAt TEXT NULL, RebindCooldownUntil TEXT NULL, CreatedAt TEXT NOT NULL, UpdatedAt TEXT NOT NULL);');
        $pdo->exec('CREATE TABLE HardwareBindings (id INTEGER PRIMARY KEY AUTOINCREMENT, licenseId INTEGER NOT NULL, hardwareDigest TEXT NOT NULL, hostname TEXT DEFAULT "Client PC", boundAt TEXT NOT NULL, lastSeenAt TEXT NOT NULL);');
        $pdo->exec('CREATE TABLE SecurityAuditEvents (id INTEGER PRIMARY KEY AUTOINCREMENT, serialValue TEXT NOT NULL, clientIp TEXT NOT NULL, hardwareDigest TEXT NOT NULL, reasons TEXT NOT NULL, createdAt TEXT NOT NULL);');
        $now = Carbon::now()->toISOString();
        $pdo->exec("INSERT INTO ProductLicenses (serialValue, dealCode, allowedSeats, isActive, isRevoked, expiresAt, createdAt) VALUES ('{$this->serial}', 'DAILY', 1, 1, 0, NULL, '{$now}')");
    }

    private function insertBinding(string $hwid, string $lastSeen): void
    {
        $pdo = $this->shard();
        $now = Carbon::now()->toISOString();
        $pdo->exec("INSERT INTO MachineBindings (LicenseId, FingerprintHash, FirstSeenAt, LastSeenAt, ReleasedAt, CreatedAt, UpdatedAt) VALUES (1, '{$hwid}', '{$now}', '{$lastSeen}', NULL, '{$now}', '{$now}')");
    }

    private function insertTamperEvent(): void
    {
        $pdo = $this->shard();
        $now = Carbon::now()->toISOString();
        $pdo->exec("INSERT INTO SecurityAuditEvents (serialValue, clientIp, hardwareDigest, reasons, createdAt) VALUES ('{$this->serial}', '127.0.0.1', 'hw-a', '\"Tamper: FileHashMismatch\"', '{$now}')");
    }

    private function activeBindingCount(): int
    {
        $row = $this->shard()->query('SELECT COUNT(*) as cnt FROM MachineBindings WHERE ReleasedAt IS NULL')->fetch(PDO::FETCH_ASSOC);

        return (int) ($row['cnt'] ?? 0);
    }

    private function auditCount(): int
    {
        $row = $this->shard()->query("SELECT COUNT(*) as cnt FROM SecurityAuditEvents WHERE reasons LIKE '%Heartbeat cull%'")->fetch(PDO::FETCH_ASSOC);

        return (int) ($row['cnt'] ?? 0);
    }

    private function shard(): PDO
    {
        return new PDO("sqlite:{$this->shardPath}");
    }
}
