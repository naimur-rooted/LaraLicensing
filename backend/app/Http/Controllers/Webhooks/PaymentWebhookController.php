<?php

declare(strict_types=1);

namespace App\Http\Controllers\Webhooks;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Services\PaymentWebhookService;
use App\Support\ApiEnvelope;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Controller receiving and validating decoupled payment webhooks
 * from Stripe, LemonSqueezy, and WooCommerce.
 */
class PaymentWebhookController extends Controller
{
    public function __construct(
        private readonly PaymentWebhookService $webhookService
    ) {}

    public function handleStripe(Request $request): JsonResponse
    {
        return $this->handleGateway('stripe', $request);
    }

    public function handleLemonSqueezy(Request $request): JsonResponse
    {
        return $this->handleGateway('lemonsqueezy', $request);
    }

    public function handleWooCommerce(Request $request): JsonResponse
    {
        return $this->handleGateway('woocommerce', $request);
    }

    private function handleGateway(string $gateway, Request $request): JsonResponse
    {
        $eventData = $this->webhookService->extractEventData($gateway, $request);
        // Ignored events must not mint a license and must not insert a
        // WebhookEvents row, so they are filtered before any product check.
        if (!$this->webhookService->isSupportedEvent($gateway, $eventData['eventName'] ?? '')) {
            return $this->ignoredEvent($gateway, $request, $eventData);
        }
        // Signature is checked before product existence, so an invalid
        // signature always returns 401 (and does not leak whether the
        // product exists). A missing secret also fails closed as 401.
        // Product existence is checked after signature, before any
        // license is minted or any WebhookEvents row is inserted:
        // previously an unknown slug could pass via a fallback secret,
        // insert a row, and return a success serial that existed nowhere.
        $productSlug = (string) ($eventData['productSlug'] ?? '');
        $secret = $this->webhookService->resolveSecret($gateway, $productSlug);
        if ($secret === null || $secret === '') {
            return $this->signatureError($gateway, $request);
        }
        if (!$this->webhookService->isSignatureValid($gateway, $request, $secret)) {
            return $this->signatureError($gateway, $request);
        }
        $product = $productSlug !== '' && $productSlug !== 'default-app'
            ? Product::query()->where('productSlug', $productSlug)->where('isActive', true)->first()
            : null;
        if ($product === null) {
            $requestId = (string) ($request->attributes->get('lara.request_id') ?? uniqid('req_', true));

            return ApiEnvelope::failure(
                'ProductNotFound',
                "Product [{$productSlug}] not found.",
                $requestId,
                404,
                'ProductNotFound',
                ['Gateway' => $gateway]
            );
        }
        if (empty($eventData['customerEmail'])) {
            return $this->payloadError($gateway, $request);
        }

        return $this->processValidWebhook($gateway, $request, $eventData);
    }

    private function processValidWebhook(string $gateway, Request $request, array $eventData): JsonResponse
    {
        $eventId = (string) $eventData['eventId'];
        if ($this->webhookService->hasProcessedEvent($eventId, $gateway)) {
            $existing = (array) $this->webhookService->getProcessedEvent($eventId, $gateway);
            return $this->replayResponse($gateway, $request, $existing);
        }
        $serial = $this->webhookService->mintSerialValue($eventData['productSlug'], $gateway, $eventId);
        $this->webhookService->recordEvent($gateway, $eventData, $serial, $request);

        return $this->successResponse($gateway, $request, $eventData, $serial, false);
    }

    private function replayResponse(string $gateway, Request $request, array $existing): JsonResponse
    {
        $requestId = (string) ($request->attributes->get('lara.request_id') ?? uniqid('req_', true));
        $serialData = [
            'SerialValue' => (string) ($existing['SerialValue'] ?? ''),
            'ProductSlug' => (string) ($existing['ProductSlug'] ?? ''),
            'CustomerEmail' => (string) ($existing['CustomerEmail'] ?? ''),
            'TransactionId' => (string) ($existing['EventId'] ?? ''),
            'IsReplayed' => true,
        ];
        return ApiEnvelope::success([$serialData], $requestId, 200, 'Webhook replayed successfully.', [
            'Gateway' => $gateway,
            'EventId' => (string) ($existing['EventId'] ?? ''),
            'IsReplayed' => true,
        ]);
    }

    private function successResponse(string $gateway, Request $request, array $data, string $serial, bool $isReplay): JsonResponse
    {
        $requestId = (string) ($request->attributes->get('lara.request_id') ?? uniqid('req_', true));
        $serialData = [
            'SerialValue' => $serial,
            'ProductSlug' => $data['productSlug'],
            'CustomerEmail' => $data['customerEmail'],
            'DealCode' => $data['dealCode'],
            'TransactionId' => $data['eventId'],
            'IsReplayed' => $isReplay,
        ];
        return ApiEnvelope::success([$serialData], $requestId, 200, 'License serial issued successfully via webhook.', [
            'Gateway' => $gateway,
            'EventId' => $data['eventId'],
            'IsReplayed' => $isReplay,
        ]);
    }

    private function signatureError(string $gateway, Request $request): JsonResponse
    {
        $requestId = (string) ($request->attributes->get('lara.request_id') ?? uniqid('req_', true));
        return ApiEnvelope::failure(
            'WebhookSignatureInvalid',
            "HMAC signature validation failed for gateway [{$gateway}].",
            $requestId,
            401,
            'WebhookSignatureInvalid',
            ['Gateway' => $gateway]
        );
    }

    private function payloadError(string $gateway, Request $request): JsonResponse
    {
        $requestId = (string) ($request->attributes->get('lara.request_id') ?? uniqid('req_', true));
        return ApiEnvelope::failure(
            'WebhookPayloadInvalid',
            'Missing customer email in webhook payload.',
            $requestId,
            422,
            'WebhookPayloadInvalid',
            ['Gateway' => $gateway]
        );
    }

    private function ignoredEvent(string $gateway, Request $request, array $data): JsonResponse
    {
        $requestId = (string) ($request->attributes->get('lara.request_id') ?? uniqid('req_', true));
        return ApiEnvelope::success([], $requestId, 200, "Event [{$data['eventName']}] ignored.", [
            'Gateway' => $gateway,
            'EventName' => $data['eventName'],
            'IsIgnored' => true,
        ]);
    }
}
