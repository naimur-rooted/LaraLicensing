<?php

declare(strict_types=1);

namespace Tests\Feature\Verification;

use App\Models\Product;
use App\Services\LicenseBackfillService;
use Database\Seeders\ProductSeeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use PDO;
use Tests\TestCase;

/**
 * Phase 4 item 1: Serials-only gateway keys must verify/activate after
 * just-in-time promotion, unknown serials must still 404 with no row
 * created, revoked Serials keys must never promote as active, and the
 * backfill routine must be idempotent with correct seats/expiry.
 *
 * Fixtures are Serials-only on purpose: the shard is created here with
 * just a Serials table (no ProductLicenses row), mirroring a key issued
 * by a gateway before the Phase 3 dual-write existed.
 */
class GatewayLicenseBackfillTest extends TestCase
{
    private const HWID_A = 'e3b0c44298fc1c149afbf4c8996fb92427ae41e4649b934ca495991b7852b855';

    private Product $product;
    private string $shardPath;

    protected function setUp(): void
    {
        parent::setUp();
        if (!Schema::connection('root')->hasTable('Products')) {
            $this->artisan('migrate', ['--path' => 'database/migrations/root/2026_10_20_000001_create_root_products_table.php']);
        }
        $this->seed(ProductSeeder::class);
        $this->product = Product::firstOrFail();
        $this->shardPath = database_path("product_{$this->product->productSlug}.sqlite");
        @unlink($this->shardPath);
        $this->createSerialsTable();
    }

    protected function tearDown(): void
    {
        @unlink($this->shardPath);
        @unlink(base_path("repodb/product_{$this->product->productSlug}.sqlite"));
        parent::tearDown();
    }

    private function createSerialsTable(): void
    {
        touch($this->shardPath);
        $pdo = new PDO("sqlite:{$this->shardPath}");
        $pdo->exec('CREATE TABLE IF NOT EXISTS Serials (
            SerialId INTEGER PRIMARY KEY AUTOINCREMENT,
            SerialValue VARCHAR(128) NOT NULL UNIQUE,
            LicenseKey VARCHAR(128),
            ProductSlug VARCHAR(64) NOT NULL,
            CustomerEmail VARCHAR(128) NOT NULL,
            IsRevoked INTEGER NOT NULL DEFAULT 0,
            IssuedAt DATETIME,
            CreatedAt DATETIME,
            UpdatedAt DATETIME
        );');
    }

    private function seedSerial(string $serial, int $revoked = 0, ?string $issuedAt = null): void
    {
        $now = $issuedAt ?? now()->toDateTimeString();
        $pdo = new PDO("sqlite:{$this->shardPath}");
        $stmt = $pdo->prepare('INSERT INTO Serials (SerialValue, LicenseKey, ProductSlug, CustomerEmail, IsRevoked, IssuedAt, CreatedAt, UpdatedAt) VALUES (?, ?, ?, ?, ?, ?, ?, ?)');
        $stmt->execute([$serial, $serial, $this->product->productSlug, 'buyer@example.test', $revoked, $now, $now, $now]);
    }

    private function seedWebhookEvent(string $serial, string $dealCode, string $eventId = 'evt_backfill_1'): void
    {
        if (!Schema::connection('root')->hasTable('WebhookEvents')) {
            DB::connection('root')->statement('CREATE TABLE IF NOT EXISTS "WebhookEvents" (
                "EventId" VARCHAR(128) NOT NULL, "Gateway" VARCHAR(32) NOT NULL,
                "EventName" VARCHAR(64) NOT NULL, "ProductSlug" VARCHAR(64) NOT NULL,
                "CustomerEmail" VARCHAR(128) NOT NULL, "SerialValue" VARCHAR(128) NOT NULL,
                "PayloadJson" TEXT NULL, "created_at" DATETIME NOT NULL, "updated_at" DATETIME NOT NULL,
                PRIMARY KEY ("EventId", "Gateway"))');
        }
        DB::connection('root')->table('WebhookEvents')->insert([
            'EventId' => $eventId, 'Gateway' => 'stripe', 'EventName' => 'checkout.session.completed',
            'ProductSlug' => $this->product->productSlug, 'CustomerEmail' => 'buyer@example.test',
            'SerialValue' => $serial,
            'PayloadJson' => json_encode(['eventId' => $eventId, 'customerEmail' => 'buyer@example.test', 'productSlug' => $this->product->productSlug, 'dealCode' => $dealCode]),
            'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    private function licenseRow(string $serial): array|false
    {
        $pdo = new PDO("sqlite:{$this->shardPath}");

        return $pdo->query("SELECT * FROM ProductLicenses WHERE serialValue = '{$serial}'")->fetch(PDO::FETCH_ASSOC);
    }

    public function test_serials_only_key_verifies_after_jit_promotion_without_binding(): void
    {
        $this->seedSerial('LARA-BACKFILL-01');
        $res = $this->postJson('/api/v1/verify', [
            'productSlug' => $this->product->productSlug, 'serialValue' => 'LARA-BACKFILL-01',
            'hardwareDigest' => self::HWID_A,
        ]);
        $res->assertStatus(200)->assertJsonPath('Status.IsSuccess', true)->assertJsonPath('Results.0.isValid', true);
        $row = $this->licenseRow('LARA-BACKFILL-01');
        $this->assertNotFalse($row, 'JIT promotion must create the ProductLicenses row.');
        // Fallback: seeded product has 3 deals and no webhook event, so no
        // deal is uniquely identifiable — 1 seat, DEFAULT code, null expiry.
        $this->assertSame('DEFAULT', $row['dealCode']);
        $this->assertSame(1, (int) $row['allowedSeats']);
        $this->assertNull($row['expiresAt']);
        $this->assertSame(1, (int) $row['isActive']);
        $this->assertSame(0, (int) $row['isRevoked']);
        $pdo = new PDO("sqlite:{$this->shardPath}");
        $count = (int) $pdo->query('SELECT COUNT(*) FROM MachineBindings WHERE ReleasedAt IS NULL')->fetchColumn();
        $this->assertSame(0, $count, 'Verify must not bind a seat, even when it promotes.');
    }

    public function test_serials_only_key_activates_and_binds(): void
    {
        $this->seedSerial('LARA-BACKFILL-02');
        $res = $this->postJson('/api/v1/activate', [
            'productSlug' => $this->product->productSlug, 'serialValue' => 'LARA-BACKFILL-02',
            'hardwareDigest' => self::HWID_A,
        ]);
        $res->assertStatus(200)->assertJsonPath('Status.IsSuccess', true)->assertJsonPath('Results.0.isActivated', true);
        $this->assertNotFalse($this->licenseRow('LARA-BACKFILL-02'));
        $pdo = new PDO("sqlite:{$this->shardPath}");
        $binding = $pdo->query('SELECT * FROM MachineBindings WHERE FingerprintHash = "' . self::HWID_A . '" AND ReleasedAt IS NULL')->fetch(PDO::FETCH_ASSOC);
        $this->assertNotFalse($binding, 'Activate must bind a seat after promotion.');
    }

    public function test_unknown_key_still_404_and_creates_no_row(): void
    {
        $this->seedSerial('LARA-BACKFILL-03');
        $res = $this->postJson('/api/v1/verify', [
            'productSlug' => $this->product->productSlug, 'serialValue' => 'LARA-UNKNOWN-000',
            'hardwareDigest' => self::HWID_A,
        ]);
        $res->assertStatus(404)->assertJsonPath('Attributes.Error.ErrorCode', 'LicenseNotFound');
        $this->assertFalse($this->licenseRow('LARA-UNKNOWN-000'), 'Unknown serial must create no ProductLicenses row.');
        $pdo = new PDO("sqlite:{$this->shardPath}");
        $serials = (int) $pdo->query("SELECT COUNT(*) FROM Serials WHERE SerialValue = 'LARA-UNKNOWN-000'")->fetchColumn();
        $this->assertSame(0, $serials, 'Unknown serial must create no Serials row either.');
        $activate = $this->postJson('/api/v1/activate', [
            'productSlug' => $this->product->productSlug, 'serialValue' => 'LARA-UNKNOWN-000',
            'hardwareDigest' => self::HWID_A,
        ]);
        $activate->assertStatus(404)->assertJsonPath('Attributes.Error.ErrorCode', 'LicenseNotFound');
        $this->assertFalse($this->licenseRow('LARA-UNKNOWN-000'));
    }

    public function test_revoked_serials_key_promotes_as_revoked_not_active(): void
    {
        $this->seedSerial('LARA-BACKFILL-REVOKED', 1);
        $res = $this->postJson('/api/v1/verify', [
            'productSlug' => $this->product->productSlug, 'serialValue' => 'LARA-BACKFILL-REVOKED',
            'hardwareDigest' => self::HWID_A,
        ]);
        $res->assertStatus(403)->assertJsonPath('Attributes.Error.ErrorCode', 'LicenseRevoked');
        $row = $this->licenseRow('LARA-BACKFILL-REVOKED');
        $this->assertNotFalse($row, 'Revoked key is promoted so the portal shows it, but flagged revoked.');
        $this->assertSame(1, (int) $row['isRevoked']);
        $this->assertSame(0, (int) $row['isActive']);
    }

    public function test_backfill_copies_webhook_deal_seats_and_expiry_and_is_idempotent(): void
    {
        // ELM_1Y_THREE: yearly, 3 seats. Issued 2026-01-15 => expiry 2027-01-15.
        $this->seedSerial('LARA-BACKFILL-04', 0, '2026-01-15 10:00:00');
        $this->seedWebhookEvent('LARA-BACKFILL-04', 'ELM_1Y_THREE');
        $service = app(LicenseBackfillService::class);
        $first = $service->backfillAll($this->product->productSlug);
        $this->assertSame(1, $first['totals']['promoted']);
        $row = $this->licenseRow('LARA-BACKFILL-04');
        $this->assertNotFalse($row);
        $this->assertSame('ELM_1Y_THREE', $row['dealCode']);
        $this->assertSame(3, (int) $row['allowedSeats']);
        $this->assertSame('buyer@example.test', $row['customerEmail']);
        $this->assertSame('evt_backfill_1', $row['transactionId']);
        $this->assertSame(
            \Illuminate\Support\Carbon::parse('2026-01-15 10:00:00')->addYear()->toISOString(),
            \Illuminate\Support\Carbon::parse((string) $row['expiresAt'])->toISOString()
        );
        $second = $service->backfillAll($this->product->productSlug);
        $this->assertSame(0, $second['totals']['promoted']);
        $this->assertSame(1, $second['totals']['alreadyPresent']);
        $pdo = new PDO("sqlite:{$this->shardPath}");
        $count = (int) $pdo->query("SELECT COUNT(*) FROM ProductLicenses WHERE serialValue = 'LARA-BACKFILL-04'")->fetchColumn();
        $this->assertSame(1, $count, 'Second backfill run must not duplicate.');
    }

    public function test_promoted_expired_key_is_rejected_after_promotion(): void
    {
        // Daily deal would need a 'daily' deal to exist; use the fallback
        // path's opposite: a webhook-matched yearly deal issued 2 years ago.
        $this->seedSerial('LARA-BACKFILL-EXPIRED', 0, '2024-01-15 10:00:00');
        $this->seedWebhookEvent('LARA-BACKFILL-EXPIRED', 'ELM_1Y_THREE', 'evt_backfill_exp');
        $res = $this->postJson('/api/v1/verify', [
            'productSlug' => $this->product->productSlug, 'serialValue' => 'LARA-BACKFILL-EXPIRED',
            'hardwareDigest' => self::HWID_A,
        ]);
        $res->assertStatus(403)->assertJsonPath('Attributes.Error.ErrorCode', 'LicenseExpired');
        $this->assertNotFalse($this->licenseRow('LARA-BACKFILL-EXPIRED'), 'Expired key is still promoted, then rejected on its derived expiry.');
    }

    public function test_backfill_artisan_command_reports_and_promotes(): void
    {
        $this->seedSerial('LARA-BACKFILL-05');
        $this->artisan('lara:licenses:backfill', ['--product' => $this->product->productSlug])
            ->assertExitCode(0);
        $this->assertNotFalse($this->licenseRow('LARA-BACKFILL-05'));
    }
}
