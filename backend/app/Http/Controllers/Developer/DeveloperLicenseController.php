<?php

declare(strict_types=1);

namespace App\Http\Controllers\Developer;

use App\Exceptions\AuthException;
use App\Exceptions\NotFoundException;
use App\Http\Controllers\Controller;
use App\Models\Product;
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
                    'activeSeats' => count($boundMachines),
                    'isActive' => $isActive,
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

    /**
     * Resolve the authenticated user ID from Sanctum token or session.
     */
    private function resolveUserId(Request $request): int
    {
        $userId = (int) ($request->user()?->UserId ?? auth()->id() ?? auth('sanctum')->id());
        return $userId > 0 ? $userId : 0;
    }
}
