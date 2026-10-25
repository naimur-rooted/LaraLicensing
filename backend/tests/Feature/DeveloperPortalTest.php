<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Http\Controllers\Developer\ProductController;
use App\Http\Controllers\Developer\DeveloperOverviewController;
use App\Http\Controllers\Developer\AiArchitectController;

use App\Models\Product;
use Tests\TestCase;

/**
 * Test verifying Developer Portal, Product Shards, and AI Blueprint generation.
 */
class DeveloperPortalTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        if (!\Illuminate\Support\Facades\Schema::connection('root')->hasTable('Products')) {
            $this->artisan('migrate', ['--path' => 'database/migrations/root/2026_10_20_000001_create_root_products_table.php']);
        }
        if (!\Illuminate\Support\Facades\Schema::connection('root')->hasTable('ProductDeals')) {
            $this->artisan('migrate', ['--path' => 'database/migrations/root/2026_10_21_000001_add_features_to_product_deals_table.php']);
        }
        $this->seed(\Database\Seeders\ProductSeeder::class);
    }

    public function test_can_list_developer_products_with_deals(): void
    {
        $response = $this->getJson('/api/v1/developer/products');
        $response->assertStatus(200)
            ->assertJsonPath('IsSuccess', true);
    }

    public function test_can_create_product_and_provisions_sqlite_shard(): void
    {
        $slug = 'test-scraper-' . time();
        $response = $this->postJson('/api/v1/developer/products', [
            'productName' => 'Test Lead Scraper',
            'productSlug' => $slug,
            'category' => 'ChromeExtension',
            'description' => 'Autonomous browser scraping tool',
            'checkIntervalDays' => 7,
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('IsSuccess', true)
            ->assertJsonPath('Data.productSlug', $slug);

        $shardPath = database_path("product_{$slug}.sqlite");
        $this->assertFileExists($shardPath);
        if (file_exists($shardPath)) {
            @unlink($shardPath);
        }
    }

    public function test_can_add_custom_pricing_deal(): void
    {
        $product = Product::first();
        $this->assertNotNull($product);

        $response = $this->postJson("/api/v1/developer/products/{$product->productSlug}/deals", [
            'dealName' => 'Special Black Friday 2-Year',
            'dealCode' => 'DEAL_BF_2Y_' . time(),
            'billingInterval' => 'biennial',
            'priceCents' => 5900,
            'allowedMachineSeats' => 5,
            'allowedDomains' => 5,
            'isDecoyTarget' => true,
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('IsSuccess', true)
            ->assertJsonPath('Data.isDecoyTarget', true);
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
        $this->assertStringContainsString('Autonomous Client License Verification Blueprint', $markdown);
        $this->assertStringContainsString($product->productSlug, $markdown);
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

        $response->assertStatus(200)
            ->assertJsonPath('IsSuccess', true)
            ->assertJsonPath('Data.CustomerEmail', 'client@acmecorp.com');

        $this->assertStringContainsStringIgnoringCase($product->productSlug, $response->json('Data.SerialValue'));
    }
}
