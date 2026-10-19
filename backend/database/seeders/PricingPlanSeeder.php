<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\PricingPlan;
use Illuminate\Database\Seeder;

/**
 * Seeds VPN-style decoy pricing tiers into Root DB.
 */
final class PricingPlanSeeder extends Seeder
{
    public function run(): void
    {
        $this->seedMonthlyAnchor();
        $this->seedAnnualTier();
        $this->seedBiennialDecoyTarget();
    }

    private function seedMonthlyAnchor(): void
    {
        PricingPlan::updateOrCreate(
            ['planCode' => 'PLAN_1M'],
            [
                'planName' => '1 Month Plan',
                'billingInterval' => 'monthly',
                'retailPriceCents' => 1299,
                'discountedPriceCents' => 1299,
                'allowedMachineSlots' => 1,
                'isBestValue' => false,
                'isActive' => true,
            ]
        );
    }

    private function seedAnnualTier(): void
    {
        PricingPlan::updateOrCreate(
            ['planCode' => 'PLAN_1Y'],
            [
                'planName' => '1 Year Plan',
                'billingInterval' => 'yearly',
                'retailPriceCents' => 15588,
                'discountedPriceCents' => 5988,
                'allowedMachineSlots' => 3,
                'isBestValue' => false,
                'isActive' => true,
            ]
        );
    }

    private function seedBiennialDecoyTarget(): void
    {
        PricingPlan::updateOrCreate(
            ['planCode' => 'PLAN_2Y'],
            [
                'planName' => '2 Years + 3 Months Free',
                'billingInterval' => 'biennial',
                'retailPriceCents' => 35073,
                'discountedPriceCents' => 6450,
                'allowedMachineSlots' => 10,
                'isBestValue' => true,
                'isActive' => true,
            ]
        );
    }
}
