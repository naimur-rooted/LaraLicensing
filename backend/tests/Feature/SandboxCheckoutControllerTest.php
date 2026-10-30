<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Http\Controllers\SandboxCheckoutController;
use App\Models\Product;
use App\Models\ProductDeal;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Covers SandboxCheckoutController (GET /checkout/{slug} and
 * POST /checkout/{slug}/process) for the EndpointInventory gate.
 *
 * Root cause guarded: these two web routes shipped in 7404ea8 with no
 * test file referencing the controller, so the parity gate failed.
 * The process test uses a unique product slug so its shard file
 * (backend/database/product_sandbox-gate.sqlite) never collides with
 * seeded products, and cleans that file up afterwards.
 */
final class SandboxCheckoutControllerTest extends TestCase
{
    private const SLUG = 'sandbox-gate';

    protected function setUp(): void
    {
        parent::setUp();
        $product = Product::create([
            'userId' => 1,
            'productSlug' => self::SLUG,
            'productName' => 'Sandbox Gate Product',
            'category' => 'DesktopApp',
        ]);
        ProductDeal::create([
            'productId' => (int) $product->getKey(),
            'dealName' => 'Annual Pro',
            'dealCode' => 'ANNUAL-PRO',
            'billingInterval' => 'yearly',
            'priceCents' => 4900,
            'allowedMachineSeats' => 3,
            'isActive' => 1,
        ]);
    }

    protected function tearDown(): void
    {
        $shard = database_path('product_' . self::SLUG . '.sqlite');
        if (file_exists($shard)) {
            unlink($shard);
        }
        $repodb = base_path('repodb/product_' . self::SLUG . '.sqlite');
        if (file_exists($repodb)) {
            unlink($repodb);
        }
        parent::tearDown();
    }

    public function test_show_checkout_unknown_slug_returns_404(): void
    {
        $res = $this->get('/checkout/no-such-product');
        $res->assertStatus(404);
    }

    public function test_show_checkout_known_slug_returns_html(): void
    {
        $res = $this->get('/checkout/' . self::SLUG);
        $res->assertStatus(200);
        $this->assertStringContainsString('text/html', (string) $res->headers->get('Content-Type'));
        $this->assertStringContainsString('Sandbox Gate Product', (string) $res->getContent());
    }

    public function test_process_payment_unknown_slug_returns_404(): void
    {
        $res = $this->postJson('/checkout/no-such-product/process', [
            'dealCode' => 'ANNUAL-PRO',
            'email' => 'buyer@example.test',
        ]);
        $res->assertStatus(404);
    }

    public function test_process_payment_mints_hotel_serial_and_shard_row(): void
    {
        $res = $this->postJson('/checkout/' . self::SLUG . '/process', [
            'dealCode' => 'ANNUAL-PRO',
            'email' => 'buyer@example.test',
            'name' => 'Front Desk Manager',
        ]);
        $res->assertStatus(200);
        $res->assertJsonPath('success', true);
        $serial = (string) $res->json('licenseKey');
        $this->assertStringStartsWith('HOTEL-ANNUALPRO-', $serial);
        $this->assertSame('buyer@example.test', $res->json('customerEmail'));

        $pdo = new \PDO('sqlite:' . database_path('product_' . self::SLUG . '.sqlite'));
        $stmt = $pdo->prepare('SELECT serialValue FROM ProductLicenses WHERE serialValue = :s');
        $stmt->execute([':s' => $serial]);
        $this->assertNotFalse($stmt->fetch(), 'SandboxCheckoutController must record the minted serial in the product shard.');
    }
}
