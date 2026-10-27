<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Product;
use App\Support\AuditWriter;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use PDO;

/**
 * Service managing decoupled payment webhook validation, deduplication,
 * and automated serial issuance across Stripe, LemonSqueezy, and WooCommerce.
 */
class PaymentWebhookService
{
    // No hardcoded fallback secret: if a product has no webhookSecret and
    // no gateway env secret is configured, the caller must fail closed
    // with 401 instead of accepting a signature made with a public default.
    // Previous behaviour returned a literal default here, which meant an
    // attacker who read the repo could forge a valid signature for any
    // unknown product slug.

    public function isSignatureValid(string $gateway, Request $request, string $secret): bool
    {
        return match ($gateway) {
            'stripe' => $this->isStripeSignatureValid($request, $secret),
            'lemonsqueezy' => $this->isLemonSqueezySignatureValid($request, $secret),
            'woocommerce' => $this->isWooCommerceSignatureValid($request, $secret),
            default => false,
        };
    }

    public function isStripeSignatureValid(Request $request, string $secret): bool
    {
        $sigHeader = (string) ($request->header('Stripe-Signature') ?: $request->header('stripe-signature') ?: '');
        $rawBody = (string) $request->getContent();
        if ($sigHeader === '') {
            return false;
        }
        if (str_contains($sigHeader, 't=')) {
            return $this->isStripeTimestampValid($sigHeader, $rawBody, $secret);
        }

        return hash_equals(hash_hmac('sha256', $rawBody, $secret), $sigHeader);
    }

    private function isStripeTimestampValid(string $header, string $body, string $secret): bool
    {
        preg_match('/t=([0-9]+)/', $header, $tMatches);
        preg_match('/v1=([a-f0-9]+)/', $header, $v1Matches);
        $timestamp = $tMatches[1] ?? '';
        $expectedSig = $v1Matches[1] ?? '';
        if ($timestamp === '' || $expectedSig === '') {
            return false;
        }
        // Stripe replay protection: reject timestamps more than 5 minutes
        // old or in the future. Tests use time() at request time, so this
        // does not break valid current webhooks.
        if (abs(time() - (int) $timestamp) > 300) {
            return false;
        }

        return hash_equals(hash_hmac('sha256', $timestamp . '.' . $body, $secret), $expectedSig);
    }

    public function isLemonSqueezySignatureValid(Request $request, string $secret): bool
    {
        $sigHeader = (string) ($request->header('X-Signature') ?: $request->header('x-signature') ?: '');
        $rawBody = (string) $request->getContent();
        if ($sigHeader === '') {
            return false;
        }

        return hash_equals(hash_hmac('sha256', $rawBody, $secret), $sigHeader);
    }

    public function isWooCommerceSignatureValid(Request $request, string $secret): bool
    {
        $sigHeader = (string) ($request->header('X-WC-Webhook-Signature') ?: $request->header('x-wc-webhook-signature') ?: '');
        $rawBody = (string) $request->getContent();
        if ($sigHeader === '') {
            return false;
        }
        $expected = base64_encode(hash_hmac('sha256', $rawBody, $secret, true));

        return hash_equals($expected, $sigHeader);
    }

    public function hasProcessedEvent(string $eventId, string $gateway): bool
    {
        $this->ensureWebhookTableExists();

        return DB::connection('root')->table('WebhookEvents')
            ->where('EventId', $eventId)
            ->where('Gateway', $gateway)
            ->exists();
    }

    public function getProcessedEvent(string $eventId, string $gateway): ?array
    {
        $this->ensureWebhookTableExists();
        $row = DB::connection('root')->table('WebhookEvents')
            ->where('EventId', $eventId)
            ->where('Gateway', $gateway)
            ->first();

        return $row !== null ? (array) $row : null;
    }

    public function extractEventData(string $gateway, Request $request): array
    {
        return match ($gateway) {
            'stripe' => $this->extractStripeData($request),
            'lemonsqueezy' => $this->extractLemonSqueezyData($request),
            'woocommerce' => $this->extractWooCommerceData($request),
            default => [],
        };
    }

    private function extractStripeData(Request $request): array
    {
        $obj = (array) $request->input('data.object', []);
        $meta = (array) ($obj['metadata'] ?? []);
        $email = (string) ($obj['customer_details']['email'] ?? $obj['customer_email'] ?? $obj['email'] ?? '');
        $productSlug = (string) ($meta['product_slug'] ?? $meta['productSlug'] ?? $request->query('product_slug', 'default-app'));
        $dealCode = (string) ($meta['deal_code'] ?? $meta['dealCode'] ?? 'DEFAULT');

        return [
            'eventId' => (string) ($request->input('id') ?: $obj['id'] ?? uniqid('evt_', true)),
            'eventName' => (string) $request->input('type', 'checkout.session.completed'),
            'customerEmail' => $email,
            'productSlug' => $productSlug,
            'dealCode' => $dealCode,
        ];
    }

    private function extractLemonSqueezyData(Request $request): array
    {
        $meta = (array) $request->input('meta', []);
        $custom = (array) ($meta['custom_data'] ?? []);
        $attr = (array) $request->input('data.attributes', []);
        $email = (string) ($attr['user_email'] ?? $attr['customer_email'] ?? '');
        $productSlug = (string) ($custom['product_slug'] ?? $custom['productSlug'] ?? $request->query('product_slug', 'default-app'));

        return [
            'eventId' => (string) ($request->input('data.id') ?: $meta['custom_data']['order_id'] ?? uniqid('ord_', true)),
            'eventName' => (string) ($meta['event_name'] ?? 'order_created'),
            'customerEmail' => $email,
            'productSlug' => $productSlug,
            'dealCode' => (string) ($custom['deal_code'] ?? 'DEFAULT'),
        ];
    }

    private function extractWooCommerceData(Request $request): array
    {
        $email = (string) $request->input('billing.email', '');
        $metaList = (array) $request->input('meta_data', []);
        $metaMap = [];
        foreach ($metaList as $item) {
            if (isset($item['key'], $item['value'])) {
                $metaMap[(string) $item['key']] = (string) $item['value'];
            }
        }
        $productSlug = (string) ($metaMap['product_slug'] ?? $request->query('product_slug', 'default-app'));

        return [
            'eventId' => (string) ($request->input('id') ?: $request->header('X-WC-Webhook-ID') ?: uniqid('wc_', true)),
            'eventName' => (string) ($request->header('X-WC-Webhook-Topic') ?: 'order.created'),
            'customerEmail' => $email,
            'productSlug' => $productSlug,
            'dealCode' => (string) ($metaMap['deal_code'] ?? 'DEFAULT'),
        ];
    }

    public function isSupportedEvent(string $gateway, string $eventName): bool
    {
        return match ($gateway) {
            'stripe' => $eventName === 'checkout.session.completed',
            'lemonsqueezy' => $eventName === 'order_created',
            'woocommerce' => in_array($eventName, ['order.created', 'order_created', 'order.completed'], true),
            default => false,
        };
    }

    public function mintSerialValue(string $productSlug, string $gateway, string $eventId): string
    {
        $prefix = strtoupper(substr($gateway, 0, 3));
        $hash = strtoupper(substr(md5($eventId . microtime()), 0, 16));

        return strtoupper($productSlug) . '-' . $prefix . '-' . $hash;
    }

    public function recordEvent(string $gateway, array $data, string $serial, Request $request): void
    {
        $this->ensureWebhookTableExists();
        $this->insertWebhookEventRow($gateway, $data, $serial, Carbon::now());
        $this->recordToProductShard($data['productSlug'], $serial, $data['customerEmail'], $data['dealCode']);
        AuditWriter::write($request, 'WebhookIngested', 'WebhookEvents', $data['eventId'], [
            'Gateway' => $gateway,
            'ProductSlug' => $data['productSlug'],
            'SerialValue' => $serial,
        ]);
    }

    private function insertWebhookEventRow(string $gateway, array $data, string $serial, Carbon $now): void
    {
        DB::connection('root')->table('WebhookEvents')->insert([
            'EventId' => $data['eventId'],
            'Gateway' => $gateway,
            'EventName' => $data['eventName'],
            'ProductSlug' => $data['productSlug'],
            'CustomerEmail' => $data['customerEmail'],
            'SerialValue' => $serial,
            'PayloadJson' => (string) json_encode($data),
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }

    private function recordToProductShard(string $slug, string $serial, string $email, string $deal): void
    {
        $dirs = [base_path("repodb"), database_path()];
        $now = Carbon::now()->toDateTimeString();
        foreach ($dirs as $dir) {
            $path = "{$dir}/product_{$slug}.sqlite";
            if (file_exists($path)) {
                $pdo = new PDO("sqlite:{$path}");
                $stmt = $pdo->prepare('INSERT OR IGNORE INTO Serials (SerialValue, LicenseKey, ProductSlug, CustomerEmail, IsRevoked, IssuedAt, CreatedAt, UpdatedAt) VALUES (?, ?, ?, ?, 0, ?, ?, ?)');
                $stmt->execute([$serial, $serial, $slug, $email, $now, $now, $now]);
            }
        }
    }

    public function resolveSecret(string $gateway, string $productSlug): ?string
    {
        $product = Product::query()->where('productSlug', $productSlug)->first();
        if ($product !== null && !empty($product->webhookSecret)) {
            return (string) $product->webhookSecret;
        }
        $envKey = strtoupper($gateway) . '_WEBHOOK_SECRET';
        $envSecret = (string) env($envKey, '');

        return $envSecret !== '' ? $envSecret : null;
    }

    private function ensureWebhookTableExists(): void
    {
        if (Schema::connection('root')->hasTable('WebhookEvents')) {
            return;
        }
        DB::connection('root')->statement('CREATE TABLE IF NOT EXISTS "WebhookEvents" (
            "EventId" VARCHAR(128) NOT NULL,
            "Gateway" VARCHAR(32) NOT NULL,
            "EventName" VARCHAR(64) NOT NULL,
            "ProductSlug" VARCHAR(64) NOT NULL,
            "CustomerEmail" VARCHAR(128) NOT NULL,
            "SerialValue" VARCHAR(128) NOT NULL,
            "PayloadJson" TEXT NULL,
            "created_at" DATETIME NOT NULL,
            "updated_at" DATETIME NOT NULL,
            PRIMARY KEY ("EventId", "Gateway")
        )');
    }
}
