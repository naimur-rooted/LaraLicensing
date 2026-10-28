<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Product;
use App\Models\User;
use Illuminate\Support\Facades\Schema;
use PDO;
use Tests\TestCase;

/**
 * Phase 4 item 2: Sanctum developer mint endpoint.
 *
 * POST /api/v1/developer/products/{slug}/licenses/mint must mint a
 * test licence directly to ProductLicenses (+ Serials) without the
 * browser ever touching the HMAC webhook secret. Auth: 401
 * unauthenticated, 403 non-owner, 404 unknown/inactive product.
 * Replay choice (explicit): NEW SERIAL PER CALL — two identical
 * mints create two rows with different serials; mint is not
 * idempotent.
 */
class DeveloperLicenseMintTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->ensureTablesMigrated();
        $this->seed(\Database\Seeders\ProductSeeder::class);
    }

    private function ensureTablesMigrated(): void
    {
        if (!Schema::connection('root')->hasTable('Products')) {
            $this->artisan('migrate', ['--path' => 'database/migrations/root/2026_10_20_000001_create_root_products_table.php']);
        }
        if (!Schema::connection('root')->hasTable('ProductDeals')) {
            $this->artisan('migrate', ['--path' => 'database/migrations/root/2026_10_21_000001_add_features_to_product_deals_table.php']);
        }
    }

    private function owner(): User
    {
        $user = new User();
        $user->UserId = 1;

        return $user;
    }

    private function nonOwner(): User
    {
        $user = new User();
        $user->UserId = 88888;

        return $user;
    }

    private function mintUrl(string $slug): string
    {
        return "/api/v1/developer/products/{$slug}/licenses/mint";
    }

    private function cleanUpShard(string $slug): void
    {
        $shardPath = database_path("product_{$slug}.sqlite");
        if (file_exists($shardPath)) {
            @unlink($shardPath);
        }
    }

    /** @return array<int, array<string, mixed>> */
    private function shardRows(string $slug, string $table): array
    {
        $pdo = new PDO('sqlite:' . database_path("product_{$slug}.sqlite"));
        $stmt = $pdo->query("SELECT * FROM {$table} ORDER BY 1");

        return $stmt ? $stmt->fetchAll(PDO::FETCH_ASSOC) : [];
    }

    public function test_unauthenticated_mint_returns_401(): void
    {
        $product = Product::first();
        $this->assertNotNull($product);
        $this->postJson($this->mintUrl($product->productSlug), ['dealCode' => 'ELM_1M_SINGLE'])
            ->assertStatus(401);
    }

    public function test_non_owner_mint_returns_403(): void
    {
        $product = Product::first();
        $this->assertNotNull($product);
        $this->actingAs($this->nonOwner(), 'sanctum')
            ->postJson($this->mintUrl($product->productSlug), ['dealCode' => 'ELM_1M_SINGLE'])
            ->assertStatus(403);
    }

    public function test_unknown_product_mint_returns_404(): void
    {
        $this->actingAs($this->owner(), 'sanctum')
            ->postJson($this->mintUrl('no-such-product'), ['dealCode' => 'ELM_1M_SINGLE'])
            ->assertStatus(404);
    }

    public function test_inactive_product_mint_returns_404(): void
    {
        $product = Product::first();
        $this->assertNotNull($product);
        $product->isActive = false;
        $product->save();

        try {
            $this->actingAs($this->owner(), 'sanctum')
                ->postJson($this->mintUrl($product->productSlug), ['dealCode' => 'ELM_1M_SINGLE'])
                ->assertStatus(404);
        } finally {
            $product->isActive = true;
            $product->save();
        }
    }

    public function test_unknown_deal_code_returns_400(): void
    {
        $product = Product::first();
        $this->assertNotNull($product);
        $this->cleanUpShard($product->productSlug);
        $this->actingAs($this->owner(), 'sanctum')
            ->postJson($this->mintUrl($product->productSlug), ['dealCode' => 'NO_SUCH_DEAL'])
            ->assertStatus(400);
        $this->cleanUpShard($product->productSlug);
    }

    public function test_owner_mint_creates_product_license_and_serial_without_secrets(): void
    {
        $product = Product::first();
        $this->assertNotNull($product);
        $slug = $product->productSlug;
        $this->cleanUpShard($slug);

        $response = $this->actingAs($this->owner(), 'sanctum')
            ->postJson($this->mintUrl($slug), [
                'dealCode' => 'ELM_1M_SINGLE',
                'customerEmail' => 'developer-test@domain.local',
                'transactionId' => 'tx_mock_mint_test_1',
            ]);
        $response->assertStatus(201)
            ->assertJsonPath('IsSuccess', true)
            ->assertJsonPath('Data.dealCode', 'ELM_1M_SINGLE')
            ->assertJsonPath('Data.allowedSeats', 1)
            ->assertJsonPath('Data.isSimulated', true);
        $serial = (string) $response->json('Data.serialValue');
        $this->assertNotSame('', $serial);
        // Monthly deal (ELM_1M_SINGLE) must derive an expiry via LicenseExpiry.
        $this->assertNotNull($response->json('Data.expiresAt'));

        // The response must never carry the webhook secret or private key.
        $body = $response->getContent();
        $this->assertStringNotContainsString((string) $product->webhookSecret, $body);
        $this->assertStringNotContainsString((string) $product->ed25519PrivateKey, $body);
        $this->assertStringNotContainsString('webhookSecret', $body);
        $this->assertStringNotContainsString('ed25519PrivateKey', $body);

        $licenses = $this->shardRows($slug, 'ProductLicenses');
        $this->assertCount(1, $licenses);
        $this->assertSame($serial, $licenses[0]['serialValue']);
        $this->assertSame('tx_mock_mint_test_1', $licenses[0]['transactionId']);

        $serials = $this->shardRows($slug, 'Serials');
        $this->assertCount(1, $serials);
        $this->assertSame($serial, $serials[0]['SerialValue']);

        $this->cleanUpShard($slug);
    }

    public function test_mint_without_deal_code_uses_first_active_deal(): void
    {
        $product = Product::first();
        $this->assertNotNull($product);
        $slug = $product->productSlug;
        $this->cleanUpShard($slug);

        $response = $this->actingAs($this->owner(), 'sanctum')
            ->postJson($this->mintUrl($slug), []);
        $response->assertStatus(201)
            ->assertJsonPath('IsSuccess', true)
            ->assertJsonPath('Data.dealCode', 'ELM_1M_SINGLE');
        $this->assertCount(1, $this->shardRows($slug, 'ProductLicenses'));

        $this->cleanUpShard($slug);
    }

    public function test_second_identical_mint_creates_new_serial(): void
    {
        // Explicit replay choice: NEW SERIAL PER CALL, not idempotent.
        $product = Product::first();
        $this->assertNotNull($product);
        $slug = $product->productSlug;
        $this->cleanUpShard($slug);

        $payload = ['dealCode' => 'ELM_1Y_THREE', 'transactionId' => 'tx_mock_replay_same'];
        $first = $this->actingAs($this->owner(), 'sanctum')->postJson($this->mintUrl($slug), $payload);
        $first->assertStatus(201);
        $second = $this->actingAs($this->owner(), 'sanctum')->postJson($this->mintUrl($slug), $payload);
        $second->assertStatus(201);

        $this->assertNotSame((string) $first->json('Data.serialValue'), (string) $second->json('Data.serialValue'));
        $this->assertSame(3, (int) $second->json('Data.allowedSeats'));
        $this->assertCount(2, $this->shardRows($slug, 'ProductLicenses'));
        $this->assertCount(2, $this->shardRows($slug, 'Serials'));

        $this->cleanUpShard($slug);
    }
}
