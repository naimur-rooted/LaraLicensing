<?php

declare(strict_types=1);

namespace App\Http\Controllers\Developer;

use App\Exceptions\AuthException;
use App\Exceptions\NotFoundException;
use App\Exceptions\ValidationException;
use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Services\LicenseExpiry;
use App\Support\LicenseShardSchema;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use PDO;
use Throwable;

/**
 * Controller providing Developer Portal visibility into product-isolated SQLite shard licenses.
 */
class DeveloperLicenseController extends Controller
{
    /**
     * List all licenses and bound machine devices for a given developer product shard.
     */
    public function listProductLicenses(Request $request, string $productSlug): JsonResponse
    {
        $userId = $this->resolveUserId($request);
        if ($userId <= 0) {
            throw AuthException::unauthorized('Authentication is required.');
        }

        $product = Product::where('productSlug', $productSlug)->first();
        if (!$product) {
            throw NotFoundException::notFound("Product '{$productSlug}' not found.");
        }

        $ownerId = (int) ($product->userId ?? $product->UserId ?? 0);
        if ($ownerId !== $userId) {
            throw AuthException::forbidden('You do not have permission to view licenses for this product.');
        }

        $shardPath = database_path("product_{$productSlug}.sqlite");
        if (!file_exists($shardPath)) {
            return response()->json([
                'IsSuccess' => true,
                'Data' => [],
            ]);
        }

        try {
            $pdo = new PDO("sqlite:{$shardPath}");
            $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

            $checkTable = $pdo->query("SELECT name FROM sqlite_master WHERE type='table' AND name='ProductLicenses'")->fetch();
            if (!$checkTable) {
                return response()->json([
                    'IsSuccess' => true,
                    'Data' => [],
                ]);
            }

            $stmt = $pdo->query('SELECT id, serialValue, dealCode, allowedSeats, isActive, isRevoked, createdAt FROM ProductLicenses ORDER BY id DESC');
            $licenses = $stmt->fetchAll(PDO::FETCH_ASSOC);

            $data = [];
            foreach ($licenses as $lic) {
                $licenseId = (int) $lic['id'];
                $expiresAt = $this->licenseExpiresAt($pdo, $licenseId);
                $activeSeats = $this->countMachineSeats($pdo, $licenseId);

                $bStmt = $pdo->prepare('SELECT hostname, hardwareDigest, boundAt FROM HardwareBindings WHERE licenseId = :lid ORDER BY id DESC');
                $bStmt->execute([':lid' => $licenseId]);
                $bindings = $bStmt->fetchAll(PDO::FETCH_ASSOC);

                $boundMachines = array_map(function ($b): array {
                    return [
                        'hostname' => (string) ($b['hostname'] ?? 'Client PC'),
                        'hardwareDigest' => (string) ($b['hardwareDigest'] ?? ''),
                        'boundAt' => (string) ($b['boundAt'] ?? ''),
                    ];
                }, $bindings);

                $isRevoked = !empty($lic['isRevoked']);
                $isActive = (bool) $lic['isActive'] && !$isRevoked;

                $data[] = [
                    'licenseId' => $licenseId,
                    'serialValue' => (string) $lic['serialValue'],
                    'dealCode' => (string) $lic['dealCode'],
                    'allowedSeats' => (int) $lic['allowedSeats'],
                    'activeSeats' => $activeSeats,
                    'isActive' => $isActive,
                    'expiresAt' => $expiresAt,
                    'createdAt' => (string) $lic['createdAt'],
                    'boundMachines' => $boundMachines,
                ];
            }

            return response()->json([
                'IsSuccess' => true,
                'Data' => $data,
            ]);
        } catch (Throwable $e) {
            return response()->json([
                'IsSuccess' => false,
                'ErrorCode' => 'ShardReadFailed',
                'Message' => 'Failed to read product license shard: ' . $e->getMessage(),
            ], 500);
        }
    }

    private function licenseExpiresAt(PDO $pdo, int $licenseId): ?string
    {
        $cols = $pdo->query('PRAGMA table_info(ProductLicenses)')->fetchAll(PDO::FETCH_ASSOC);
        foreach ($cols as $col) {
            if (($col['name'] ?? '') === 'expiresAt') {
                $stmt = $pdo->prepare('SELECT expiresAt FROM ProductLicenses WHERE id = :lid LIMIT 1');
                $stmt->execute([':lid' => $licenseId]);

                return (string) ($stmt->fetch(PDO::FETCH_ASSOC)['expiresAt'] ?? '') ?: null;
            }
        }

        return null;
    }

    private function countMachineSeats(PDO $pdo, int $licenseId): int
    {
        $check = $pdo->query("SELECT name FROM sqlite_master WHERE type='table' AND name='MachineBindings'")->fetch();
        if (!$check) {
            return 0;
        }
        $stmt = $pdo->prepare('SELECT COUNT(*) as cnt FROM MachineBindings WHERE LicenseId = :lid AND ReleasedAt IS NULL');
        $stmt->execute([':lid' => $licenseId]);

        return (int) ($stmt->fetch(PDO::FETCH_ASSOC)['cnt'] ?? 0);
    }

    /**
     * Delete a license from the product shard if it has no active machine bindings.
     */
    public function deleteLicense(Request $request, string $productSlug, int $licenseId): JsonResponse
    {
        $userId = $this->resolveUserId($request);
        if ($userId <= 0) {
            throw AuthException::unauthorized('Authentication is required.');
        }

        $product = Product::where('productSlug', $productSlug)->first();
        if (!$product) {
            throw NotFoundException::notFound("Product '{$productSlug}' not found.");
        }

        $ownerId = (int) ($product->userId ?? $product->UserId ?? 0);
        if ($ownerId !== $userId) {
            throw AuthException::forbidden('You do not have permission to delete licenses for this product.');
        }

        $shardPath = database_path("product_{$productSlug}.sqlite");
        if (!file_exists($shardPath)) {
            throw NotFoundException::notFound('Product shard not found.');
        }

        $pdo = new PDO("sqlite:{$shardPath}");
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        $checkTable = $pdo->query("SELECT name FROM sqlite_master WHERE type='table' AND name='ProductLicenses'")->fetch();
        if (!$checkTable) {
            throw NotFoundException::notFound('License not found.');
        }

        $stmt = $pdo->prepare('SELECT id FROM ProductLicenses WHERE id = :lid LIMIT 1');
        $stmt->execute([':lid' => $licenseId]);
        $license = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$license) {
            throw NotFoundException::notFound('License not found.');
        }

        $checkHbTable = $pdo->query("SELECT name FROM sqlite_master WHERE type='table' AND name='HardwareBindings'")->fetch();
        $activeSeats = 0;
        if ($checkHbTable) {
            $bStmt = $pdo->prepare('SELECT COUNT(*) as cnt FROM HardwareBindings WHERE licenseId = :lid');
            $bStmt->execute([':lid' => $licenseId]);
            $activeSeats = (int) ($bStmt->fetch(PDO::FETCH_ASSOC)['cnt'] ?? 0);
        }

        if ($activeSeats > 0) {
            $conflictStatus = 409;
            return response()->json([
                'IsSuccess' => false,
                'ErrorCode' => 'ActiveSeatsConflict',
                'Message' => 'Cannot delete an active license with bound machines; revoke it instead.',
            ], $conflictStatus);
        }

        $delStmt = $pdo->prepare('DELETE FROM ProductLicenses WHERE id = :lid');
        $delStmt->execute([':lid' => $licenseId]);

        $checkMbTable = $pdo->query("SELECT name FROM sqlite_master WHERE type='table' AND name='MachineBindings'")->fetch();
        if ($checkMbTable) {
            $delMb = $pdo->prepare('DELETE FROM MachineBindings WHERE LicenseId = :lid');
            $delMb->execute([':lid' => $licenseId]);
        }

        return response()->json([
            'IsSuccess' => true,
            'Message' => 'License deleted successfully.',
        ]);
    }

    /**
     * Revoke a license: mark it revoked/inactive and release all bound
     * machines, so the developer can then delete it. This is the endpoint
     * deleteLicense's 409 message points to ("revoke it instead").
     */
    public function revokeLicense(Request $request, string $productSlug, int $licenseId): JsonResponse
    {
        $userId = $this->resolveUserId($request);
        if ($userId <= 0) {
            throw AuthException::unauthorized('Authentication is required.');
        }

        $product = Product::where('productSlug', $productSlug)->first();
        if (!$product) {
            throw NotFoundException::notFound("Product '{$productSlug}' not found.");
        }

        $ownerId = (int) ($product->userId ?? $product->UserId ?? 0);
        if ($ownerId !== $userId) {
            throw AuthException::forbidden('You do not have permission to revoke licenses for this product.');
        }

        $shardPath = database_path("product_{$productSlug}.sqlite");
        if (!file_exists($shardPath)) {
            throw NotFoundException::notFound('Product shard not found.');
        }

        $pdo = new PDO("sqlite:{$shardPath}");
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        $stmt = $pdo->prepare('SELECT id, isRevoked FROM ProductLicenses WHERE id = :lid LIMIT 1');
        $stmt->execute([':lid' => $licenseId]);
        $license = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$license) {
            throw NotFoundException::notFound('License not found.');
        }

        $pdo->prepare('UPDATE ProductLicenses SET isRevoked = 1, isActive = 0 WHERE id = :lid')
            ->execute([':lid' => $licenseId]);

        $checkHbTable = $pdo->query("SELECT name FROM sqlite_master WHERE type='table' AND name='HardwareBindings'")->fetch();
        if ($checkHbTable) {
            $pdo->prepare('DELETE FROM HardwareBindings WHERE licenseId = :lid')->execute([':lid' => $licenseId]);
        }
        $checkMbTable = $pdo->query("SELECT name FROM sqlite_master WHERE type='table' AND name='MachineBindings'")->fetch();
        if ($checkMbTable) {
            $pdo->prepare('DELETE FROM MachineBindings WHERE LicenseId = :lid')->execute([':lid' => $licenseId]);
        }

        return response()->json([
            'IsSuccess' => true,
            'Message' => 'License revoked successfully.',
        ]);
    }

    /**
     * Phase 4 item 2: Sanctum-authenticated simulated (test) licence mint.
     *
     * This replaces the frontend's previous call to the HMAC-gated
     * webhookIssue endpoint, which now returns 401 without a gateway
     * signature. The browser never holds, sends, or receives the
     * product webhookSecret or ed25519 private key: auth is the
     * developer's Sanctum Bearer token plus the product-owner check
     * below, and the response carries only licence scalars (never the
     * Product model, whose $hidden fields must stay hidden).
     *
     * Marker: ProductLicenses has NO simulated/test column (schema
     * changes belong to another worker), so a minted test licence is
     * marked by the existing simulated-issuance pattern — a
     * transactionId starting with `tx_mock_` (generated here when the
     * caller omits one) and, by default, the test customerEmail
     * `developer-test@domain.local`. Callers that supply their own
     * transactionId should keep the `tx_mock_` prefix or the row is
     * not distinguishable from a gateway licence. `isSimulated` in
     * the response is endpoint-derived, not a stored column.
     *
     * Replay choice (explicit, not ambiguous): NEW SERIAL PER CALL.
     * Every POST mints a fresh serial, even with an identical body;
     * mint is not idempotent. Each "Mint Test Serial" click therefore
     * adds one row to the portal list, and callers that want dedup
     * must check the list first. Expiry is derived from the deal's
     * billingInterval via LicenseExpiry; seats from allowedMachineSeats.
     */
    public function mintLicense(Request $request, string $productSlug): JsonResponse
    {
        $userId = $this->resolveUserId($request);
        if ($userId <= 0) {
            throw AuthException::unauthorized('Authentication is required.');
        }

        $product = Product::with('deals')->where('productSlug', $productSlug)->first();
        if (!$product || !(bool) ($product->isActive ?? false)) {
            throw NotFoundException::notFound("Product '{$productSlug}' not found.");
        }

        $ownerId = (int) ($product->userId ?? $product->UserId ?? 0);
        if ($ownerId !== $userId) {
            throw AuthException::forbidden('You do not have permission to mint licenses for this product.');
        }

        $deal = $this->resolveMintDeal($product, trim((string) $request->input('dealCode', '')));
        $email = strtolower(trim((string) $request->input('customerEmail', '')));
        if ($email === '') {
            $email = 'developer-test@domain.local';
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw ValidationException::invalidInput('customerEmail must be a valid email address.');
        }
        $txId = trim((string) $request->input('transactionId', ''));
        if ($txId === '') {
            $txId = 'tx_mock_' . bin2hex(random_bytes(8));
        }

        $dealCode = (string) $deal->dealCode;
        $seats = max(1, (int) $deal->allowedMachineSeats);
        $expiresAt = LicenseExpiry::derive((string) $deal->billingInterval);
        $serial = $this->insertMintedLicense($productSlug, $dealCode, $seats, $email, $txId, $expiresAt);
        $this->insertMintedSerial($productSlug, $serial, $email);

        return response()->json([
            'IsSuccess' => true,
            'Data' => [
                'serialValue' => $serial,
                'dealCode' => $dealCode,
                'allowedSeats' => $seats,
                'customerEmail' => $email,
                'transactionId' => $txId,
                'expiresAt' => $expiresAt,
                'isSimulated' => true,
            ],
        ], 201);
    }

    /**
     * Resolve the deal for a mint: an explicit dealCode must match an
     * existing deal for this product (same rule as webhookIssue — no
     * DEFAULT fallback); an omitted dealCode uses the product's first
     * active deal, so the portal "Mint Test Serial" button works
     * without the caller knowing a deal code.
     */
    private function resolveMintDeal(Product $product, string $dealCode)
    {
        $deals = $product->deals ?? collect();
        if ($dealCode !== '') {
            $deal = $deals->first(fn ($d) => strtoupper((string) $d->dealCode) === strtoupper($dealCode));
            if (!$deal) {
                throw ValidationException::invalidInput('dealCode must match an existing deal for this product.');
            }

            return $deal;
        }

        $deal = $deals->first(fn ($d) => (bool) ($d->isActive ?? false));
        if (!$deal) {
            throw ValidationException::invalidInput('Product has no active deal to mint a test license for.');
        }

        return $deal;
    }

    /**
     * Insert one ProductLicenses row with a fresh random serial.
     * Retries on the (negligible) serial collision; the serial is
     * random per call, which is what makes mint non-idempotent.
     */
    private function insertMintedLicense(string $slug, string $dealCode, int $seats, string $email, string $txId, ?string $expiresAt): string
    {
        $pdo = $this->openMintShard($slug);
        LicenseShardSchema::ensureProductLicenses($pdo);
        $now = now()->toISOString();

        for ($attempt = 0; $attempt < 3; $attempt++) {
            $serial = strtoupper($slug) . '-TEST-' . strtoupper(bin2hex(random_bytes(8)));
            $stmt = $pdo->prepare('INSERT OR IGNORE INTO ProductLicenses (serialValue, dealCode, allowedSeats, isActive, isRevoked, customerEmail, transactionId, expiresAt, createdAt) VALUES (:s, :d, :seats, 1, 0, :email, :tx, :exp, :dt)');
            $stmt->execute([':s' => $serial, ':d' => $dealCode, ':seats' => $seats, ':email' => $email, ':tx' => $txId, ':exp' => $expiresAt, ':dt' => $now]);
            if ($stmt->rowCount() === 1) {
                return $serial;
            }
        }

        throw ValidationException::invalidInput('Could not mint a unique test license serial.');
    }

    /**
     * Dual-write the minted serial to Serials, mirroring the gateway
     * dual-write in PaymentWebhookService, so verify/activate lookups
     * that read Serials see test licences too. The repodb copy is
     * written only if that shard file already exists (same rule as
     * the gateway path); it is never created from scratch here.
     */
    private function insertMintedSerial(string $slug, string $serial, string $email): void
    {
        $now = now()->toDateTimeString();
        $paths = [database_path("product_{$slug}.sqlite")];
        $repodbPath = base_path("repodb/product_{$slug}.sqlite");
        if (file_exists($repodbPath)) {
            $paths[] = $repodbPath;
        }

        foreach ($paths as $path) {
            $pdo = new PDO("sqlite:{$path}");
            $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
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
            $stmt = $pdo->prepare('INSERT OR IGNORE INTO Serials (SerialValue, LicenseKey, ProductSlug, CustomerEmail, IsRevoked, IssuedAt, CreatedAt, UpdatedAt) VALUES (?, ?, ?, ?, 0, ?, ?, ?)');
            $stmt->execute([$serial, $serial, $slug, $email, $now, $now, $now]);
        }
    }

    private function openMintShard(string $slug): PDO
    {
        $path = database_path("product_{$slug}.sqlite");
        if (!file_exists($path)) {
            touch($path);
        }
        $pdo = new PDO("sqlite:{$path}");
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        return $pdo;
    }

    /**
     * Resolve the authenticated user ID from Sanctum token or session.
     */
    private function resolveUserId(Request $request): int
    {
        $userId = (int) ($request->user()?->UserId ?? auth()->id() ?? auth('sanctum')->id());
        return $userId > 0 ? $userId : 0;
    }
}
