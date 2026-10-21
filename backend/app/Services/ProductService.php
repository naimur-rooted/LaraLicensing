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
        return ProductDeal::create([
            'productId' => $product->ProductId,
            'dealName' => (string) $attributes['dealName'],
            'dealCode' => strtoupper(trim((string) $attributes['dealCode'])),
            'billingInterval' => (string) ($attributes['billingInterval'] ?? 'monthly'),
            'priceCents' => (int) ($attributes['priceCents'] ?? 0),
            'allowedMachineSeats' => (int) ($attributes['allowedMachineSeats'] ?? 1),
            'allowedDomains' => (int) ($attributes['allowedDomains'] ?? 1),
            'isDecoyTarget' => (bool) ($attributes['isDecoyTarget'] ?? false),
            'isActive' => true,
        ]);
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
