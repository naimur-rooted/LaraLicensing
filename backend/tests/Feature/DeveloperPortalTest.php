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

    public function test_unauthenticated_cannot_list_product_licenses(): void
    {
        $product = Product::first();
        $this->assertNotNull($product);
        $response = $this->getJson("/api/v1/developer/products/{$product->productSlug}/licenses");
        $response->assertStatus(401);
    }

    public function test_can_list_sharded_product_licenses_for_owner(): void
    {
        $product = Product::first();
        $this->assertNotNull($product);

        $ownerId = (int) ($product->userId ?? $product->UserId ?? 1);
        $owner = new User();
        $owner->UserId = $ownerId;
        $owner->Email = 'owner@example.test';

        // Issue a license into the shard
        $this->postJson("/api/v1/products/{$product->productSlug}/licenses/issue", [
            'transactionId' => 'tx_test_' . uniqid(),
            'customerEmail' => 'buyer@example.test',
            'dealCode' => 'MONTHLY_ANCHOR',
        ]);

        $response = $this->actingAs($owner)->getJson("/api/v1/developer/products/{$product->productSlug}/licenses");
        $response->assertStatus(200)
            ->assertJsonPath('IsSuccess', true)
            ->assertJsonStructure([
                'IsSuccess',
                'Data' => [
                    '*' => [
                        'licenseId',
                        'serialValue',
                        'dealCode',
                        'allowedSeats',
                        'activeSeats',
                        'isActive',
                        'createdAt',
                        'boundMachines',
                    ],
                ],
            ]);
    }

    public function test_non_owner_cannot_list_product_licenses(): void
    {
        $product = Product::first();
        $this->assertNotNull($product);

        $otherUser = new User();
        $otherUser->UserId = 99999;

        $response = $this->actingAs($otherUser)->getJson("/api/v1/developer/products/{$product->productSlug}/licenses");
        $response->assertStatus(403);
    }

    public function test_windows_style_idempotent_reactivation_and_seat_limits(): void
    {
        $product = Product::first();
        $this->assertNotNull($product);

        $issueRes = $this->postJson("/api/v1/products/{$product->productSlug}/licenses/issue", [
            'transactionId' => 'tx_reactivate_' . uniqid(),
            'customerEmail' => 'seatbuyer@example.test',
            'dealCode' => 'ELM_1M_SINGLE',
        ]);
        $issueRes->assertStatus(200);
        $serial = (string) $issueRes->json('Data.SerialValue');

        // First activation by HWID alpha-123
        $act1 = $this->postJson("/api/v1/products/{$product->productSlug}/activate", [
            'serialValue' => $serial,
            'hardwareDigest' => 'hwid-alpha-123',
            'hostname' => 'Alpha Workstation',
        ]);
        $act1->assertStatus(200)
            ->assertJsonPath('Data.isValid', true)
            ->assertJsonPath('Data.activeSeats', 1);

        // Idempotent re-activation with same HWID (uppercase variation)
        $act2 = $this->postJson("/api/v1/products/{$product->productSlug}/activate", [
            'license_key' => $serial,
            'hwid' => 'HWID-ALPHA-123',
            'machineName' => 'Alpha Workstation Renamed',
        ]);
        $act2->assertStatus(200)
            ->assertJsonPath('Data.isValid', true)
            ->assertJsonPath('Data.activeSeats', 1);

        // Different machine attempt should be rejected with SeatLimitExceeded (409)
        $act3 = $this->postJson("/api/v1/products/{$product->productSlug}/activate", [
            'serial' => $serial,
            'machineId' => 'hwid-beta-999',
            'computerName' => 'Beta Workstation',
        ]);
        $act3->assertStatus(409)
            ->assertJsonPath('Data.isValid', false)
            ->assertJsonPath('Data.errorCode', 'SeatLimitExceeded');
    }

    public function test_dynamic_payload_extractor_aliases_and_missing_slug_validation(): void
    {
        $product = Product::first();
        $this->assertNotNull($product);

        // Missing slug should fail with 400
        $failRes = $this->postJson('/api/v1/activate', [
            'serial' => 'ANY-KEY',
            'hwid' => 'any-hwid',
        ]);
        $failRes->assertStatus(400);

        $issueRes = $this->postJson("/api/v1/products/{$product->productSlug}/licenses/issue", [
            'transactionId' => 'tx_aliases_' . uniqid(),
            'customerEmail' => 'aliastester@example.test',
            'dealCode' => 'ELM_1Y_THREE',
        ]);
        $issueRes->assertStatus(200);
        $serial = (string) $issueRes->json('Data.SerialValue');

        // Test with key, machineGuid, computerName aliases via productSlug body parameter
        $act = $this->postJson('/api/v1/activate', [
            'productSlug' => $product->productSlug,
            'key' => strtolower($serial),
            'machineGuid' => 'GUID-TEST-456',
            'computerName' => 'Developer Rig',
        ]);
        $act->assertStatus(200)
            ->assertJsonPath('Data.isValid', true)
            ->assertJsonPath('Data.serialValue', strtoupper($serial));
    }

    public function test_developer_can_delete_license_without_active_seats(): void
    {
        $product = Product::first();
        $this->assertNotNull($product);

        $ownerId = (int) ($product->userId ?? $product->UserId ?? 1);
        $owner = new User();
        $owner->UserId = $ownerId;

        $issueRes = $this->postJson("/api/v1/products/{$product->productSlug}/licenses/issue", [
            'transactionId' => 'tx_del_' . uniqid(),
            'customerEmail' => 'deletable@example.test',
            'dealCode' => 'ELM_1M_SINGLE',
        ]);
        $issueRes->assertStatus(200);

        // List licenses to find the created licenseId
        $listRes = $this->actingAs($owner)->getJson("/api/v1/developer/products/{$product->productSlug}/licenses");
        $listRes->assertStatus(200);
        $licenses = $listRes->json('Data');
        $this->assertNotEmpty($licenses);
        $targetLicense = $licenses[0];
        $licenseId = (int) $targetLicense['licenseId'];

        // Delete license
        $delRes = $this->actingAs($owner)->deleteJson("/api/v1/developer/products/{$product->productSlug}/licenses/{$licenseId}");
        $delRes->assertStatus(200)
            ->assertJsonPath('IsSuccess', true)
            ->assertJsonPath('Message', 'License deleted successfully.');

        // Deleting non-existent license returns 404
        $delAgain = $this->actingAs($owner)->deleteJson("/api/v1/developer/products/{$product->productSlug}/licenses/{$licenseId}");
        $delAgain->assertStatus(404);
    }

    public function test_cannot_delete_license_with_active_seats_conflict(): void
    {
        $product = Product::first();
        $this->assertNotNull($product);

        $ownerId = (int) ($product->userId ?? $product->UserId ?? 1);
        $owner = new User();
        $owner->UserId = $ownerId;

        $issueRes = $this->postJson("/api/v1/products/{$product->productSlug}/licenses/issue", [
            'transactionId' => 'tx_conflict_' . uniqid(),
            'customerEmail' => 'activebound@example.test',
            'dealCode' => 'ELM_1M_SINGLE',
        ]);
        $issueRes->assertStatus(200);
        $serial = (string) $issueRes->json('Data.SerialValue');

        // Activate seat
        $this->postJson("/api/v1/products/{$product->productSlug}/activate", [
            'serialValue' => $serial,
            'hardwareDigest' => 'hwid-bound-device',
        ])->assertStatus(200);

        // Get license ID
        $listRes = $this->actingAs($owner)->getJson("/api/v1/developer/products/{$product->productSlug}/licenses");
        $licenses = $listRes->json('Data');
        $licenseId = (int) $licenses[0]['licenseId'];

        // Attempt deletion while seat is bound
        $delRes = $this->actingAs($owner)->deleteJson("/api/v1/developer/products/{$product->productSlug}/licenses/{$licenseId}");
        $delRes->assertStatus(409)
            ->assertJsonPath('IsSuccess', false)
            ->assertJsonPath('ErrorCode', 'ActiveSeatsConflict')
            ->assertJsonPath('Message', 'Cannot delete an active license with bound machines; revoke it instead.');

        // Now deactivate seat
        $this->postJson("/api/v1/products/{$product->productSlug}/deactivate", [
            'serialValue' => $serial,
            'hardwareDigest' => 'hwid-bound-device',
        ])->assertStatus(200);

        // Deletion should now succeed
        $delSuccess = $this->actingAs($owner)->deleteJson("/api/v1/developer/products/{$product->productSlug}/licenses/{$licenseId}");
        $delSuccess->assertStatus(200)
            ->assertJsonPath('IsSuccess', true)
            ->assertJsonPath('Message', 'License deleted successfully.');
    }

    public function test_non_owner_or_unauthenticated_cannot_delete_license(): void
    {
        $product = Product::first();
        $this->assertNotNull($product);

        // Unauthenticated -> 401
        $this->deleteJson("/api/v1/developer/products/{$product->productSlug}/licenses/1")
            ->assertStatus(401);

        // Non-owner -> 403
        $otherUser = new User();
        $otherUser->UserId = 88888;
        $this->actingAs($otherUser)->deleteJson("/api/v1/developer/products/{$product->productSlug}/licenses/1")
            ->assertStatus(403);
    }

    private function cleanUpShard(string $slug): void
    {
        $shardPath = database_path("product_{$slug}.sqlite");
        if (file_exists($shardPath)) {
            @unlink($shardPath);
        }
    }
}
