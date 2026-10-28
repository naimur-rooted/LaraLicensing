<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Http\Controllers\Developer\AdvancedLicensingController;

use App\Models\Product;
use App\Models\Reseller;
use App\Services\LicenseExpiry;
use App\Services\ProductService;
use Tests\TestCase;

/**
 * Feature tests covering Client Handshake, Reseller Wholesale Quotas,
 * and Real-Time Fraud Quarantine.
 */
class AdvancedEcosystemTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        if (!\Illuminate\Support\Facades\Schema::connection('root')->hasTable('Products')) {
            $this->artisan('migrate', ['--path' => 'database/migrations/root/2026_10_20_000001_create_root_products_table.php']);
        }
        $this->seed(\Database\Seeders\ProductSeeder::class);

        @unlink(database_path('shard_reseller_sohel-reseller.sqlite'));

        if (!\Illuminate\Support\Facades\Schema::connection('root')->hasTable('Resellers')) {
            \Illuminate\Support\Facades\Schema::connection('root')->create('Resellers', function ($table) {
                $table->id('ResellerId');
                $table->string('ResellerName');
                $table->string('ResellerSlug')->unique();
                $table->string('ContactEmail');
                $table->boolean('IsActive')->default(true);
                $table->timestamps();
            });
        }

        Reseller::updateOrCreate(
            ['ResellerSlug' => 'sohel-reseller'],
            [
                'ResellerName' => 'Sohel Digital Agency',
                'ContactEmail' => 'sohel@agency.local',
                'IsActive' => true,
            ]
        );
    }

    private function seedIssuedLicense(Product $product, string $serial): void
    {
        // Decision 2A: pre-seed as an issued licence; verify/activate
        // no longer auto-register. Fresh shard per test for isolation.
        @unlink(database_path("product_{$product->productSlug}.sqlite"));
        app(ProductService::class)->recordShardLicense(
            $product, $serial, 'ELM_1M_SINGLE', 1, LicenseExpiry::derive('monthly')
        );
    }

    public function test_client_license_handshake_and_offline_token_generation(): void
    {
        $product = Product::first();
        $this->assertNotNull($product);
        $this->seedIssuedLicense($product, 'ELM-DEMO-VALID-001');

        // Decision 2A: binding happens on activate, not on verify.
        $response = $this->postJson("/api/v1/products/{$product->productSlug}/activate", [
            'serialValue' => 'ELM-DEMO-VALID-001',
            'hardwareDigest' => 'HWID_WORKSTATION_ALPHA',
            'hostname' => 'Office PC',
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('IsSuccess', true)
            ->assertJsonPath('Data.isValid', true)
            ->assertJsonPath('Data.allowedSeats', 1)
            ->assertJsonPath('Data.activeSeats', 1);

        $this->assertNotEmpty($response->json('Data.offlineToken'));

        // Verify after activate is read-only and still valid.
        $this->postJson("/api/v1/products/{$product->productSlug}/verify", [
            'serialValue' => 'ELM-DEMO-VALID-001',
            'hardwareDigest' => 'HWID_WORKSTATION_ALPHA',
        ])->assertStatus(200)->assertJsonPath('Data.isValid', true)->assertJsonPath('Data.activeSeats', 1);
    }

    public function test_hardware_seat_locking_prevents_overflow(): void
    {
        $product = Product::first();
        $this->assertNotNull($product);
        $this->seedIssuedLicense($product, 'ELM-SEAT-TEST-001');

        // First device binding (seat 1 of 1) via activate.
        $this->postJson("/api/v1/products/{$product->productSlug}/activate", [
            'serialValue' => 'ELM-SEAT-TEST-001',
            'hardwareDigest' => 'HWID_FIRST_MACHINE',
        ])->assertStatus(200);

        // Second device attempt on 1-seat license -> 409 SeatLimitExceeded.
        $response = $this->postJson("/api/v1/products/{$product->productSlug}/activate", [
            'serialValue' => 'ELM-SEAT-TEST-001',
            'hardwareDigest' => 'HWID_SECOND_MACHINE',
        ]);

        $response->assertStatus(409)
            ->assertJsonPath('IsSuccess', false)
            ->assertJsonPath('Data.errorCode', 'SeatLimitExceeded');

        // Read-only verify does not enforce seat binding.
        $this->postJson("/api/v1/products/{$product->productSlug}/verify", [
            'serialValue' => 'ELM-SEAT-TEST-001',
            'hardwareDigest' => 'HWID_SECOND_MACHINE',
        ])->assertStatus(200)->assertJsonPath('Data.isValid', true);
    }

    public function test_reseller_wholesale_quota_allocation_and_overview(): void
    {
        // Phase 3: reseller-wholesale now requires Sanctum auth.
        $user = new \App\Models\User();
        $user->UserId = 1;
        // Allocate wholesale quota
        $this->actingAs($user, 'sanctum')->postJson('/api/v1/reseller-wholesale/sohel-reseller/quota', [
            'tierCode' => 'ESSENTIAL',
            'count' => 200,
        ])->assertStatus(201);

        // Create custom offer
        $this->actingAs($user, 'sanctum')->postJson('/api/v1/reseller-wholesale/sohel-reseller/offers', [
            'offerName' => 'Agency Special 3-Pack',
            'retailPriceCents' => 4900,
            'durationDays' => 365,
            'machineSlots' => 3,
            'commissionPercent' => 25,
        ])->assertStatus(201);

        // Fetch overview
        $response = $this->actingAs($user, 'sanctum')->getJson('/api/v1/reseller-wholesale/sohel-reseller/overview');
        if ($response->status() !== 200) {
            dump($response->json());
        }
        $response->assertStatus(200)
            ->assertJsonPath('IsSuccess', true)
            ->assertJsonPath('Data.metrics.totalGranted', 200);
    }

    public function test_fraud_detection_and_1_click_quarantine(): void
    {
        $product = Product::first();
        $this->assertNotNull($product);
        $owner = new \App\Models\User();
        $owner->UserId = (int) ($product->userId ?? $product->UserId ?? 1);

        // Phase 3: security endpoints require Sanctum + product owner.
        $this->postJson("/api/v1/security/{$product->productSlug}/quarantine", [
            'serialValue' => 'ELM-SUSPICIOUS-SERIAL-99',
        ])->assertStatus(401);
        $other = new \App\Models\User();
        $other->UserId = 99999;
        $this->actingAs($other, 'sanctum')->postJson("/api/v1/security/{$product->productSlug}/quarantine", [
            'serialValue' => 'ELM-SUSPICIOUS-SERIAL-99',
        ])->assertStatus(403);

        // Quarantine license
        $qRes = $this->actingAs($owner, 'sanctum')->postJson("/api/v1/security/{$product->productSlug}/quarantine", [
            'serialValue' => 'ELM-SUSPICIOUS-SERIAL-99',
            'reason' => 'Impossible travel velocity detected',
        ]);

        $qRes->assertStatus(200)
            ->assertJsonPath('IsSuccess', true);

        // Verify quarantined license is rejected on verification
        $vRes = $this->postJson("/api/v1/products/{$product->productSlug}/verify", [
            'serialValue' => 'ELM-SUSPICIOUS-SERIAL-99',
            'hardwareDigest' => 'HWID_QUARANTINED_DEVICE',
        ]);

        $vRes->assertStatus(403)
            ->assertJsonPath('IsSuccess', false)
            ->assertJsonPath('Data.errorCode', 'LicenseRevoked');
    }
}
