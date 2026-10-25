<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\PricingPlan;
use App\Services\JwtTokenService;
use App\Services\RiskAssessmentService;
use Tests\TestCase;

final class AdvancedLicensingTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        if (!\Illuminate\Support\Facades\Schema::connection('root')->hasTable('PricingPlans')) {
            $this->artisan('migrate', ['--path' => 'database/migrations/root/2026_10_19_000001_create_root_pricing_plans_table.php']);
        }
        $this->seed(\Database\Seeders\PricingPlanSeeder::class);
    }

    public function testPricingPlansListReturnsSeededTiers(): void
    {
        $response = $this->getJson('/api/Pricing/Plans');
        $response->assertStatus(200);
        $response->assertJsonPath('IsSuccess', true);
    }

    public function testOfflineTokenIssuanceAndVerification(): void
    {
        $jwt = new JwtTokenService();
        $token = $jwt->issueOfflineToken('LARA-TEST-0001', 'HWID1234567890AB', 'PLAN_2Y', 10);
        $result = $jwt->verifyOfflineToken($token, 'HWID1234567890AB');

        $this->assertTrue($result['isValid']);
        $this->assertSame('LARA-TEST-0001', $result['payload']['sub'] ?? null);
    }

    public function testRiskAssessmentEvaluatesNormalAttemptAsLowRisk(): void
    {
        $rba = new RiskAssessmentService();
        $evaluation = $rba->evaluateAttempt('LARA-TEST-0001', '127.0.0.1', 'HWID1234567890AB');

        $this->assertTrue($evaluation['isPermitted']);
        $this->assertLessThan(75, $evaluation['riskScore']);
    }
}
