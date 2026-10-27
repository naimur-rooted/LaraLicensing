<?php

declare(strict_types=1);

namespace App\Http\Controllers\Developer;

use App\Exceptions\AuthException;
use App\Exceptions\NotFoundException;
use App\Exceptions\ValidationException;
use App\Http\Controllers\Controller;
use App\Services\ProductService;
use App\Support\AuditWriter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Controller handling Developer Portal Product actions, Deals sync, and AI Blueprints.
 */
class ProductController extends Controller
{
    public function __construct(
        private readonly ProductService $productService
    ) {}

    public function index(Request $request): JsonResponse
    {
        $userId = $this->resolveUserId($request);
        $products = $userId > 0 ? $this->productService->listProducts($userId) : collect();
        return response()->json(['IsSuccess' => true, 'Data' => $products]);
    }

    public function show(string $slug, Request $request): JsonResponse
    {
        $product = $this->productService->getProductBySlug($slug);
        if (!$product) {
            throw NotFoundException::notFound('Product not found.');
        }
        $this->requireOwnerForRequest($product, $request);
        return response()->json(['IsSuccess' => true, 'Data' => $product]);
    }

    // Defence in depth: route middleware already requires Sanctum auth,
    // but a direct controller call must still not leak another developer's
    // product. The product owner is the only caller allowed here.
    private function requireOwnerForRequest($product, Request $request): void
    {
        $userId = $this->resolveUserId($request);
        if ($userId <= 0) {
            throw AuthException::unauthorized('Authentication is required.');
        }
        $ownerId = (int) ($product->userId ?? $product->UserId ?? 0);
        if ($ownerId !== $userId) {
            throw AuthException::forbidden('You do not have permission to access this product.');
        }
    }

    public function store(Request $request): JsonResponse
    {
        $name = trim((string) $request->input('productName', ''));
        if (empty($name)) {
            throw ValidationException::invalidInput('productName is required.');
        }
        $userId = $this->resolveUserId($request);
        if ($userId <= 0) {
            throw AuthException::unauthorized('Authentication is required.');
        }
        $product = $this->productService->createProduct($userId, $request->all());
        AuditWriter::write($request, 'ProductCreated', 'Products', (int) $product->ProductId, [
            'ProductSlug' => (string) $product->productSlug,
            'ProductName' => (string) $product->productName,
        ]);
        return response()->json(['IsSuccess' => true, 'Data' => $product], 201);
    }

    private function resolveUserId(Request $request): int
    {
        $userId = (int) ($request->user()?->UserId ?? auth()->id() ?? auth('sanctum')->id());
        return $userId > 0 ? $userId : 0;
    }

    public function addDeal(string $slug, Request $request): JsonResponse
    {
        $product = $this->productService->getProductBySlug($slug);
        if (!$product) {
            throw NotFoundException::notFound('Product not found.');
        }
        $this->requireOwnerForRequest($product, $request);
        $deal = $this->productService->createDeal($product, $request->all());
        return response()->json(['IsSuccess' => true, 'Data' => $deal], 201);
    }

    public function updateDeal(string $slug, int $dealId, Request $request): JsonResponse
    {
        $product = $this->productService->getProductBySlug($slug);
        if (!$product) {
            throw NotFoundException::notFound('Product not found.');
        }
        $this->requireOwnerForRequest($product, $request);
        $deal = $this->productService->updateDeal($product, $dealId, $request->all());
        if (!$deal) {
            throw NotFoundException::notFound('Deal not found.');
        }
        return response()->json(['IsSuccess' => true, 'Data' => $deal]);
    }

    public function deleteDeal(string $slug, int $dealId, Request $request): JsonResponse
    {
        $product = $this->productService->getProductBySlug($slug);
        if (!$product) {
            throw NotFoundException::notFound('Product not found.');
        }
        $this->requireOwnerForRequest($product, $request);
        $deleted = $this->productService->deleteDeal($product, $dealId);
        if (!$deleted) {
            throw NotFoundException::notFound('Deal not found.');
        }
        return response()->json(['IsSuccess' => true]);
    }

    public function aiBlueprint(string $slug, Request $request): JsonResponse
    {
        $product = $this->productService->getProductBySlug($slug);
        if (!$product) {
            throw NotFoundException::notFound('Product not found.');
        }
        $this->requireOwnerForRequest($product, $request);
        $blueprint = $this->productService->generateAiBlueprint($product);
        return response()->json(['IsSuccess' => true, 'Data' => ['BlueprintMarkdown' => $blueprint, 'ProductSlug' => $slug]]);
    }

    public function webhookIssue(string $slug, Request $request): JsonResponse
    {
        $product = $this->productService->getProductBySlug($slug);
        if (!$product) {
            throw NotFoundException::notFound('Product not found.');
        }
        $txId = trim((string) $request->input('transactionId', ''));
        $email = trim((string) $request->input('customerEmail', ''));
        if (empty($txId) || empty($email)) {
            throw ValidationException::invalidInput('transactionId and customerEmail are required.');
        }
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw ValidationException::invalidInput('customerEmail must be a valid email address.');
        }

        $dealCode = trim((string) $request->input('dealCode', ''));
        // A license must be linked to a real deal for this product.
        // Previously an unknown dealCode fell back to 'DEFAULT' and minted
        // a license with no deal linkage, so portal and verify could not
        // tell what was actually purchased.
        $deal = $dealCode !== '' ? $product->deals->where('dealCode', strtoupper($dealCode))->first() : null;
        if (!$deal) {
            throw ValidationException::invalidInput('dealCode must match an existing deal for this product.');
        }
        $seats = (int) $deal->allowedMachineSeats;
        $resolvedDeal = (string) $deal->dealCode;

        $serialValue = strtoupper($product->productSlug . '-' . substr(md5($txId . $email), 0, 16));
        $this->productService->recordShardLicense($product, $serialValue, $resolvedDeal, $seats);

        $payload = [
            'SerialValue' => $serialValue,
            'CustomerEmail' => $email,
            'ProductSlug' => $product->productSlug,
            'DealCode' => $resolvedDeal,
            'IssuedAt' => now()->toISOString(),
        ];
        return response()->json([
            'IsSuccess' => true,
            'Message' => 'License issued successfully via decoupled webhook.',
            'Data' => $payload,
        ]);
    }

    private function buildWebhookPayload($product, string $txId, string $email, string $dealCode): array
    {
        $serialValue = strtoupper($product->productSlug . '-' . substr(md5($txId . $email), 0, 16));
        return [
            'SerialValue' => $serialValue,
            'CustomerEmail' => $email,
            'ProductSlug' => $product->productSlug,
            'DealCode' => $dealCode ?: 'DEFAULT',
            'IssuedAt' => now()->toISOString(),
        ];
    }

    public function publicDeals(string $slug): JsonResponse
    {
        $product = $this->productService->getProductBySlug($slug);
        if (!$product) {
            throw NotFoundException::notFound('Product not found.');
        }
        return response()->json([
            'IsSuccess' => true,
            'Data' => $this->formatPublicDeals($product),
        ]);
    }

    private function formatPublicDeals($product): array
    {
        return [
            'productSlug' => $product->productSlug,
            'productName' => $product->productName,
            'category' => $product->category,
            'description' => $product->description,
            'checkIntervalDays' => $product->checkIntervalDays,
            'deals' => $product->deals->where('isActive', true)->values(),
        ];
    }
}
