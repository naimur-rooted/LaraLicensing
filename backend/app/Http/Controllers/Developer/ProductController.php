<?php

declare(strict_types=1);

namespace App\Http\Controllers\Developer;

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
        $products = $this->productService->listProducts($userId);
        return response()->json(['IsSuccess' => true, 'Data' => $products]);
    }

    public function show(string $slug): JsonResponse
    {
        $product = $this->productService->getProductBySlug($slug);
        if (!$product) {
            throw NotFoundException::notFound('Product not found.');
        }
        return response()->json(['IsSuccess' => true, 'Data' => $product]);
    }

    public function store(Request $request): JsonResponse
    {
        $name = trim((string) $request->input('productName', ''));
        if (empty($name)) {
            throw ValidationException::invalidInput('productName is required.');
        }
        $userId = $this->resolveUserId($request);
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
        return $userId > 0 ? $userId : 1;
    }

    public function addDeal(string $slug, Request $request): JsonResponse
    {
        $product = $this->productService->getProductBySlug($slug);
        if (!$product) {
            throw NotFoundException::notFound('Product not found.');
        }
        $deal = $this->productService->createDeal($product, $request->all());
        return response()->json(['IsSuccess' => true, 'Data' => $deal], 201);
    }

    public function updateDeal(string $slug, int $dealId, Request $request): JsonResponse
    {
        $product = $this->productService->getProductBySlug($slug);
        if (!$product) {
            throw NotFoundException::notFound('Product not found.');
        }
        $deal = $this->productService->updateDeal($product, $dealId, $request->all());
        if (!$deal) {
            throw NotFoundException::notFound('Deal not found.');
        }
        return response()->json(['IsSuccess' => true, 'Data' => $deal]);
    }

    public function deleteDeal(string $slug, int $dealId): JsonResponse
    {
        $product = $this->productService->getProductBySlug($slug);
        if (!$product) {
            throw NotFoundException::notFound('Product not found.');
        }
        $deleted = $this->productService->deleteDeal($product, $dealId);
        return response()->json(['IsSuccess' => $deleted]);
    }

    public function aiBlueprint(string $slug): JsonResponse
    {
        $product = $this->productService->getProductBySlug($slug);
        if (!$product) {
            throw NotFoundException::notFound('Product not found.');
        }
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

        $dealCode = trim((string) $request->input('dealCode', ''));
        $payload = $this->buildWebhookPayload($product, $txId, $email, $dealCode);
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
