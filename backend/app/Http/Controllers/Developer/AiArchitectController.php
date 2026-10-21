<?php

declare(strict_types=1);

namespace App\Http\Controllers\Developer;

use App\Http\Controllers\Controller;
use App\Models\ProductDeal;
use App\Services\AiArchitectService;
use App\Services\ProductService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Throwable;

/**
 * Controller exposing Grounded AI Pricing Architect generation and one-click apply endpoints.
 */
class AiArchitectController extends Controller
{
    public function __construct(
        private readonly AiArchitectService $aiArchitectService,
        private readonly ProductService $productService
    ) {}

    /** Generate AI pricing proposal grounded in product metadata and user intent. */
    public function designPricing(Request $request): JsonResponse
    {
        $slug = trim((string) $request->input('productSlug', ''));
        $userPrompt = trim((string) $request->input('userPrompt', 'Design an optimal high-converting decoy pricing model for this software.'));

        $product = $this->productService->getProductBySlug($slug);
        if (!$product) {
            $name = trim((string) $request->input('productName', ''));
            if (!empty($name)) {
                $product = new \App\Models\Product([
                    'productName' => $name,
                    'productSlug' => $slug ?: \Illuminate\Support\Str::slug($name),
                    'category' => (string) $request->input('category', 'DesktopApp'),
                    'description' => (string) $request->input('description', ''),
                ]);
            } else {
                return response()->json(['IsSuccess' => false, 'ErrorCode' => 'ProductNotFound', 'Message' => 'Product not found.'], 404);
            }
        }

        try {
            $proposal = $this->aiArchitectService->designPricingStrategy($product, $userPrompt);
            return response()->json(['IsSuccess' => true, 'Data' => $proposal]);
        } catch (Throwable $e) {
            return response()->json(['IsSuccess' => false, 'ErrorCode' => 'AiGenerationFailed', 'Message' => $e->getMessage()], 500);
        }
    }

    /** Apply AI-generated deals to the product, updating database and live storefront. */
    public function applyAiDeals(string $slug, Request $request): JsonResponse
    {
        $product = $this->productService->getProductBySlug($slug);
        if (!$product) {
            return response()->json(['IsSuccess' => false, 'ErrorCode' => 'ProductNotFound', 'Message' => 'Product not found.'], 404);
        }

        $deals = $request->input('deals', []);
        if (!is_array($deals) || empty($deals)) {
            return response()->json(['IsSuccess' => false, 'ErrorCode' => 'InvalidDeals', 'Message' => 'Deals array is required.'], 422);
        }

        // Atomically replace existing deals for this product
        ProductDeal::where('productId', $product->ProductId)->delete();

        foreach ($deals as $deal) {
            $this->productService->createDeal($product, (array) $deal);
        }

        $refreshed = $this->productService->getProductBySlug($slug);
        return response()->json([
            'IsSuccess' => true,
            'Message' => 'AI deals successfully applied to product and live storefront.',
            'Data' => $refreshed,
        ]);
    }

    /** Generate framework-tailored client integration code via Gemini. */
    public function generateBlueprintCode(string $slug, Request $request): JsonResponse
    {
        $product = $this->productService->getProductBySlug($slug);
        if (!$product) {
            return response()->json(['IsSuccess' => false, 'ErrorCode' => 'ProductNotFound', 'Message' => 'Product not found.'], 404);
        }

        $framework = trim((string) $request->input('framework', 'universal-agent'));
        $notes = trim((string) $request->input('notes', ''));

        try {
            $blueprint = $this->aiArchitectService->generateClientBlueprintCode($product, $framework, $notes);
            return response()->json(['IsSuccess' => true, 'Data' => $blueprint]);
        } catch (Throwable $e) {
            return response()->json(['IsSuccess' => false, 'ErrorCode' => 'BlueprintGenerationFailed', 'Message' => $e->getMessage()], 500);
        }
    }
}
