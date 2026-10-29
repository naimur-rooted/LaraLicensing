<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Product;
use App\Models\ReleaseManifest;

/** Signed release manifests: an unsigned hash list is not security, an Ed25519 signature is. */
final class TamperManifestService
{
    public function __construct(private readonly JwtTokenService $jwtService, private readonly TamperManifestCanonicalizer $canonicalizer = new TamperManifestCanonicalizer())
    {
    }

    /** Sign a release manifest for a product and store it. */
    public function signManifest(Product $product, string $version, array $files, ?string $minVersion): ReleaseManifest
    {
        $product = $this->ensureKeys($product);
        $normalized = $this->canonicalizer->normalizeFiles($files);
        $payload = $this->manifestPayload((string) $product->productSlug, $version, $minVersion, $normalized);
        $hash = hash('sha256', $this->canonicalizer->canonicalJson($payload));
        $signature = base64_encode(sodium_crypto_sign_detached($this->canonicalizer->canonicalJson($payload), base64_decode((string) $product->ed25519PrivateKey)));

        return ReleaseManifest::updateOrCreate(
            ['productSlug' => (string) $product->productSlug, 'releaseVersion' => $version],
            ['minVersion' => $minVersion, 'manifestSha256' => $hash, 'signature' => $signature, 'kid' => $this->kidForPublicKey((string) $product->ed25519PublicKey), 'filesJson' => json_encode($normalized, JSON_THROW_ON_ERROR), 'isActive' => true]
        );
    }

    /** Verify a signed manifest against the product public key. */
    public function verifyManifest(Product $product, array $manifest): array
    {
        if (($manifest['alg'] ?? '') !== 'ed25519') {
            return ['isValid' => false, 'reason' => 'AlgNotPinned'];
        }
        if (($manifest['kid'] ?? '') !== $this->kidForPublicKey((string) $product->ed25519PublicKey)) {
            return ['isValid' => false, 'reason' => 'KidMismatch'];
        }
        $files = $this->canonicalizer->normalizeFiles($manifest['files'] ?? []);
        $payload = $this->manifestPayload((string) ($manifest['productSlug'] ?? ''), (string) ($manifest['releaseVersion'] ?? ''), $manifest['minVersion'] ?? null, $files);
        if (hash('sha256', $this->canonicalizer->canonicalJson($payload)) !== (string) ($manifest['manifestSha256'] ?? '')) {
            return ['isValid' => false, 'reason' => 'ManifestHashMismatch'];
        }
        $verified = $this->verifySignature($manifest, $payload, (string) $product->ed25519PublicKey);

        return ['isValid' => $verified, 'reason' => $verified ? null : 'InvalidSignature'];
    }

    /** Compare submitted file hashes against a signed manifest. */
    public function verifySubmittedFiles(array $manifestFiles, array $submittedFiles): array
    {
        $manifest = $this->canonicalizer->normalizeFiles($manifestFiles);
        $submitted = $this->canonicalizer->normalizeFiles($submittedFiles);
        $byPath = [];
        foreach ($submitted as $file) {
            $byPath[$file['path']] = $file;
        }

        return $this->canonicalizer->fileMismatches($manifest, $byPath);
    }

    /** Public manifest array a client can verify offline with the product public key. */
    public function toPublicArray(ReleaseManifest $model): array
    {
        $files = json_decode((string) $model->filesJson, true) ?: [];

        return ['productSlug' => (string) $model->productSlug, 'releaseVersion' => (string) $model->releaseVersion, 'minVersion' => $model->minVersion, 'files' => $files, 'manifestSha256' => (string) $model->manifestSha256, 'signature' => (string) $model->signature, 'kid' => (string) $model->kid, 'alg' => 'ed25519'];
    }

    /** @param array<string, mixed> $data */
    public function canonicalJson(array $data): string
    {
        return $this->canonicalizer->canonicalJson($data);
    }

    private function manifestPayload(string $slug, string $version, ?string $minVersion, array $files): array
    {
        return ['productSlug' => $slug, 'releaseVersion' => $version, 'minVersion' => $minVersion, 'files' => $files];
    }

    private function verifySignature(array $manifest, array $payload, string $publicKey): bool
    {
        $signature = base64_decode((string) ($manifest['signature'] ?? ''), true);
        if ($signature === false || strlen($signature) !== SODIUM_CRYPTO_SIGN_BYTES) {
            return false;
        }
        try {
            return sodium_crypto_sign_verify_detached($signature, $this->canonicalizer->canonicalJson($payload), base64_decode($publicKey));
        } catch (\Throwable) {
            return false;
        }
    }

    private function kidForPublicKey(string $publicKey): string
    {
        return substr(hash('sha256', $publicKey), 0, 16);
    }

    private function ensureKeys(Product $product): Product
    {
        $rawPrivate = base64_decode((string) $product->ed25519PrivateKey, true);
        if ($rawPrivate !== false && strlen($rawPrivate) === 64) {
            return $product;
        }
        $keyPair = $this->jwtService->generateEd25519KeyPair();
        $product->update(['ed25519PublicKey' => $keyPair['public'], 'ed25519PrivateKey' => $keyPair['private']]);

        return $product;
    }
}
