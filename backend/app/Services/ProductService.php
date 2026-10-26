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
        $dealsUrl = url("/api/v1/products/{$product->productSlug}/deals");
        $dealsSummary = $product->deals && $product->deals->isNotEmpty()
            ? $product->deals->map(function ($d) {
                $priceFormatted = '$' . number_format(($d->priceCents ?? 0) / 100, 2);
                $features = !empty($d->features)
                    ? (is_array($d->features) ? $d->features : (json_decode($d->features, true) ?: []))
                    : [];
                $featuresFormatted = !empty($features) ? ' | Features: ' . implode(', ', $features) : '';
                return "- {$d->dealName} ({$d->dealCode}): {$priceFormatted} / {$d->billingInterval} ({$d->allowedMachineSeats} seat(s)){$featuresFormatted}";
            })->implode("\n")
            : "- Standard Plan (STD): $49.00 / yearly (1 seat)";

        return <<<MARKDOWN
# Autonomous Client License Verification & Commercial Storefront Blueprint
Integrate LaraLicensing into this application adhering strictly to the contract below.

## 1. Product Identity & Cryptographic Contract
- Product Name: {$product->productName}
- Product Slug: {$product->productSlug}
- Verification Endpoint (POST): {$verifyUrl}
- Live Deals Endpoint (GET): {$dealsUrl}
- Public Key (Ed25519): {$product->ed25519PublicKey}
- Cadence: Re-verify every {$product->checkIntervalDays} days

## 2. Configured Pricing Packages (Ground Truth):
{$dealsSummary}
(Formula: Display Price = priceCents / 100.0, e.g., 3900 -> \$39.00)

## 3. Strict Non-Negotiable Architecture Invariants (Zero-Hallucination):

1. Dual-Section Storefront & Activation Dialog:
   - When the software starts without an active license, it MUST NOT render a bare license key text box alone.
   - It MUST render a dual-section UI:
     * Top Section: Display the 3 configured pricing deals as cards showing Deal Name, formatted Price (\$xx.xx / interval), allowed PC seats, and feature perks.
     * Bottom Section: "Already have a license key?" input box + "Activate License" button.

2. Zero Dummy Fallback Mandate (CRITICAL):
   - You MUST NOT hardcode invented dummy prices (\$29, \$290, \$490, etc.) in catch blocks or fallbacks.
   - Prices and features MUST strictly reflect the provided deals list above or be parsed from the Live Deals Endpoint.
   - If network or parsing fails, show a clean "Cannot reach licensing server. [Retry]" label. NEVER display fake numbers.

3. Zero-Dependency Parser Mandate:
   - For Java or C++, do NOT assume external JSON libraries (Gson, Jackson) exist.
   - Use standard library regex matching to parse `dealName`, `priceCents`, and `allowedMachineSeats` safely.

4. Buy Plan Action Mandate:
   - DO NOT open the raw API URL (.../deals) in the user's web browser.
   - When "Buy Plan" is clicked, display a purchase instruction modal (e.g., "To activate this package, contact vendor or visit vendor checkout") or open the vendor's actual checkout URL.

5. Hardware Fingerprinting & Offline Token:
   - Read the machine GUID / hardware digest.
   - Send verification POST request with `{"serialValue": "...", "hardwareDigest": "..."}`.
   - Cache the signed Ed25519 JWT offline token locally. Verify signature offline on startup without hitting the network every time.
MARKDOWN;
    }

    /** Generate Ed25519 keypair for offline token verification. */
    private function generateKeyPair(): array
    {
        return (new JwtTokenService())->generateEd25519KeyPair();
    }
}
