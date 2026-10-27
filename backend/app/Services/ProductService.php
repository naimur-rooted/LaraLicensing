<?php

declare(strict_types=1);

namespace App\Services;

use App\Exceptions\ValidationException;
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
        if ($slug === '') {
            throw ValidationException::invalidInput('productSlug could not be derived from productName.');
        }
        if (Product::query()->where('productSlug', $slug)->exists()) {
            throw ValidationException::invalidInput('productSlug already exists.');
        }
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
        $pdo->exec('CREATE TABLE IF NOT EXISTS ProductLicenses (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            serialValue TEXT NOT NULL,
            dealCode TEXT NOT NULL,
            allowedSeats INTEGER DEFAULT 1,
            isActive INTEGER DEFAULT 1,
            isRevoked INTEGER DEFAULT 0,
            createdAt TEXT NOT NULL
        );');
        $pdo->exec('CREATE TABLE IF NOT EXISTS HardwareBindings (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            licenseId INTEGER NOT NULL,
            hardwareDigest TEXT NOT NULL,
            hostname TEXT DEFAULT \'Client PC\',
            boundAt TEXT NOT NULL,
            lastSeenAt TEXT NOT NULL,
            clientIp TEXT DEFAULT \'127.0.0.1\',
            countryCode TEXT DEFAULT \'US\',
            countryName TEXT DEFAULT \'United States\'
        );');
    }

    /** Record an issued serial license into the isolated product SQLite shard. */
    public function recordShardLicense(Product $product, string $serial, string $dealCode, int $allowedSeats): void
    {
        $this->provisionProductShard($product->productSlug);
        $shardPath = database_path("product_{$product->productSlug}.sqlite");
        $pdo = new PDO("sqlite:{$shardPath}");
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        $check = $pdo->prepare('SELECT id FROM ProductLicenses WHERE serialValue = :s LIMIT 1');
        $check->execute([':s' => $serial]);
        if (!$check->fetch()) {
            try {
                $ins = $pdo->prepare('INSERT OR IGNORE INTO ProductLicenses (serialValue, dealCode, allowedSeats, isActive, isRevoked, createdAt) VALUES (:s, :d, :seats, 1, 0, :dt)');
                $ins->execute([
                    ':s' => $serial,
                    ':d' => $dealCode,
                    ':seats' => max(1, $allowedSeats),
                    ':dt' => now()->toISOString(),
                ]);
            } catch (\PDOException $e) {
                // Concurrent replay racing the check above: a UNIQUE
                // violation means the serial already exists, which is
                // idempotent success, not a 500. Re-throw anything else.
                if (!str_contains((string) $e->getMessage(), 'UNIQUE') && (int) $e->getCode() !== 23000) {
                    throw $e;
                }
            }
        }
    }

    private const ALLOWED_BILLING_INTERVALS = [
        'daily', 'weekly', 'monthly', 'quarterly', 'yearly', 'biennial', 'lifetime',
    ];

    private function validateDealAttributes(Product $product, array $attributes, ?int $excludeDealId = null): array
    {
        $identity = $this->validateDealIdentity($attributes);
        $numbers = $this->validateDealNumbers($attributes);
        $this->assertDealCodeUnique($product, $identity['dealCode'], $excludeDealId);
        return array_merge($identity, $numbers);
    }

    /** @return array{dealName:string,dealCode:string,billingInterval:string} */
    private function validateDealIdentity(array $attributes): array
    {
        $dealName = trim((string) ($attributes['dealName'] ?? ''));
        if ($dealName === '') {
            throw ValidationException::invalidInput('dealName is required.');
        }
        $dealCode = strtoupper(trim((string) ($attributes['dealCode'] ?? '')));
        if ($dealCode === '') {
            throw ValidationException::invalidInput('dealCode is required.');
        }
        $interval = (string) ($attributes['billingInterval'] ?? 'monthly');
        if (!in_array($interval, self::ALLOWED_BILLING_INTERVALS, true)) {
            throw ValidationException::invalidInput('billingInterval must be one of: daily, weekly, monthly, quarterly, yearly, biennial, lifetime.');
        }
        return ['dealName' => $dealName, 'dealCode' => $dealCode, 'billingInterval' => $interval];
    }

    /** @return array{priceCents:int,allowedMachineSeats:int} */
    private function validateDealNumbers(array $attributes): array
    {
        $priceRaw = $attributes['priceCents'] ?? 0;
        if (!is_numeric($priceRaw) || (int) $priceRaw < 0) {
            throw ValidationException::invalidInput('priceCents must be a non-negative integer.');
        }
        $seatsRaw = $attributes['allowedMachineSeats'] ?? 1;
        if (!is_numeric($seatsRaw) || (int) $seatsRaw < 1) {
            throw ValidationException::invalidInput('allowedMachineSeats must be at least 1.');
        }
        return ['priceCents' => (int) $priceRaw, 'allowedMachineSeats' => (int) $seatsRaw];
    }

    private function assertDealCodeUnique(Product $product, string $dealCode, ?int $excludeDealId): void
    {
        $dealQuery = ProductDeal::query()->where('productId', $product->ProductId)->where('dealCode', $dealCode);
        if ($excludeDealId !== null) {
            $dealQuery->where('ProductDealId', '!=', $excludeDealId);
        }
        if ($dealQuery->exists()) {
            throw ValidationException::invalidInput('dealCode already exists for this product.');
        }
    }

    /** Create a deal/pricing package for a product. */
    public function createDeal(Product $product, array $attributes): ProductDeal
    {
        $validated = $this->validateDealAttributes($product, $attributes);
        $features = $attributes['features'] ?? [];
        if (is_string($features)) {
            $features = array_values(array_filter(array_map('trim', explode("\n", $features))));
        }

        return ProductDeal::create([
            'productId' => $product->ProductId,
            'dealName' => $validated['dealName'],
            'dealCode' => $validated['dealCode'],
            'billingInterval' => $validated['billingInterval'],
            'priceCents' => $validated['priceCents'],
            'allowedMachineSeats' => $validated['allowedMachineSeats'],
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
        // Merge with existing values so validation sees a complete deal,
        // then validate the merged result (including per-product uniqueness
        // excluding this deal itself).
        $merged = $this->mergeDealAttributes($deal, $attrs);
        $product = Product::query()->where('ProductId', $deal->productId)->first();
        if ($product !== null) {
            $merged = array_merge($merged, $this->validateDealAttributes($product, $merged, (int) $deal->ProductDealId));
        }
        $features = $this->parseDealFeatures($attrs, $deal);
        return $this->buildDealUpdateResult($deal, $attrs, $merged, $features);
    }

    private function mergeDealAttributes(ProductDeal $deal, array $attrs): array
    {
        return [
            'dealName' => $attrs['dealName'] ?? $deal->dealName,
            'dealCode' => $attrs['dealCode'] ?? $deal->dealCode,
            'billingInterval' => $attrs['billingInterval'] ?? $deal->billingInterval,
            'priceCents' => $attrs['priceCents'] ?? $deal->priceCents,
            'allowedMachineSeats' => $attrs['allowedMachineSeats'] ?? $deal->allowedMachineSeats,
        ];
    }

    /** @return array<int,string> */
    private function parseDealFeatures(array $attrs, ProductDeal $deal): array
    {
        $features = $attrs['features'] ?? $deal->features;
        if (is_string($features)) {
            $features = array_values(array_filter(array_map('trim', explode("\n", $features))));
        }
        return is_array($features) ? $features : [];
    }

    /** @return array<string,mixed> */
    private function buildDealUpdateResult(ProductDeal $deal, array $attrs, array $merged, array $features): array
    {
        return [
            'dealName' => $merged['dealName'],
            'dealCode' => $merged['dealCode'],
            'billingInterval' => $merged['billingInterval'],
            'priceCents' => $merged['priceCents'],
            'allowedMachineSeats' => $merged['allowedMachineSeats'],
            'allowedDomains' => isset($attrs['allowedDomains']) ? (int) $attrs['allowedDomains'] : $deal->allowedDomains,
            'isDecoyTarget' => isset($attrs['isDecoyTarget']) ? (bool) $attrs['isDecoyTarget'] : $deal->isDecoyTarget,
            'features' => $features,
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
            : '- No pricing deals configured yet. Add a deal to this product, then regenerate the blueprint.';

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
