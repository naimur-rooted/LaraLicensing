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
        $product = Product::create($this->buildProductPayload($userId, $slug, $attributes, $keyPair));
        $this->provisionProductShard($slug);
        $this->provisionProductDeals($product, $attributes['deals'] ?? []);

        return $product->load('deals');
    }

    private function buildProductPayload(int $userId, string $slug, array $attrs, array $keyPair): array
    {
        return [
            'userId' => $userId,
            'productSlug' => $slug,
            'productName' => (string) $attrs['productName'],
            'category' => (string) ($attrs['category'] ?? 'DesktopApp'),
            'description' => $attrs['description'] ?? null,
            'ed25519PublicKey' => $keyPair['public'],
            'ed25519PrivateKey' => $keyPair['private'],
            'webhookSecret' => 'whsec_' . Str::random(32),
            'checkIntervalDays' => (int) ($attrs['checkIntervalDays'] ?? 7),
            'isActive' => true,
        ];
    }

    private function provisionProductDeals(Product $product, mixed $deals): void
    {
        if (is_array($deals) && !empty($deals)) {
            foreach ($deals as $deal) {
                $this->createDeal($product, (array) $deal);
            }
        }
    }

    /** Provision an isolated SQLite shard for the product in repodb/. */
    private function provisionProductShard(string $slug): void
    {
        $targetPaths = [
            base_path("repodb/product_{$slug}.sqlite"),
            database_path("product_{$slug}.sqlite"),
        ];
        foreach ($targetPaths as $path) {
            $this->ensureSqliteShardReady($path);
        }
    }

    private function ensureSqliteShardReady(string $path): void
    {
        $dir = dirname($path);
        if (!is_dir($dir)) {
            mkdir($dir, 0777, true);
        }
        if (!file_exists($path)) {
            touch($path);
            $pdo = new PDO("sqlite:{$path}");
            $pdo->exec('PRAGMA journal_mode = WAL;');
            $pdo->exec('PRAGMA busy_timeout = 5000;');
            $this->initProductShardSchema($pdo);
        }
    }

    private function initProductShardSchema(PDO $pdo): void
    {
        $pdo->exec('CREATE TABLE IF NOT EXISTS Licenses (
            LicenseId INTEGER PRIMARY KEY AUTOINCREMENT,
            LicenseKey VARCHAR(128) NOT NULL UNIQUE,
            ProductSlug VARCHAR(64) NOT NULL,
            CustomerEmail VARCHAR(128) NOT NULL,
            DealCode VARCHAR(40) NOT NULL DEFAULT "DEFAULT",
            Status VARCHAR(32) NOT NULL DEFAULT "Active",
            IssuedAt DATETIME,
            CreatedAt DATETIME,
            UpdatedAt DATETIME
        );');
        $this->initSerialsAndBindingsTables($pdo);
    }

    private function initSerialsAndBindingsTables(PDO $pdo): void
    {
        $pdo->exec('CREATE TABLE IF NOT EXISTS Serials (
            SerialId INTEGER PRIMARY KEY AUTOINCREMENT,
            SerialValue VARCHAR(128) NOT NULL UNIQUE,
            LicenseKey VARCHAR(128),
            ProductSlug VARCHAR(64) NOT NULL,
            CustomerEmail VARCHAR(128) NOT NULL,
            IsRevoked INTEGER NOT NULL DEFAULT 0,
            IssuedAt DATETIME,
            CreatedAt DATETIME,
            UpdatedAt DATETIME
        );');
        $pdo->exec('CREATE TABLE IF NOT EXISTS MachineBindings (
            MachineBindingId INTEGER PRIMARY KEY AUTOINCREMENT,
            LicenseId INTEGER NOT NULL,
            SerialValue VARCHAR(128),
            FingerprintHash VARCHAR(64) NOT NULL,
            Hostname VARCHAR(128) DEFAULT \'Client PC\',
            ClientIp VARCHAR(45) DEFAULT \'127.0.0.1\',
            CountryCode VARCHAR(10) DEFAULT \'US\',
            CountryName VARCHAR(64) DEFAULT \'United States\',
            FirstSeenAt DATETIME,
            LastSeenAt DATETIME,
            ReleasedAt DATETIME,
            RebindCooldownUntil DATETIME,
            CreatedAt DATETIME,
            UpdatedAt DATETIME
        );');
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

        $deal->update($this->buildUpdateDealAttributes($deal, $attributes));

        return $deal;
    }

    private function buildUpdateDealAttributes(ProductDeal $deal, array $attrs): array
    {
        $features = $attrs['features'] ?? $deal->features;
        if (is_string($features)) {
            $features = array_values(array_filter(array_map('trim', explode("\n", $features))));
        }

        return [
            'dealName' => (string) ($attrs['dealName'] ?? $deal->dealName),
            'dealCode' => isset($attrs['dealCode']) ? strtoupper(trim((string) $attrs['dealCode'])) : $deal->dealCode,
            'billingInterval' => (string) ($attrs['billingInterval'] ?? $deal->billingInterval),
            'priceCents' => isset($attrs['priceCents']) ? (int) $attrs['priceCents'] : $deal->priceCents,
            'allowedMachineSeats' => isset($attrs['allowedMachineSeats']) ? (int) $attrs['allowedMachineSeats'] : $deal->allowedMachineSeats,
            'allowedDomains' => isset($attrs['allowedDomains']) ? (int) $attrs['allowedDomains'] : $deal->allowedDomains,
            'isDecoyTarget' => isset($attrs['isDecoyTarget']) ? (bool) $attrs['isDecoyTarget'] : $deal->isDecoyTarget,
            'features' => is_array($features) ? $features : [],
            'isActive' => isset($attrs['isActive']) ? (bool) $attrs['isActive'] : $deal->isActive,
        ];
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

    /** Generate Ed25519 keypair for offline token verification. */
    private function generateKeyPair(): array
    {
        return (new JwtTokenService())->generateEd25519KeyPair();
    }
}
