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
        // Blueprint auto-generation on creation, reusing the existing
        // on-demand format. Data shape is unchanged; the blueprint is an
        // additional top-level field so existing clients keep working.
        $blueprint = $this->productService->generateAiBlueprint($product);

        return response()->json(['IsSuccess' => true, 'Data' => $product, 'BlueprintMarkdown' => $blueprint], 201);
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

    // Decision 1A: webhookIssue is HMAC-signed, no unsigned fallback.
    // Contract: header X-Webhook-Signature (or X-Signature) must be
    // hex HMAC-SHA256 of the raw request body, keyed by the product
    // webhookSecret. Missing/empty secret or bad signature => 401.
    // Callers must obtain the product webhookSecret from the product
    // record; it is not returned in product serialization (see F3).
    public function webhookIssue(string $slug, Request $request): JsonResponse
    {
        $product = $this->productService->getProductBySlug($slug);
        if (!$product) {
            throw NotFoundException::notFound('Product not found.');
        }
        $this->requireWebhookSignature($product, $request);
        [$txId, $email] = $this->validateWebhookInputs($request);
        $deal = $this->resolveWebhookDeal($product, $request);
        $serialValue = $this->mintWebhookSerial($product, $deal, $txId, $email);
        $payload = $this->buildWebhookPayload($product, $serialValue, strtolower(trim($email)), (string) $deal->dealCode);
        return response()->json(['IsSuccess' => true, 'Message' => 'License issued successfully via decoupled webhook.', 'Data' => $payload]);
    }

    private function mintWebhookSerial($product, $deal, string $txId, string $email): string
    {
        $emailNorm = strtolower(trim($email));
        $serial = strtoupper($product->productSlug . '-' . substr(md5($txId . $emailNorm), 0, 16));
        $expiry = \App\Services\LicenseExpiry::derive((string) $deal->billingInterval);
        $this->productService->recordShardLicense($product, $serial, (string) $deal->dealCode, (int) $deal->allowedMachineSeats, $expiry, $emailNorm, $txId);

        return $serial;
    }

    private function requireWebhookSignature($product, Request $request): void
    {
        $secret = (string) ($product->webhookSecret ?? '');
        $sig = (string) ($request->header('X-Webhook-Signature') ?: $request->header('X-Signature') ?: '');
        if ($secret === '' || $sig === '') {
            throw AuthException::unauthorized('Webhook signature is required.');
        }
        $expected = hash_hmac('sha256', (string) $request->getContent(), $secret);
        if (!hash_equals($expected, $sig)) {
            throw AuthException::unauthorized('Webhook signature is invalid.');
        }
    }

    /** @return array{0:string,1:string} */
    private function validateWebhookInputs(Request $request): array
    {
        $txId = trim((string) $request->input('transactionId', ''));
        $email = trim((string) $request->input('customerEmail', ''));
        if (empty($txId) || empty($email)) {
            throw ValidationException::invalidInput('transactionId and customerEmail are required.');
        }
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw ValidationException::invalidInput('customerEmail must be a valid email address.');
        }
        return [$txId, $email];
    }

    // A license must be linked to a real deal for this product.
    // Previously an unknown dealCode fell back to 'DEFAULT' and minted
    // a license with no deal linkage, so portal and verify could not
    // tell what was actually purchased.
    private function resolveWebhookDeal($product, Request $request)
    {
        $dealCode = trim((string) $request->input('dealCode', ''));
        $deal = $dealCode !== '' ? $product->deals->where('dealCode', strtoupper($dealCode))->first() : null;
        if (!$deal) {
            throw ValidationException::invalidInput('dealCode must match an existing deal for this product.');
        }
        return $deal;
    }

    private function buildWebhookPayload($product, string $serialValue, string $email, string $dealCode): array
    {
        return [
            'SerialValue' => $serialValue,
            'CustomerEmail' => $email,
            'ProductSlug' => $product->productSlug,
            'DealCode' => $dealCode,
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
        ], 200, [], JSON_UNESCAPED_SLASHES);
    }

    private function formatPublicDeals($product): array
    {
        return [
            'productSlug' => $product->productSlug,
            'productName' => $product->productName,
            'category' => $product->category,
            'description' => $product->description,
            'checkIntervalDays' => $product->checkIntervalDays,
            'deals' => $product->deals->where('isActive', true)->values()->map(function ($d) use ($product) {
                $a = $d->toArray();
                $a['checkoutUrl'] = url('/checkout/' . $product->productSlug . '?deal=' . $d->dealCode);
                return $a;
            }),
        ];
    }
}
