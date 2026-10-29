<?php

declare(strict_types=1);

namespace App\Http\Controllers\Developer;

use App\Exceptions\AuthException;
use App\Exceptions\NotFoundException;
use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Services\FraudScoringService;
use App\Support\ApiEnvelope;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** Developer-only fraud score for a licence, for review, not auto-block. */
final class FraudScoreController extends Controller
{
    public function __construct(private readonly FraudScoringService $scoring)
    {
    }

    public function score(Request $request, string $productSlug): JsonResponse
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
        $serial = strtoupper(trim((string) $request->input('serialValue', '')));
        $result = $this->scoring->score((string) $product->productSlug, $serial);

        return ApiEnvelope::success(
            results: $result,
            requestId: (string) ($request->headers->get('X-Request-Id') ?? ''),
        );
    }
}
