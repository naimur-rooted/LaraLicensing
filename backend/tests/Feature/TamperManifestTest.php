<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Http\Controllers\Developer\TamperManifestController;
use App\Models\Product;
use App\Services\JwtTokenService;
use App\Services\TamperManifestService;
use Tests\TestCase;

/**
 * Signed release manifests, phase 1 tamper detection.
 *
 * An unsigned hash list is not security. A manifest signed with the
 * product Ed25519 key is, and a tampered file must fail closed.
 */
final class TamperManifestTest extends TestCase
{
    private TamperManifestService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new TamperManifestService(new JwtTokenService());
    }

    public function test_tamper_manifest_controller_exists(): void
    {
        $this->assertTrue(class_exists(TamperManifestController::class));
    }

    public function test_signed_manifest_verifies_and_tampered_file_fails(): void
    {
        $product = $this->makeProduct('tamper-' . time());
        $files = [['path' => 'app.exe', 'sha256' => hash('sha256', 'good'), 'bytes' => 4]];
        $model = $this->service->signManifest($product, '1.0.0', $files, '1.0.0');
        $manifest = $this->service->toPublicArray($model);

        $checked = $this->service->verifyManifest($product->fresh(), $manifest);
        $this->assertTrue($checked['isValid']);
        $this->assertSame([], $this->service->verifySubmittedFiles($manifest['files'], $files));

        $tampered = [['path' => 'app.exe', 'sha256' => hash('sha256', 'bad'), 'bytes' => 3]];
        $mismatches = $this->service->verifySubmittedFiles($manifest['files'], $tampered);
        $this->assertNotSame([], $mismatches);
    }

    public function test_tampered_signature_fails_verification(): void
    {
        $product = $this->makeProduct('tamper-sig-' . time());
        $files = [['path' => 'app.exe', 'sha256' => hash('sha256', 'good'), 'bytes' => 4]];
        $model = $this->service->signManifest($product, '1.0.0', $files, null);
        $manifest = $this->service->toPublicArray($model);
        $manifest['signature'] = base64_encode('tampered-signature-not-valid-at-all-0000000000000000');

        $checked = $this->service->verifyManifest($product->fresh(), $manifest);
        $this->assertFalse($checked['isValid']);
    }

    public function test_tamper_verify_endpoint_fails_closed_on_hash_mismatch(): void
    {
        $product = $this->makeProduct('tamper-api-' . time());
        $files = [['path' => 'app.exe', 'sha256' => hash('sha256', 'good'), 'bytes' => 4]];
        $model = $this->service->signManifest($product, '1.0.0', $files, null);
        $manifest = $this->service->toPublicArray($model);

        $good = $this->postJson("/Api/v1/products/{$product->productSlug}/tamper-verify", ['manifest' => $manifest, 'files' => $files]);
        $good->assertStatus(200);
        $this->assertTrue($good->json('Results.isValid'));

        $badFiles = [['path' => 'app.exe', 'sha256' => hash('sha256', 'bad'), 'bytes' => 3]];
        $bad = $this->postJson("/Api/v1/products/{$product->productSlug}/tamper-verify", ['manifest' => $manifest, 'files' => $badFiles]);
        $bad->assertStatus(200);
        $this->assertFalse($bad->json('Results.isValid'));
        $this->assertTrue($bad->json('Results.failClosed'));
        $this->assertNotSame([], $bad->json('Results.mismatches'));
    }

    private function makeProduct(string $slug): Product
    {
        $keys = (new JwtTokenService())->generateEd25519KeyPair();

        return Product::create([
            'userId' => 1,
            'productSlug' => $slug,
            'productName' => 'Tamper Test Product',
            'category' => 'DesktopApp',
            'ed25519PublicKey' => $keys['public'],
            'ed25519PrivateKey' => $keys['private'],
            'checkIntervalDays' => 7,
            'isActive' => true,
        ]);
    }
}
