<?php

declare(strict_types=1);

namespace App\Http\Controllers\Developer;

use App\Exceptions\AuthException;
use App\Exceptions\NotFoundException;
use App\Exceptions\ValidationException;
use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\ReleaseManifest;
use App\Services\LicenceGateService;
use App\Services\TamperManifestService;
use App\Support\ApiEnvelope;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use PDO;

/**
 * Signed release manifests and tamper verification.
 *
 * A developer signs a release manifest with the product Ed25519 key.
 * A client verifies the manifest signature and its own file hashes
 * against that manifest, and must fail closed on any mismatch.
 * A tamper report is logged for developer review only; it never
 * auto-revokes a licence, because a client report alone is not
 * proof against a spoofed report.
 */
final class TamperManifestController extends Controller
{
    public function __construct(private readonly TamperManifestService $manifestService, private readonly LicenceGateService $gate = new LicenceGateService())
    {
    }

    public function store(Request $request, string $productSlug): JsonResponse
    {
        $product = $this->requireOwnerProduct($request, $productSlug);
        $version = trim((string) $request->input('releaseVersion', ''));
        if ($version === '') {
            throw ValidationException::invalidInput('releaseVersion is required.');
        }
        $model = $this->manifestService->signManifest($product, $version, (array) $request->input('files', []), $request->input('minVersion'));

        return ApiEnvelope::success(
            results: ['Item' => $this->manifestService->toPublicArray($model)],
            requestId: (string) ($request->headers->get('X-Request-Id') ?? ''),
            httpCode: 201,
        );
    }

    public function verify(Request $request, string $productSlug): JsonResponse
    {
        $product = Product::where('productSlug', $productSlug)->where('isActive', true)->first();
        if ($product === null) {
            throw NotFoundException::notFound("Product '{$productSlug}' not found.");
        }
        $manifest = (array) $request->input('manifest', []);
        $checked = $this->manifestService->verifyManifest($product, $manifest);
        $mismatches = $checked['isValid'] ? $this->manifestService->verifySubmittedFiles($manifest['files'] ?? [], (array) $request->input('files', [])) : [];
        $isValid = $checked['isValid'] && $mismatches === [];
        $reason = $isValid ? null : ($checked['reason'] ?? 'FileHashMismatch');
        if ($isValid === false) {
            $this->logTamperEvent($productSlug, (string) $request->input('serialValue', ''), (string) $request->ip(), (string) $request->input('hardwareDigest', ''), $reason, $mismatches);
        }

        $serial = (string) $request->input('serialValue', '');
        $licenceStatus = $serial !== '' ? $this->gate->check($productSlug, strtoupper($serial))['status'] : null;

        return ApiEnvelope::success(
            results: ['isValid' => $isValid, 'reason' => $reason, 'mismatches' => $mismatches, 'failClosed' => $isValid === false, 'licenceStatus' => $licenceStatus],
            requestId: (string) ($request->headers->get('X-Request-Id') ?? ''),
        );
    }

    public function list(Request $request, string $productSlug): JsonResponse
    {
        $product = $this->requireOwnerProduct($request, $productSlug);
        $items = ReleaseManifest::query()->where('productSlug', (string) $product->productSlug)->orderByDesc('ReleaseManifestId')->get();

        return ApiEnvelope::success(
            results: ['Items' => $items->all()],
            requestId: (string) ($request->headers->get('X-Request-Id') ?? ''),
        );
    }

    private function requireOwnerProduct(Request $request, string $productSlug): Product
    {
        $userId = (int) ($request->user()?->UserId ?? auth()->id() ?? 0);
        if ($userId <= 0) {
            throw AuthException::unauthorized('Authentication is required.');
        }
        $product = Product::where('productSlug', $productSlug)->first();
        if ($product === null) {
            throw NotFoundException::notFound("Product '{$productSlug}' not found.");
        }
        $ownerId = (int) ($product->userId ?? $product->UserId ?? 0);
        if ($ownerId !== $userId) {
            throw AuthException::forbidden('You do not have permission for this product.');
        }

        return $product;
    }

    private function logTamperEvent(string $slug, string $serial, string $ip, string $hwid, ?string $reason, array $mismatches): void
    {
        $shardPath = database_path("product_{$slug}.sqlite");
        if (file_exists($shardPath) === false) {
            return;
        }
        $pdo = new PDO("sqlite:{$shardPath}");
        $pdo->exec('CREATE TABLE IF NOT EXISTS SecurityAuditEvents (id INTEGER PRIMARY KEY AUTOINCREMENT, serialValue TEXT NOT NULL, clientIp TEXT NOT NULL, hardwareDigest TEXT NOT NULL, reasons TEXT NOT NULL, createdAt TEXT NOT NULL);');
        $stmt = $pdo->prepare('INSERT INTO SecurityAuditEvents (serialValue, clientIp, hardwareDigest, reasons, createdAt) VALUES (:s, :ip, :hwid, :r, :dt)');
        $stmt->execute([':s' => $serial, ':ip' => $ip, ':hwid' => $hwid, ':r' => json_encode(['Tamper: ' . $reason, 'Mismatches' => $mismatches]), ':dt' => Carbon::now()->toISOString()]);
    }
}
