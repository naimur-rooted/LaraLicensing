<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Product;
use App\Models\User;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * Test verifying Developer Portal, Product Shards, Dynamic User Scoping, and AI Blueprint generation.
 */
class DeveloperPortalTest extends TestCase
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

    public function test_can_list_developer_products_with_deals(): void
    {
        $response = $this->getJson('/api/v1/developer/products');
        $response->assertStatus(200)->assertJsonPath('IsSuccess', true);
    }

    public function test_authenticated_developer_without_products_sees_empty_list(): void
    {
        $user = new User();
        $user->UserId = 999;
        $response = $this->actingAs($user)->getJson('/api/v1/developer/products');
        $response->assertStatus(200)
            ->assertJsonPath('IsSuccess', true)
            ->assertJsonCount(0, 'Data');
    }

    public function test_overview_returns_zero_state_for_developer_without_products(): void
    {
        $user = new User();
        $user->UserId = 999;
        $response = $this->actingAs($user)->getJson('/api/v1/developer/overview');
        $response->assertStatus(200)
            ->assertJsonPath('IsSuccess', true)
            ->assertJsonPath('Data.productsCount', 0)
            ->assertJsonPath('Data.kpis.activations.current', 0)
            ->assertJsonPath('Data.kpis.licenses.current', 0)
            ->assertJsonPath('Data.trialExpiresOn', 'Active');
    }

    public function test_authenticated_product_creation_assigns_developer_user_id(): void
    {
        $user = new User();
        $user->UserId = 999;
        $slug = 'scoped-tool-' . time();
        $response = $this->actingAs($user)->postJson('/api/v1/developer/products', [
            'productName' => 'Scoped Developer Tool',
            'productSlug' => $slug,
            'category' => 'DesktopApp',
        ]);
        $response->assertStatus(201)->assertJsonPath('Data.userId', 999);
        $this->actingAs($user)->getJson('/api/v1/developer/products')
            ->assertStatus(200)->assertJsonCount(1, 'Data');
        $this->cleanUpShard($slug);
    }

    public function test_can_create_product_and_provisions_sqlite_shard(): void
    {
        $user = new User();
        $user->UserId = 1;
        $slug = 'test-scraper-' . time();
        $response = $this->actingAs($user)->postJson('/api/v1/developer/products', [
            'productName' => 'Test Lead Scraper',
            'productSlug' => $slug,
            'category' => 'ChromeExtension',
        ]);
        $response->assertStatus(201)
            ->assertJsonPath('IsSuccess', true)
            ->assertJsonPath('Data.productSlug', $slug);
        $this->assertFileExists(database_path("product_{$slug}.sqlite"));
        $this->cleanUpShard($slug);
    }

    public function test_unauthenticated_caller_cannot_create_product(): void
    {
        $response = $this->postJson('/api/v1/developer/products', [
            'productName' => 'Unauthorized Product',
            'productSlug' => 'unauthorized-tool',
            'category' => 'ChromeExtension',
        ]);
        $response->assertStatus(401);
    }

    public function test_can_add_custom_pricing_deal(): void
    {
        $product = Product::first();
        $this->assertNotNull($product);
        $response = $this->postJson("/api/v1/developer/products/{$product->productSlug}/deals", [
            'dealName' => 'Special 2-Year',
            'dealCode' => 'DEAL_2Y_' . time(),
            'billingInterval' => 'biennial',
            'priceCents' => 5900,
            'allowedMachineSeats' => 5,
            'allowedDomains' => 5,
            'isDecoyTarget' => true,
        ]);
        $response->assertStatus(201)->assertJsonPath('Data.isDecoyTarget', true);
    }

    public function test_can_generate_ai_integration_blueprint(): void
    {
        $product = Product::first();
        $this->assertNotNull($product);
        $response = $this->getJson("/api/v1/developer/products/{$product->productSlug}/blueprint");
        $response->assertStatus(200)
            ->assertJsonPath('IsSuccess', true)
            ->assertJsonStructure(['Data' => ['BlueprintMarkdown', 'ProductSlug']]);
        $markdown = $response->json('Data.BlueprintMarkdown');
        $this->assertStringContainsString('Autonomous Client License Verification & Commercial Storefront Blueprint', $markdown);
        $this->assertStringContainsString($product->productSlug, $markdown);
        $this->assertStringContainsString('Live Deals Endpoint (GET):', $markdown);
        $this->assertStringContainsString('Dual-Section Storefront & Activation Dialog:', $markdown);
        $this->assertStringContainsString('Zero Dummy Fallback Mandate', $markdown);
        $this->assertStringContainsString('Zero-Dependency Parser Mandate', $markdown);
        $this->assertStringContainsString('Buy Plan Action Mandate', $markdown);
    }

    public function test_can_issue_license_via_decoupled_webhook(): void
    {
        $product = Product::first();
        $this->assertNotNull($product);
        $response = $this->postJson("/api/v1/products/{$product->productSlug}/licenses/issue", [
            'transactionId' => 'tx_stripe_mock_89234891',
            'customerEmail' => 'client@acmecorp.com',
            'dealCode' => 'ELM_1Y_THREE',
        ]);
        $response->assertStatus(200)->assertJsonPath('Data.CustomerEmail', 'client@acmecorp.com');
        $this->assertStringContainsStringIgnoringCase($product->productSlug, $response->json('Data.SerialValue'));
    }

    private function cleanUpShard(string $slug): void
    {
        $shardPath = database_path("product_{$slug}.sqlite");
        if (file_exists($shardPath)) {
            @unlink($shardPath);
        }
    }
}
