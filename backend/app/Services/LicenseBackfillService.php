<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Product;
use App\Support\LicenseShardSchema;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use PDO;

/**
 * Phase 4 item 1: backfill / repair for pre-Phase-3 gateway keys.
 *
 * Schema facts (read from ProductService / PaymentWebhookService):
 * - Serials: SerialId, SerialValue UNIQUE, LicenseKey, ProductSlug,
 *   CustomerEmail NOT NULL, IsRevoked (0/1), IssuedAt, CreatedAt.
 *   No dealCode, no seats, no isActive, no expiry.
 * - ProductLicenses: id, serialValue UNIQUE, dealCode, allowedSeats,
 *   isActive, isRevoked, customerEmail, transactionId, expiresAt.
 * - WebhookEvents: EventId, Gateway, ProductSlug, CustomerEmail,
 *   SerialValue, PayloadJson (carries dealCode, no seats/expiry).
 * - Licenses (shard, legacy): LicenseKey, ProductSlug, CustomerEmail,
 *   DealCode, Status.
 *
 * Derivation priority: WebhookEvents dealCode, then Licenses dealCode,
 * then the product's single active deal, then a logged fallback
 * (first non-DEFAULT candidate else DEFAULT, 1 seat, null expiry).
 * Expiry uses LicenseExpiry::derive, anchored at Serials IssuedAt.
 * Revoked Serials rows promote as isRevoked=1 / isActive=0.
 * Live readers use database_path/ only; repodb/ is a legacy mirror.
 */
class LicenseBackfillService
{
    public function findSerial(PDO $pdo, string $serial): ?array
    {
        if (!$this->tableExists($pdo, 'Serials')) {
            return null;
        }
        $stmt = $pdo->prepare('SELECT * FROM Serials WHERE SerialValue = :s LIMIT 1');
        $stmt->execute([':s' => $serial]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ?: null;
    }

    public function promoteSerial(PDO $pdo, Product $product, array $serialRow): ?array
    {
        $slug = (string) $product->productSlug;
        if ((string) ($serialRow['ProductSlug'] ?? '') !== $slug) {
            Log::warning('license.backfill.slug_mismatch', ['productSlug' => $slug]);

            return null;
        }
        LicenseShardSchema::ensureProductLicenses($pdo);
        $serial = (string) $serialRow['SerialValue'];
        if ($this->findLicense($pdo, $serial) !== null) {
            return $this->findLicense($pdo, $serial);
        }

        return $this->doPromote($pdo, $product, $serialRow, $this->derive($pdo, $product, $serialRow));
    }

    private function doPromote(PDO $pdo, Product $product, array $serialRow, array $derived): ?array
    {
        $serial = (string) $serialRow['SerialValue'];
        $isRevoked = (int) ($serialRow['IsRevoked'] ?? 0) === 1;
        $this->insertLicenseRow($pdo, $serial, $derived, $isRevoked);
        $this->fillLicenseMeta($pdo, $serial, $derived);
        $row = $this->findLicense($pdo, $serial);
        $this->logPromotion($product, $derived, $isRevoked, $row !== null);

        return $row;
    }

    private function insertLicenseRow(PDO $pdo, string $serial, array $derived, bool $isRevoked): void
    {
        $ins = $pdo->prepare('INSERT OR IGNORE INTO ProductLicenses (serialValue, dealCode, allowedSeats, isActive, isRevoked, customerEmail, transactionId, expiresAt, createdAt) VALUES (:s, :d, :seats, :active, :revoked, :email, :tx, :exp, :dt)');
        $ins->execute([
            ':s' => $serial, ':d' => $derived['dealCode'],
            ':seats' => max(1, $derived['allowedSeats']),
            ':active' => $isRevoked ? 0 : 1, ':revoked' => $isRevoked ? 1 : 0,
            ':email' => $derived['customerEmail'], ':tx' => $derived['transactionId'],
            ':exp' => $derived['expiresAt'], ':dt' => Carbon::now()->toISOString(),
        ]);
    }

    private function fillLicenseMeta(PDO $pdo, string $serial, array $derived): void
    {
        $upd = $pdo->prepare('UPDATE ProductLicenses SET expiresAt = COALESCE(expiresAt, :exp), customerEmail = COALESCE(customerEmail, :email), transactionId = COALESCE(transactionId, :tx) WHERE serialValue = :s');
        $upd->execute([':exp' => $derived['expiresAt'], ':email' => $derived['customerEmail'], ':tx' => $derived['transactionId'], ':s' => $serial]);
    }

    private function logPromotion(Product $product, array $derived, bool $isRevoked, bool $promoted): void
    {
        if (!$promoted) {
            return;
        }
        Log::info('license.backfill.promoted', [
            'productSlug' => (string) $product->productSlug,
            'dealCode' => $derived['dealCode'], 'source' => $derived['source'],
            'fallback' => $derived['fallback'], 'isRevoked' => $isRevoked,
        ]);
    }

    public function backfillShard(PDO $pdo, Product $product): array
    {
        $stats = ['totalSerials' => 0, 'promoted' => 0, 'alreadyPresent' => 0, 'revoked' => 0, 'fallback' => 0];
        if (!$this->tableExists($pdo, 'Serials')) {
            return $stats;
        }
        LicenseShardSchema::ensureProductLicenses($pdo);
        $rows = $pdo->query('SELECT * FROM Serials')->fetchAll(PDO::FETCH_ASSOC);
        foreach ($rows as $row) {
            $this->processBackfillRow($pdo, $product, $row, $stats);
        }

        return $stats;
    }

    private function processBackfillRow(PDO $pdo, Product $product, array $row, array &$stats): void
    {
        if ((string) ($row['ProductSlug'] ?? '') !== (string) $product->productSlug) {
            return;
        }
        $stats['totalSerials']++;
        $serial = (string) $row['SerialValue'];
        if ($this->findLicense($pdo, $serial) !== null) {
            $stats['alreadyPresent']++;

            return;
        }
        $derived = $this->derive($pdo, $product, $row);
        $promoted = $this->doPromote($pdo, $product, $row, $derived);
        $this->countPromotion($promoted, $derived, $stats);
    }

    private function countPromotion(?array $promoted, array $derived, array &$stats): void
    {
        if ($promoted === null) {
            return;
        }
        $stats['promoted']++;
        if ((int) ($promoted['isRevoked'] ?? 0) === 1) {
            $stats['revoked']++;
        }
        if ($derived['fallback']) {
            $stats['fallback']++;
        }
    }

    public function backfillAll(?string $slug = null, bool $includeRepodb = false): array
    {
        $products = $slug !== null ? Product::query()->where('productSlug', $slug)->get() : Product::query()->get();
        $perProduct = [];
        $totals = ['totalSerials' => 0, 'promoted' => 0, 'alreadyPresent' => 0, 'revoked' => 0, 'fallback' => 0];
        foreach ($products as $product) {
            $perProduct[(string) $product->productSlug] = $this->backfillProduct($product, $includeRepodb, $totals);
        }

        return ['products' => $perProduct, 'totals' => $totals];
    }

    private function backfillProduct(Product $product, bool $includeRepodb, array &$totals): array
    {
        $slug = (string) $product->productSlug;
        $paths = [database_path("product_{$slug}.sqlite")];
        $repodbPath = base_path("repodb/product_{$slug}.sqlite");
        if ($includeRepodb && file_exists($repodbPath)) {
            $paths[] = $repodbPath;
        }
        $combined = ['totalSerials' => 0, 'promoted' => 0, 'alreadyPresent' => 0, 'revoked' => 0, 'fallback' => 0, 'shards' => []];
        foreach ($paths as $path) {
            $this->backfillPath($path, $product, $combined, $totals);
        }

        return $combined;
    }

    private function backfillPath(string $path, Product $product, array &$combined, array &$totals): void
    {
        if (!file_exists($path)) {
            return;
        }
        $pdo = new PDO("sqlite:{$path}");
        $stats = $this->backfillShard($pdo, $product);
        $combined['shards'][] = $path;
        foreach (['totalSerials', 'promoted', 'alreadyPresent', 'revoked', 'fallback'] as $key) {
            $combined[$key] += $stats[$key];
            $totals[$key] += $stats[$key];
        }
    }

    public function derive(PDO $pdo, Product $product, array $serialRow): array
    {
        $product->loadMissing('deals');
        $serial = (string) $serialRow['SerialValue'];
        $webhook = $this->findWebhookEvent((string) $product->productSlug, $serial);
        $candidates = $this->candidates($pdo, $serialRow, $webhook);
        $deal = $this->matchedDeal($product, $candidates);
        $source = $deal !== null ? 'webhook-event' : null;
        if ($deal === null) {
            $deal = $this->singleDealOrLog($product, $candidates, $source);
        }
        $email = $this->deriveEmail($serialRow, $webhook);
        $tx = $webhook['EventId'] ?? null;
        if ($deal !== null) {
            return $this->buildDealResult($deal, $email, $tx, $serialRow, $source ?? 'deal');
        }

        return $this->buildFallbackResult($candidates, $email, $tx, $product);
    }

    private function candidates(PDO $pdo, array $serialRow, ?array $webhook): array
    {
        $candidates = [];
        foreach ([$this->payloadDealCode($webhook), $this->licensesDealCode($pdo, $serialRow)] as $code) {
            if ($code !== null && $code !== '' && strtoupper($code) !== 'DEFAULT') {
                $candidates[] = $code;
            }
        }

        return $candidates;
    }

    private function matchedDeal(Product $product, array $candidates): mixed
    {
        foreach ($candidates as $code) {
            $deal = $this->matchDeal($product, $code);
            if ($deal !== null) {
                return $deal;
            }
        }

        return null;
    }

    private function singleDealOrLog(Product $product, array $candidates, ?string &$source): mixed
    {
        if ($candidates !== []) {
            Log::warning('license.backfill.deal_code_unmatched', ['productSlug' => (string) $product->productSlug, 'candidateDealCodes' => $candidates]);
        }
        $active = $product->deals->filter(fn ($d) => $d->isActive === null || (bool) $d->isActive)->values();
        if ($active->count() !== 1) {
            return null;
        }
        $source = 'single-deal';

        return $active->first();
    }

    private function deriveEmail(array $serialRow, ?array $webhook): ?string
    {
        $email = (string) ($serialRow['CustomerEmail'] ?? '') !== '' ? (string) $serialRow['CustomerEmail'] : ($webhook['CustomerEmail'] ?? null);

        return $email !== '' ? $email : null;
    }

    private function buildDealResult(mixed $deal, ?string $email, mixed $tx, array $serialRow, string $source): array
    {
        return [
            'dealCode' => (string) $deal->dealCode,
            'allowedSeats' => max(1, (int) $deal->allowedMachineSeats),
            'customerEmail' => $email, 'transactionId' => $tx,
            'expiresAt' => LicenseExpiry::derive((string) $deal->billingInterval, $this->issuedAt($serialRow)),
            'source' => $source, 'fallback' => false,
        ];
    }

    private function buildFallbackResult(array $candidates, ?string $email, mixed $tx, Product $product): array
    {
        Log::warning('license.backfill.fallback', ['productSlug' => (string) $product->productSlug, 'candidateDealCodes' => $candidates, 'dealCode' => $candidates[0] ?? 'DEFAULT']);

        return [
            'dealCode' => $candidates[0] ?? 'DEFAULT', 'allowedSeats' => 1,
            'customerEmail' => $email, 'transactionId' => $tx,
            'expiresAt' => null, 'source' => 'fallback', 'fallback' => true,
        ];
    }

    private function findLicense(PDO $pdo, string $serial): ?array
    {
        if (!$this->tableExists($pdo, 'ProductLicenses')) {
            return null;
        }
        $stmt = $pdo->prepare('SELECT * FROM ProductLicenses WHERE serialValue = :s LIMIT 1');
        $stmt->execute([':s' => $serial]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ?: null;
    }

    private function tableExists(PDO $pdo, string $table): bool
    {
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM sqlite_master WHERE type = 'table' AND name = :t");
        $stmt->execute([':t' => $table]);

        return (int) $stmt->fetchColumn() > 0;
    }

    private function findWebhookEvent(string $slug, string $serial): ?array
    {
        try {
            if (!Schema::connection('root')->hasTable('WebhookEvents')) {
                return null;
            }
            $row = DB::connection('root')->table('WebhookEvents')->where('ProductSlug', $slug)->where('SerialValue', $serial)->orderBy('created_at')->first();
        } catch (\Throwable) {
            return null;
        }

        return $row !== null ? (array) $row : null;
    }

    private function payloadDealCode(?array $webhook): ?string
    {
        if ($webhook === null || empty($webhook['PayloadJson'])) {
            return null;
        }
        $payload = json_decode((string) $webhook['PayloadJson'], true);
        if (!is_array($payload)) {
            return null;
        }
        $code = $payload['dealCode'] ?? null;

        return is_string($code) && $code !== '' ? $code : null;
    }

    private function licensesDealCode(PDO $pdo, array $serialRow): ?string
    {
        if (!$this->tableExists($pdo, 'Licenses')) {
            return null;
        }
        $key = (string) ($serialRow['LicenseKey'] ?? $serialRow['SerialValue'] ?? '');
        if ($key === '') {
            return null;
        }
        $stmt = $pdo->prepare('SELECT DealCode FROM Licenses WHERE LicenseKey = :k LIMIT 1');
        $stmt->execute([':k' => $key]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        $code = $row['DealCode'] ?? null;

        return is_string($code) && $code !== '' ? $code : null;
    }

    private function matchDeal(Product $product, string $code): mixed
    {
        foreach ($product->deals as $deal) {
            if (strtoupper((string) $deal->dealCode) === strtoupper($code)) {
                return $deal;
            }
        }

        return null;
    }

    private function issuedAt(array $serialRow): Carbon
    {
        foreach (['IssuedAt', 'CreatedAt'] as $field) {
            $value = (string) ($serialRow[$field] ?? '');
            if ($value !== '') {
                try {
                    return Carbon::parse($value);
                } catch (\Throwable) {
                }
            }
        }

        return Carbon::now();
    }
}
