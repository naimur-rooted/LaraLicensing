<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Product;
use App\Models\ProductDeal;
use Illuminate\Database\Seeder;

/**
 * Seeder for sample developer products and tiered deals.
 */
class ProductSeeder extends Seeder
{
    public function run(): void
    {
        $jwtService = new \App\Services\JwtTokenService();
        $keyPair = $jwtService->generateEd25519KeyPair();
        $product = Product::updateOrCreate(
            ['productSlug' => 'elementor-assistant'],
            [
                'userId' => 1,
                'productName' => 'Elementor Assistant Pro',
                'category' => 'WordPressPlugin',
                'description' => 'Autonomous styling and widget generation engine for Elementor.',
                'ed25519PublicKey' => $keyPair['public'],
                'ed25519PrivateKey' => $keyPair['private'],
                'webhookSecret' => 'whsec_elementor_prod_demo_9823478912',
                'checkIntervalDays' => 7,
                'isActive' => true,
            ]
        );

        $this->seedDeals((int) $product->ProductId);
    }

    private function seedDeals(int $productId): void
    {
        $deals = [
            [
                'dealCode' => 'ELM_1M_SINGLE',
                'dealName' => 'Single Site Monthly',
                'billingInterval' => 'monthly',
                'priceCents' => 999,
                'allowedMachineSeats' => 1,
                'allowedDomains' => 1,
                'isDecoyTarget' => false,
            ],
            [
                'dealCode' => 'ELM_1Y_THREE',
                'dealName' => '3 Sites Annual',
                'billingInterval' => 'yearly',
                'priceCents' => 4999,
                'allowedMachineSeats' => 3,
                'allowedDomains' => 3,
                'isDecoyTarget' => false,
            ],
            [
                'dealCode' => 'ELM_2Y_UNLIMITED',
                'dealName' => 'Unlimited Agency (2 Years + 3 Mo)',
                'billingInterval' => 'biennial',
                'priceCents' => 8900,
                'allowedMachineSeats' => 10,
                'allowedDomains' => 10,
                'isDecoyTarget' => true,
            ],
        ];

        foreach ($deals as $deal) {
            ProductDeal::updateOrCreate(
                ['productId' => $productId, 'dealCode' => $deal['dealCode']],
                $deal
            );
        }
    }
}
