<?php

declare(strict_types=1);

namespace App\Http\Controllers\Developer;

use App\Http\Controllers\Controller;
use App\Services\ProductService;
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

    public function index(): JsonResponse
    {
        $products = $this->productService->listProducts(1);
        return response()->json(['IsSuccess' => true, 'Data' => $products]);
    }

    public function show(string $slug): JsonResponse
    {
        $product = $this->productService->getProductBySlug($slug);
        if (!$product) {
            return response()->json(['IsSuccess' => false, 'ErrorCode' => 'ProductNotFound'], 404);
        }
        return response()->json(['IsSuccess' => true, 'Data' => $product]);
    }

    public function store(Request $request): JsonResponse
    {
        $name = trim((string) $request->input('productName', ''));
        if (empty($name)) {
            return response()->json(['IsSuccess' => false, 'ErrorCode' => 'InvalidInput', 'Message' => 'productName is required.'], 422);
        }
        $product = $this->productService->createProduct(1, $request->all());
        return response()->json(['IsSuccess' => true, 'Data' => $product], 201);
    }

    public function addDeal(string $slug, Request $request): JsonResponse
    {
        $product = $this->productService->getProductBySlug($slug);
        if (!$product) {
            return response()->json(['IsSuccess' => false, 'ErrorCode' => 'ProductNotFound'], 404);
        }
        $deal = $this->productService->createDeal($product, $request->all());
        return response()->json(['IsSuccess' => true, 'Data' => $deal], 201);
    }

    public function updateDeal(string $slug, int $dealId, Request $request): JsonResponse
    {
        $product = $this->productService->getProductBySlug($slug);
        if (!$product) {
            return response()->json(['IsSuccess' => false, 'ErrorCode' => 'ProductNotFound'], 404);
        }
        $deal = $this->productService->updateDeal($product, $dealId, $request->all());
        if (!$deal) {
            return response()->json(['IsSuccess' => false, 'ErrorCode' => 'DealNotFound', 'Message' => 'Deal not found.'], 404);
        }
        return response()->json(['IsSuccess' => true, 'Data' => $deal]);
    }

    public function deleteDeal(string $slug, int $dealId): JsonResponse
    {
        $product = $this->productService->getProductBySlug($slug);
        if (!$product) {
            return response()->json(['IsSuccess' => false, 'ErrorCode' => 'ProductNotFound'], 404);
        }
        $deleted = $this->productService->deleteDeal($product, $dealId);
        return response()->json(['IsSuccess' => $deleted]);
    }

    public function aiBlueprint(string $slug): JsonResponse
    {
        $product = $this->productService->getProductBySlug($slug);
        if (!$product) {
            return response()->json(['IsSuccess' => false, 'ErrorCode' => 'ProductNotFound'], 404);
        }
        $blueprint = $this->productService->generateAiBlueprint($product);
        return response()->json(['IsSuccess' => true, 'Data' => ['BlueprintMarkdown' => $blueprint, 'ProductSlug' => $slug]]);
    }

    public function webhookIssue(string $slug, Request $request): JsonResponse
    {
        $product = $this->productService->getProductBySlug($slug);
        if (!$product) {
            return response()->json(['IsSuccess' => false, 'ErrorCode' => 'ProductNotFound'], 404);
        }
        $txId = trim((string) $request->input('transactionId', ''));
        $email = trim((string) $request->input('customerEmail', ''));
        $dealCode = trim((string) $request->input('dealCode', ''));
        if (empty($txId) || empty($email)) {
            return response()->json(['IsSuccess' => false, 'ErrorCode' => 'InvalidPayload', 'Message' => 'transactionId and customerEmail are required.'], 422);
        }
        $serialValue = strtoupper($product->productSlug . '-' . substr(md5($txId . $email), 0, 16));
        return response()->json([
            'IsSuccess' => true,
            'Message' => 'License issued successfully via decoupled webhook.',
            'Data' => [
                'SerialValue' => $serialValue,
                'CustomerEmail' => $email,
                'ProductSlug' => $product->productSlug,
                'DealCode' => $dealCode ?: 'DEFAULT',
                'IssuedAt' => now()->toISOString(),
            ],
        ]);
    }

    public function publicDeals(string $slug): JsonResponse
    {
        $product = $this->productService->getProductBySlug($slug);
        if (!$product) {
            return response()->json(['IsSuccess' => false, 'ErrorCode' => 'ProductNotFound', 'Message' => 'Product not found.'], 404);
        }
        return response()->json([
            'IsSuccess' => true,
            'Data' => [
                'productSlug' => $product->productSlug,
                'productName' => $product->productName,
                'category' => $product->category,
                'description' => $product->description,
                'checkIntervalDays' => $product->checkIntervalDays,
                'deals' => $product->deals->where('isActive', true)->values(),
            ],
        ]);
    }
}
