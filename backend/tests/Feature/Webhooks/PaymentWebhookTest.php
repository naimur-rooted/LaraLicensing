<?php

declare(strict_types=1);

namespace Tests\Feature\Webhooks;

use App\Models\Product;
use Illuminate\Support\Facades\DB;
use PDO;
use Tests\TestCase;

/**
 * Feature test suite for Decoupled Payment Webhook Ingestion
 * across Stripe, LemonSqueezy, and WooCommerce.
 *
 * Verifies:
 *  - HMAC webhook signature validation
 *  - Idempotent event deduplication
 *  - Automated license serial key issuance upon checkout.session.completed / order_created
 *  - Root Audit Logging (non-repudiable AuditLogs rows)
 *  - Product SQLite Shard persistence in repodb/
 */
class PaymentWebhookTest extends TestCase
{
    private const SECRET = 'whsec_test_shared_secret_key_123';
    private const PRODUCT_SLUG = 'lead-pilot-pro';

    protected function setUp(): void
    {
        parent::setUp();
        $this->createRootFixtures();
        $this->seedTestProduct();
    }

    public function test_stripe_invalid_signature_returns_401(): void
    {
        $payload = json_encode(['id' => 'evt_test_1', 'type' => 'checkout.session.completed']);
        $response = $this->call(
            'POST',
            '/api/v1/webhooks/stripe',
            [],
            [],
            [],
            [
                'HTTP_STRIPE_SIGNATURE' => 'invalid_signature_hex',
                'CONTENT_TYPE' => 'application/json',
                'HTTP_ACCEPT' => 'application/json',
            ],
            $payload
        );

        $response->assertStatus(401);
        $this->assertSame('WebhookSignatureInvalid', $response->json('Attributes.Error.ErrorCode'));
    }

    public function test_stripe_valid_signature_issues_license_and_records_audit(): void
    {
        $eventId = 'evt_stripe_' . uniqid('', true);
        $payloadArray = [
            'id' => $eventId,
            'type' => 'checkout.session.completed',
            'data' => [
                'object' => [
                    'id' => 'cs_' . uniqid('', true),
                    'customer_details' => ['email' => 'stripe_buyer@example.com'],
                    'metadata' => [
                        'product_slug' => self::PRODUCT_SLUG,
                        'deal_code' => 'PILOT_ANNUAL',
                    ],
                ],
            ],
        ];
        $payload = (string) json_encode($payloadArray);
        $timestamp = (string) time();
        $sig = hash_hmac('sha256', $timestamp . '.' . $payload, self::SECRET);
        $sigHeader = "t={$timestamp},v1={$sig}";

        $response = $this->call(
            'POST',
            '/api/v1/webhooks/stripe',
            [],
            [],
            [],
            [
                'HTTP_STRIPE_SIGNATURE' => $sigHeader,
                'CONTENT_TYPE' => 'application/json',
                'HTTP_ACCEPT' => 'application/json',
            ],
            $payload
        );

        $response->assertStatus(200);
        $this->assertTrue($response->json('Status.IsSuccess'));
        $serial = (string) $response->json('Results.0.SerialValue');
        $this->assertNotEmpty($serial);
        $this->assertStringContainsStringIgnoringCase(self::PRODUCT_SLUG, $serial);
        $this->assertFalse((bool) $response->json('Results.0.IsReplayed'));

        // Verify Root Audit Log
        $audit = DB::connection('root')->table('AuditLogs')
            ->where('Action', 'WebhookIngested')
            ->where('TargetId', $eventId)
            ->first();
        $this->assertNotNull($audit, 'AuditLogs must record WebhookIngested row on root connection.');

        // Verify Product SQLite Shard in repodb/
        $shardPath = base_path('repodb/product_' . self::PRODUCT_SLUG . '.sqlite');
        $this->assertFileExists($shardPath);
        $pdo = new PDO("sqlite:{$shardPath}");
        $stmt = $pdo->prepare('SELECT * FROM Serials WHERE SerialValue = ?');
        $stmt->execute([$serial]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        $this->assertNotEmpty($row, 'Product shard must record the serial in Serials table.');

        // Verify Idempotent Deduplication on Replay
        $replayResponse = $this->call(
            'POST',
            '/api/v1/webhooks/stripe',
            [],
            [],
            [],
            [
                'HTTP_STRIPE_SIGNATURE' => $sigHeader,
                'CONTENT_TYPE' => 'application/json',
                'HTTP_ACCEPT' => 'application/json',
            ],
            $payload
        );

        $replayResponse->assertStatus(200);
        $this->assertTrue($replayResponse->json('Status.IsSuccess'));
        $this->assertSame($serial, $replayResponse->json('Results.0.SerialValue'), 'Replay must return original serial.');
        $this->assertTrue((bool) $replayResponse->json('Results.0.IsReplayed'), 'Replay must indicate IsReplayed === true.');
    }

    public function test_lemonsqueezy_valid_signature_issues_license(): void
    {
        $orderId = 'ord_ls_' . uniqid('', true);
        $payloadArray = [
            'meta' => [
                'event_name' => 'order_created',
                'custom_data' => [
                    'product_slug' => self::PRODUCT_SLUG,
                    'deal_code' => 'LS_LIFETIME',
                ],
            ],
            'data' => [
                'id' => $orderId,
                'attributes' => [
                    'user_email' => 'lemon_buyer@example.com',
                    'order_number' => 88129,
                ],
            ],
        ];
        $payload = (string) json_encode($payloadArray);
        $sig = hash_hmac('sha256', $payload, self::SECRET);

        $response = $this->call(
            'POST',
            '/api/v1/webhooks/lemonsqueezy',
            [],
            [],
            [],
            [
                'HTTP_X_SIGNATURE' => $sig,
                'CONTENT_TYPE' => 'application/json',
                'HTTP_ACCEPT' => 'application/json',
            ],
            $payload
        );

        $response->assertStatus(200);
        $this->assertTrue($response->json('Status.IsSuccess'));
        $serial = (string) $response->json('Results.0.SerialValue');
        $this->assertNotEmpty($serial);
        $this->assertSame('lemon_buyer@example.com', $response->json('Results.0.CustomerEmail'));

        // Replay deduplication test
        $replay = $this->call(
            'POST',
            '/api/v1/webhooks/lemonsqueezy',
            [],
            [],
            [],
            [
                'HTTP_X_SIGNATURE' => $sig,
                'CONTENT_TYPE' => 'application/json',
                'HTTP_ACCEPT' => 'application/json',
            ],
            $payload
        );
        $replay->assertStatus(200);
        $this->assertSame($serial, $replay->json('Results.0.SerialValue'));
        $this->assertTrue((bool) $replay->json('Results.0.IsReplayed'));
    }

    public function test_woocommerce_valid_signature_issues_license(): void
    {
        $orderId = 'wc_ord_' . rand(10000, 99999);
        $payloadArray = [
            'id' => $orderId,
            'status' => 'completed',
            'billing' => [
                'email' => 'woo_buyer@example.com',
            ],
            'meta_data' => [
                ['key' => 'product_slug', 'value' => self::PRODUCT_SLUG],
                ['key' => 'deal_code', 'value' => 'WOO_STARTER'],
            ],
        ];
        $payload = (string) json_encode($payloadArray);
        $sig = base64_encode(hash_hmac('sha256', $payload, self::SECRET, true));

        $response = $this->call(
            'POST',
            '/api/v1/webhooks/woocommerce',
            [],
            [],
            [],
            [
                'HTTP_X_WC_WEBHOOK_SIGNATURE' => $sig,
                'HTTP_X_WC_WEBHOOK_TOPIC' => 'order.created',
                'CONTENT_TYPE' => 'application/json',
                'HTTP_ACCEPT' => 'application/json',
            ],
            $payload
        );

        $response->assertStatus(200);
        $this->assertTrue($response->json('Status.IsSuccess'));
        $serial = (string) $response->json('Results.0.SerialValue');
        $this->assertNotEmpty($serial);
        $this->assertSame('woo_buyer@example.com', $response->json('Results.0.CustomerEmail'));
    }

    public function test_unsupported_event_is_safely_ignored(): void
    {
        $payloadArray = [
            'id' => 'evt_refund_1',
            'type' => 'charge.refunded',
            'data' => ['object' => []],
        ];
        $payload = (string) json_encode($payloadArray);
        $sig = hash_hmac('sha256', $payload, self::SECRET);

        $response = $this->call(
            'POST',
            '/api/v1/webhooks/stripe',
            [],
            [],
            [],
            [
                'HTTP_STRIPE_SIGNATURE' => $sig,
                'CONTENT_TYPE' => 'application/json',
                'HTTP_ACCEPT' => 'application/json',
            ],
            $payload
        );

        $response->assertStatus(200);
        $this->assertTrue((bool) $response->json('Attributes.IsIgnored'));
        $this->assertSame([], $response->json('Results'));
    }

    public function test_missing_customer_email_returns_422(): void
    {
        $payloadArray = [
            'id' => 'evt_no_email_1',
            'type' => 'checkout.session.completed',
            'data' => [
                'object' => [
                    'id' => 'cs_no_email',
                    'customer_details' => [],
                    'metadata' => ['product_slug' => self::PRODUCT_SLUG],
                ],
            ],
        ];
        $payload = (string) json_encode($payloadArray);
        $sig = hash_hmac('sha256', $payload, self::SECRET);

        $response = $this->call(
            'POST',
            '/api/v1/webhooks/stripe',
            [],
            [],
            [],
            [
                'HTTP_STRIPE_SIGNATURE' => $sig,
                'CONTENT_TYPE' => 'application/json',
                'HTTP_ACCEPT' => 'application/json',
            ],
            $payload
        );

        $response->assertStatus(422);
        $this->assertSame('WebhookPayloadInvalid', $response->json('Attributes.Error.ErrorCode'));
    }

    private function createRootFixtures(): void
    {
        $root = DB::connection('root');
        $root->statement('CREATE TABLE IF NOT EXISTS "AuditLogs" (
            "AuditLogId" INTEGER PRIMARY KEY AUTOINCREMENT,
            "ActorType" TEXT NOT NULL,
            "ActorId" INTEGER NULL,
            "Action" TEXT NOT NULL,
            "TargetType" TEXT NOT NULL,
            "TargetId" TEXT NULL,
            "RequestId" TEXT NOT NULL,
            "PayloadJson" TEXT NULL,
            "CreatedAt" TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
        )');
        $root->statement('CREATE TABLE IF NOT EXISTS "Products" (
            "ProductId" INTEGER PRIMARY KEY AUTOINCREMENT,
            "userId" INTEGER NOT NULL,
            "productSlug" TEXT NOT NULL UNIQUE,
            "productName" TEXT NOT NULL,
            "category" TEXT NOT NULL,
            "description" TEXT NULL,
            "ed25519PublicKey" TEXT NULL,
            "ed25519PrivateKey" TEXT NULL,
            "webhookSecret" TEXT NULL,
            "webhookUrl" TEXT NULL,
            "checkIntervalDays" INTEGER NOT NULL DEFAULT 7,
            "isActive" INTEGER NOT NULL DEFAULT 1,
            "created_at" TEXT NULL,
            "updated_at" TEXT NULL
        )');
        $root->statement('CREATE TABLE IF NOT EXISTS "ProductDeals" (
            "ProductDealId" INTEGER PRIMARY KEY AUTOINCREMENT,
            "productId" INTEGER NOT NULL,
            "dealName" TEXT NOT NULL,
            "dealCode" TEXT NOT NULL,
            "billingInterval" TEXT NOT NULL DEFAULT "monthly",
            "priceCents" INTEGER NOT NULL DEFAULT 0,
            "allowedMachineSeats" INTEGER NOT NULL DEFAULT 1,
            "allowedDomains" INTEGER NOT NULL DEFAULT 1,
            "isDecoyTarget" INTEGER NOT NULL DEFAULT 0,
            "features" TEXT NULL,
            "isActive" INTEGER NOT NULL DEFAULT 1,
            "created_at" TEXT NULL,
            "updated_at" TEXT NULL
        )');
    }

    private function seedTestProduct(): void
    {
        /** @var \App\Services\ProductService $productService */
        $productService = app(\App\Services\ProductService::class);
        Product::query()->where('productSlug', self::PRODUCT_SLUG)->delete();
        $productService->createProduct(1, [
            'productName' => 'Lead Pilot Pro',
            'productSlug' => self::PRODUCT_SLUG,
            'category' => 'DesktopApp',
            'checkIntervalDays' => 7,
        ]);
        Product::query()->where('productSlug', self::PRODUCT_SLUG)->update(['webhookSecret' => self::SECRET]);
    }
}
