<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Product;
use App\Services\AutoQuarantineService;
use App\Services\JwtTokenService;
use App\Services\TamperManifestService;
use Illuminate\Support\Carbon;
use PDO;
use Tests\TestCase;

/** Fraud Phase 3: minVersion floor and auto-quarantine on live concurrency only. */
final class FraudPhase3Test extends TestCase
{
    private string $slug = 'fraud-phase3-test';
    private string $serial = 'FRAUD-PHASE3-TEST-001';
    private ?string $shardPath = null;

    protected function setUp(): void
    {
        parent::setUp();
        $this->shardPath = database_path("product_{$this->slug}.sqlite");
        if (file_exists((string) $this->shardPath)) {
            unlink((string) $this->shardPath);
        }
        $keys = (new JwtTokenService())->generateEd25519KeyPair();
        Product::create(['userId' => 1, 'productSlug' => $this->slug, 'productName' => 'Fraud Phase 3', 'category' => 'DesktopApp', 'ed25519PublicKey' => $keys['public'], 'ed25519PrivateKey' => $keys['private'], 'checkIntervalDays' => 7, 'isActive' => true]);
        $this->createShard();
    }

    protected function tearDown(): void
    {
        if ($this->shardPath !== null && file_exists($this->shardPath)) {
            unlink($this->shardPath);
        }
        parent::tearDown();
    }

    public function test_old_manifest_fails_after_newer_floor_is_set(): void
    {
        $service = new TamperManifestService(new JwtTokenService());
        $product = Product::where('productSlug', $this->slug)->first();
        $files = [['path' => 'app.exe', 'sha256' => hash('sha256', 'good'), 'bytes' => 4]];
        $old = $service->toPublicArray($service->signManifest($product, '1.0.0', $files, '1.0.0'));
        $this->assertNull($service->checkMinVersion($this->slug, '1.0.0'));
        $service->signManifest($product->fresh(), '2.0.0', $files, '2.0.0');
        $this->assertSame('VersionBelowMinimum', $service->checkMinVersion($this->slug, '1.0.0'));
        $this->assertNull($service->checkMinVersion($this->slug, '2.0.0'));
        $good = $this->postJson("/Api/v1/products/{$this->slug}/tamper-verify", ['manifest' => $old, 'files' => $files]);
        $good->assertStatus(200);
        $this->assertFalse($good->json('Results.isValid'));
        $this->assertSame('VersionBelowMinimum', $good->json('Results.reason'));
    }

    public function test_auto_quarantine_only_on_recent_live_concurrency(): void
    {
        $service = new AutoQuarantineService();
        $this->insertBinding('hw-stale-a', Carbon::now()->subHours(2)->toISOString());
        $this->insertBinding('hw-stale-b', Carbon::now()->subHours(2)->toISOString());
        $dry = $service->quarantineShard((string) $this->shardPath, 45, true);
        $this->assertSame([], $dry['quarantined']);
        $this->assertSame(0, $this->revokedCount());
        $this->insertBinding('hw-live-a', Carbon::now()->toISOString());
        $this->insertBinding('hw-live-b', Carbon::now()->toISOString());
        $result = $service->quarantineShard((string) $this->shardPath, 45, false);
        $this->assertSame([$this->serial], $result['quarantined']);
        $this->assertSame(1, $this->revokedCount());
    }

    public function test_tamper_events_alone_do_not_auto_quarantine(): void
    {
        $service = new AutoQuarantineService();
        $now = Carbon::now()->toISOString();
        $pdo = $this->shard();
        $pdo->exec("INSERT INTO SecurityAuditEvents (serialValue, clientIp, hardwareDigest, reasons, createdAt) VALUES ('{$this->serial}', '127.0.0.1', 'hw-a', '\"Tamper: FileHashMismatch\"', '{$now}')");
        $result = $service->quarantineShard((string) $this->shardPath, 45, false);
        $this->assertSame([], $result['quarantined']);
        $this->assertSame(0, $this->revokedCount());
    }

    private function createShard(): void
    {
        $pdo = $this->shard();
        $pdo->exec('CREATE TABLE ProductLicenses (id INTEGER PRIMARY KEY AUTOINCREMENT, serialValue TEXT UNIQUE NOT NULL, dealCode TEXT NOT NULL, allowedSeats INTEGER DEFAULT 1, isActive INTEGER DEFAULT 1, isRevoked INTEGER DEFAULT 0, expiresAt TEXT NULL, createdAt TEXT NOT NULL);');
        $pdo->exec('CREATE TABLE MachineBindings (MachineBindingId INTEGER PRIMARY KEY AUTOINCREMENT, LicenseId INTEGER NOT NULL, FingerprintHash TEXT NOT NULL, Hostname TEXT DEFAULT "Client PC", ClientIp TEXT DEFAULT "127.0.0.1", CountryCode TEXT DEFAULT "US", CountryName TEXT DEFAULT "United States", FirstSeenAt TEXT NOT NULL, LastSeenAt TEXT NOT NULL, ReleasedAt TEXT NULL, RebindCooldownUntil TEXT NULL, CreatedAt TEXT NOT NULL, UpdatedAt TEXT NOT NULL);');
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

    private function revokedCount(): int
    {
        $row = $this->shard()->query('SELECT COUNT(*) as cnt FROM ProductLicenses WHERE isRevoked = 1')->fetch(PDO::FETCH_ASSOC);

        return (int) ($row['cnt'] ?? 0);
    }

    private function shard(): PDO
    {
        return new PDO("sqlite:{$this->shardPath}");
    }
}
