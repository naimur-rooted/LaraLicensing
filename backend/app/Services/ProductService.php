<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Product;
use App\Models\ProductDeal;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Str;
use PDO;

/**
 * Service managing developer Products, Shards, and AI Integration Blueprints.
 */
class ProductService
{
    /** List all active products for a developer. */
    public function listProducts(int $userId): Collection
    {
        return Product::with('deals')
            ->where('userId', $userId)
            ->where('isActive', true)
            ->get();
    }

    /** Retrieve a product by slug. */
    public function getProductBySlug(string $slug): ?Product
    {
        return Product::with('deals')
            ->where('productSlug', $slug)
            ->where('isActive', true)
            ->first();
    }

    /** Provision a new product and its dedicated SQLite shard. */
    public function createProduct(int $userId, array $attributes): Product
    {
        $slug = Str::slug((string) ($attributes['productSlug'] ?? $attributes['productName']));
        $keyPair = $this->generateKeyPair();
        $product = Product::create([
            'userId' => $userId,
            'productSlug' => $slug,
            'productName' => (string) $attributes['productName'],
            'category' => (string) ($attributes['category'] ?? 'DesktopApp'),
            'description' => $attributes['description'] ?? null,
            'ed25519PublicKey' => $keyPair['public'],
            'ed25519PrivateKey' => $keyPair['private'],
            'webhookSecret' => 'whsec_' . Str::random(32),
            'checkIntervalDays' => (int) ($attributes['checkIntervalDays'] ?? 7),
            'isActive' => true,
        ]);
        $this->provisionProductShard($slug);

        if (!empty($attributes['deals']) && is_array($attributes['deals'])) {
            foreach ($attributes['deals'] as $deal) {
                $this->createDeal($product, (array) $deal);
            }
        }

        return $product->load('deals');
    }

    /** Provision an isolated SQLite shard for the product. */
    private function provisionProductShard(string $slug): void
    {
        $shardPath = database_path("product_{$slug}.sqlite");
        if (!file_exists($shardPath)) {
            touch($shardPath);
            $pdo = new PDO("sqlite:{$shardPath}");
            $pdo->exec('PRAGMA journal_mode = WAL;');
            $pdo->exec('PRAGMA busy_timeout = 5000;');
        }
    }

    /** Create a deal/pricing package for a product. */
    public function createDeal(Product $product, array $attributes): ProductDeal
    {
        $features = $attributes['features'] ?? [];
        if (is_string($features)) {
            $features = array_values(array_filter(array_map('trim', explode("\n", $features))));
        }

        return ProductDeal::create([
            'productId' => $product->ProductId,
            'dealName' => (string) $attributes['dealName'],
            'dealCode' => strtoupper(trim((string) $attributes['dealCode'])),
            'billingInterval' => (string) ($attributes['billingInterval'] ?? 'monthly'),
            'priceCents' => (int) ($attributes['priceCents'] ?? 0),
            'allowedMachineSeats' => (int) ($attributes['allowedMachineSeats'] ?? 1),
            'allowedDomains' => (int) ($attributes['allowedDomains'] ?? 1),
            'isDecoyTarget' => (bool) ($attributes['isDecoyTarget'] ?? false),
            'features' => is_array($features) ? $features : [],
            'isActive' => true,
        ]);
    }

    /** Update an existing deal for a product. */
    public function updateDeal(Product $product, int $dealId, array $attributes): ?ProductDeal
    {
        $deal = ProductDeal::where('productId', $product->ProductId)
            ->where('ProductDealId', $dealId)
            ->first();

        if (!$deal) {
            return null;
        }

        $features = $attributes['features'] ?? $deal->features;
        if (is_string($features)) {
            $features = array_values(array_filter(array_map('trim', explode("\n", $features))));
        }

        $deal->update([
            'dealName' => (string) ($attributes['dealName'] ?? $deal->dealName),
            'dealCode' => isset($attributes['dealCode']) ? strtoupper(trim((string) $attributes['dealCode'])) : $deal->dealCode,
            'billingInterval' => (string) ($attributes['billingInterval'] ?? $deal->billingInterval),
            'priceCents' => isset($attributes['priceCents']) ? (int) $attributes['priceCents'] : $deal->priceCents,
            'allowedMachineSeats' => isset($attributes['allowedMachineSeats']) ? (int) $attributes['allowedMachineSeats'] : $deal->allowedMachineSeats,
            'allowedDomains' => isset($attributes['allowedDomains']) ? (int) $attributes['allowedDomains'] : $deal->allowedDomains,
            'isDecoyTarget' => isset($attributes['isDecoyTarget']) ? (bool) $attributes['isDecoyTarget'] : $deal->isDecoyTarget,
            'features' => is_array($features) ? $features : [],
            'isActive' => isset($attributes['isActive']) ? (bool) $attributes['isActive'] : $deal->isActive,
        ]);

        return $deal;
    }

    /** Delete a deal for a product. */
    public function deleteDeal(Product $product, int $dealId): bool
    {
        return (bool) ProductDeal::where('productId', $product->ProductId)
            ->where('ProductDealId', $dealId)
            ->delete();
    }

    /** Generate AI-Ready Markdown Integration Blueprint. */
    public function generateAiBlueprint(Product $product): string
    {
        $verifyUrl = url("/api/v1/products/{$product->productSlug}/verify");
        $dealsSummary = $product->deals->map(fn($d) => "- {$d->dealName} ({$d->dealCode}): {$d->billingInterval}, {$d->allowedMachineSeats} seats")->implode("\n");
        return <<<MARKDOWN
# Autonomous Client License Verification Blueprint
Integrate LaraLicensing into this application using the following contract:

- Product Slug: {$product->productSlug}
- Product Category: {$product->category}
- Verification Endpoint: {$verifyUrl}
- Public Key (Ed25519): {$product->ed25519PublicKey}
- Re-Verification Cadence: Every {$product->checkIntervalDays} days
- Available Deals & Seats:
{$dealsSummary}

## Architecture Invariants:
1. Generate a hardware fingerprint from Machine GUID or MAC hash.
2. Call verification endpoint on initial activation and cache the signed JWT offline token.
3. Verify the token signature locally using the public key on each startup.
4. Schedule background re-verification every {$product->checkIntervalDays} days.
MARKDOWN;
    }

    /** Generate mock Ed25519 keypair for offline token verification. */
    private function generateKeyPair(): array
    {
        return [
            'public' => 'MCowBQYDK2VwAyEA' . Str::random(40) . '=',
            'private' => 'MC4CAQAwBQYDK2VwBCIEI' . Str::random(40) . '=',
        ];
    }
}
