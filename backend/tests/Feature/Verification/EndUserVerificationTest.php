<?php

declare(strict_types=1);

namespace Tests\Feature\Verification;

use App\Models\Product;
use App\Services\JwtTokenService;
use App\Services\LicenseExpiry;
use App\Services\ProductService;
use Database\Seeders\ProductSeeder;
use PDO;
use Tests\TestCase;

/**
 * End-to-end verification of Node-Locking, MachineBindings, Seat Limits, and Ed25519 offline tokens.
 */
class EndUserVerificationTest extends TestCase
{
    private const SERIAL = 'LARA-TEST-ALPHA-99';
    private const HWID_A = 'e3b0c44298fc1c149afbf4c8996fb92427ae41e4649b934ca495991b7852b855';
    private const HWID_B = 'b94d27b9934d3e08a52e52d7da7dabfac484efe37a5380ee9088f7ace2efcde9';

    private Product $product;
    private string $shardPath;

    protected function setUp(): void
    {
        parent::setUp();
        if (!\Illuminate\Support\Facades\Schema::connection('root')->hasTable('Products')) {
            $this->artisan('migrate', ['--path' => 'database/migrations/root/2026_10_20_000001_create_root_products_table.php']);
        }
        $this->seed(ProductSeeder::class);
        $this->product = Product::firstOrFail();
        $this->shardPath = database_path("product_{$this->product->productSlug}.sqlite");
        @unlink($this->shardPath);
        $this->seedIssuedLicense();
    }

    // Decision 2A: fixtures are pre-seeded as issued licences.
    // Verify/activate must not auto-register an unknown serial.
    private function seedIssuedLicense(): void
    {
        app(ProductService::class)->recordShardLicense(
            $this->product, self::SERIAL, 'ELM_1M_SINGLE', 1,
            LicenseExpiry::derive('monthly')
        );
    }

    private function seedExpiredLicense(): void
    {
        $past = now()->subDay()->toISOString();
        app(ProductService::class)->recordShardLicense(
            $this->product, 'LARA-TEST-EXPIRED-01', 'ELM_1M_SINGLE', 1, $past
        );
    }

    protected function tearDown(): void
    {
        if (file_exists($this->shardPath)) {
            @unlink($this->shardPath);
        }
        parent::tearDown();
    }

    public function test_node_locking_and_binding_creates_machine_bindings_row(): void
    {
        $res = $this->postJson('/api/v1/activate', [
            'productSlug' => $this->product->productSlug,
            'serialValue' => self::SERIAL,
            'hardwareDigest' => self::HWID_A,
            'hostname' => 'Office Alpha PC',
        ]);
        $res->assertStatus(200)->assertJsonPath('Status.IsSuccess', true)->assertJsonPath('Results.0.isActivated', true);
        $pdo = new PDO("sqlite:{$this->shardPath}");
        $row = $pdo->query('SELECT * FROM MachineBindings WHERE FingerprintHash = "' . self::HWID_A . '"')->fetch(PDO::FETCH_ASSOC);
        $this->assertNotEmpty($row);
        $this->assertSame(self::HWID_A, $row['FingerprintHash']);
        $this->assertNull($row['ReleasedAt']);
    }

    public function test_seat_concurrency_limit_enforces_max_concurrency_and_rejects_with_409(): void
    {
        $this->postJson('/api/v1/activate', [
            'productSlug' => $this->product->productSlug, 'serialValue' => self::SERIAL,
            'hardwareDigest' => self::HWID_A,
        ])->assertStatus(200);

        $res = $this->postJson('/api/v1/activate', [
            'productSlug' => $this->product->productSlug, 'serialValue' => self::SERIAL,
            'hardwareDigest' => self::HWID_B,
        ]);
        $res->assertStatus(409)
            ->assertJsonPath('Status.IsSuccess', false)
            ->assertJsonPath('Attributes.Error.ErrorCode', 'SeatLimitExceeded');
    }

    public function test_ed25519_signing_and_offline_verification(): void
    {
        $res = $this->postJson('/api/v1/activate', [
            'productSlug' => $this->product->productSlug, 'serialValue' => self::SERIAL,
            'hardwareDigest' => self::HWID_A,
        ]);
        $token = (string) $res->json('Results.0.offlineToken');
        $pubKey = (string) $res->json('Results.0.ed25519PublicKey');
        $this->assertNotEmpty($token);
        $jwt = new JwtTokenService();
        $verified = $jwt->verifyEd25519Token($token, self::HWID_A, $pubKey);
        $this->assertTrue($verified['isValid']);
        $this->assertSame(self::SERIAL, $verified['payload']['sub'] ?? null);
        $this->assertGreaterThan(time(), $verified['payload']['exp'] ?? 0);
    }

    public function test_tampered_token_fails_ed25519_verification(): void
    {
        $res = $this->postJson('/api/v1/activate', [
            'productSlug' => $this->product->productSlug, 'serialValue' => self::SERIAL,
            'hardwareDigest' => self::HWID_A,
        ]);
        $token = (string) $res->json('Results.0.offlineToken');
        $pubKey = (string) $res->json('Results.0.ed25519PublicKey');
        $tampered = substr_replace($token, 'X', 10, 1);
        $jwt = new JwtTokenService();
        $check = $jwt->verifyEd25519Token($tampered, self::HWID_A, $pubKey);
        $this->assertFalse($check['isValid']);
        $this->assertSame('InvalidSignature', $check['reason']);
    }

    private function activateHwidA(): void
    {
        $this->postJson('/api/v1/activate', [
            'productSlug' => $this->product->productSlug, 'serialValue' => self::SERIAL,
            'hardwareDigest' => self::HWID_A,
        ])->assertStatus(200);
    }

    public function test_heartbeat_updates_last_seen_without_incrementing_seat_count(): void
    {
        $this->activateHwidA();
        $res = $this->postJson('/api/v1/heartbeat', [
            'productSlug' => $this->product->productSlug, 'serialValue' => self::SERIAL,
            'hardwareDigest' => self::HWID_A,
        ]);
        $res->assertStatus(200)->assertJsonPath('Status.IsSuccess', true);
        $pdo = new PDO("sqlite:{$this->shardPath}");
        $count = (int) $pdo->query('SELECT COUNT(*) FROM MachineBindings WHERE ReleasedAt IS NULL')->fetchColumn();
        $this->assertSame(1, $count);
    }

    public function test_self_service_deactivation_releases_device_seat(): void
    {
        $this->activateHwidA();
        $res = $this->postJson('/api/v1/deactivate', [
            'productSlug' => $this->product->productSlug, 'serialValue' => self::SERIAL,
            'hardwareDigest' => self::HWID_A,
        ]);
        $res->assertStatus(200)->assertJsonPath('Status.IsSuccess', true)->assertJsonPath('Results.0.isDeactivated', true);
        $pdo = new PDO("sqlite:{$this->shardPath}");
        $row = $pdo->query('SELECT * FROM MachineBindings WHERE FingerprintHash = "' . self::HWID_A . '"')->fetch(PDO::FETCH_ASSOC);
        $this->assertNotNull($row['ReleasedAt']);
        $this->assertNotNull($row['RebindCooldownUntil']);
    }

    public function test_deactivation_of_unbound_machine_returns_404(): void
    {
        $this->activateHwidA();
        $res = $this->postJson('/api/v1/deactivate', [
            'productSlug' => $this->product->productSlug, 'serialValue' => self::SERIAL,
            'hardwareDigest' => self::HWID_B,
        ]);
        $res->assertStatus(404)->assertJsonPath('Status.IsSuccess', false)->assertJsonPath('Attributes.Error.ErrorCode', 'MachineNotBound');
    }

    public function test_verify_endpoint_checks_license_status(): void
    {
        $res = $this->postJson('/api/v1/verify', [
            'productSlug' => $this->product->productSlug, 'serialValue' => self::SERIAL,
            'hardwareDigest' => self::HWID_A,
        ]);
        $res->assertStatus(200)->assertJsonPath('Status.IsSuccess', true)->assertJsonPath('Results.0.isValid', true);
        $this->assertNotEmpty($res->json('Results.0.offlineToken'));
    }

    public function test_verify_does_not_bind_and_unknown_serial_is_not_found(): void
    {
        $res = $this->postJson('/api/v1/verify', [
            'productSlug' => $this->product->productSlug, 'serialValue' => self::SERIAL,
            'hardwareDigest' => self::HWID_A,
        ]);
        $res->assertStatus(200);
        $pdo = new PDO("sqlite:{$this->shardPath}");
        $count = (int) $pdo->query('SELECT COUNT(*) FROM MachineBindings WHERE ReleasedAt IS NULL')->fetchColumn();
        $this->assertSame(0, $count, 'Bare verify must create no binding.');
        $unknown = $this->postJson('/api/v1/verify', [
            'productSlug' => $this->product->productSlug, 'serialValue' => 'LARA-UNKNOWN-000',
            'hardwareDigest' => self::HWID_A,
        ]);
        $unknown->assertStatus(404)->assertJsonPath('Attributes.Error.ErrorCode', 'LicenseNotFound');
        $row = $pdo->query("SELECT * FROM ProductLicenses WHERE serialValue = 'LARA-UNKNOWN-000'")->fetch(PDO::FETCH_ASSOC);
        $this->assertFalse($row, 'Verify must not auto-register unknown serials.');
    }

    public function test_activate_unknown_serial_is_not_found_and_expired_is_rejected(): void
    {
        $unknown = $this->postJson('/api/v1/activate', [
            'productSlug' => $this->product->productSlug, 'serialValue' => 'LARA-UNKNOWN-000',
            'hardwareDigest' => self::HWID_A,
        ]);
        $unknown->assertStatus(404)->assertJsonPath('Attributes.Error.ErrorCode', 'LicenseNotFound');
        $this->seedExpiredLicense();
        $expired = $this->postJson('/api/v1/activate', [
            'productSlug' => $this->product->productSlug, 'serialValue' => 'LARA-TEST-EXPIRED-01',
            'hardwareDigest' => self::HWID_A,
        ]);
        $expired->assertStatus(403)->assertJsonPath('Attributes.Error.ErrorCode', 'LicenseExpired');
        $expiredVerify = $this->postJson('/api/v1/verify', [
            'productSlug' => $this->product->productSlug, 'serialValue' => 'LARA-TEST-EXPIRED-01',
            'hardwareDigest' => self::HWID_A,
        ]);
        $expiredVerify->assertStatus(403)->assertJsonPath('Attributes.Error.ErrorCode', 'LicenseExpired');
    }

    public function test_license_expiry_derivation_for_each_interval(): void
    {
        $base = now();
        $this->assertEquals($base->copy()->addDay()->toISOString(), LicenseExpiry::derive('daily', $base));
        $this->assertEquals($base->copy()->addWeek()->toISOString(), LicenseExpiry::derive('weekly', $base));
        $this->assertEquals($base->copy()->addMonth()->toISOString(), LicenseExpiry::derive('monthly', $base));
        $this->assertEquals($base->copy()->addMonths(3)->toISOString(), LicenseExpiry::derive('quarterly', $base));
        $this->assertEquals($base->copy()->addYear()->toISOString(), LicenseExpiry::derive('yearly', $base));
        $this->assertEquals($base->copy()->addYears(2)->toISOString(), LicenseExpiry::derive('biennial', $base));
        $this->assertNull(LicenseExpiry::derive('lifetime', $base));
        $this->assertFalse(LicenseExpiry::isExpired(null));
        $this->assertTrue(LicenseExpiry::isExpired(now()->subDay()->toISOString()));
    }
}
