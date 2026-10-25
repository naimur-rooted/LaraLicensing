<?php

declare(strict_types=1);

namespace App\Services;

/**
 * Service to generate and verify asymmetric Ed25519 and HMAC offline activation tokens.
 */
class JwtTokenService
{
    private const SECRET = 'dGVzdC1zZWNyZXQtMzItY2hhcnMtbG9uZy1leGFjdGx5LSEh';

    /** Generate valid 32-byte public and 64-byte private Ed25519 keypair. */
    public function generateEd25519KeyPair(): array
    {
        $keypair = sodium_crypto_sign_keypair();
        $publicKey = sodium_crypto_sign_publickey($keypair);
        $secretKey = sodium_crypto_sign_secretkey($keypair);

        return [
            'public' => base64_encode($publicKey),
            'private' => base64_encode($secretKey),
        ];
    }

    /** Deterministic default Ed25519 keypair when product-specific key is omitted. */
    public function defaultEd25519KeyPair(): array
    {
        $seed = hash('sha256', self::SECRET, true);
        $keypair = sodium_crypto_sign_seed_keypair($seed);

        return [
            'public' => base64_encode(sodium_crypto_sign_publickey($keypair)),
            'private' => base64_encode(sodium_crypto_sign_secretkey($keypair)),
        ];
    }

    /** Issues an offline activation token using Ed25519 or HMAC. */
    public function issueOfflineToken(
        string $serialKey,
        string $hwid,
        string $tier,
        int $seats,
        int $validDays = 365,
        ?string $privateKeyBase64 = null
    ): string {
        $key = $privateKeyBase64 ?: $this->defaultEd25519KeyPair()['private'];

        return $this->issueEd25519Token($serialKey, $hwid, $tier, $seats, $key, $validDays);
    }

    /** Issues an asymmetric Ed25519 signed JWT token. */
    public function issueEd25519Token(
        string $serialKey,
        string $hwid,
        string $tier,
        int $seats,
        string $privateKeyBase64,
        int $validDays = 365,
        string $productVersion = 'v1.0.0',
        array $features = []
    ): string {
        $header = ['alg' => 'EdDSA', 'typ' => 'JWT'];
        $payload = $this->buildClaimsPayload($serialKey, $hwid, $tier, $seats, $validDays, $productVersion, $features);
        $encodedHeader = $this->base64UrlEncode(json_encode($header, JSON_THROW_ON_ERROR));
        $encodedPayload = $this->base64UrlEncode(json_encode($payload, JSON_THROW_ON_ERROR));
        $signingData = "{$encodedHeader}.{$encodedPayload}";
        $secretKey = base64_decode($privateKeyBase64);
        $rawSignature = sodium_crypto_sign_detached($signingData, $secretKey);

        return "{$signingData}." . $this->base64UrlEncode($rawSignature);
    }

    /** Builds structured payload claims for license token. */
    private function buildClaimsPayload(
        string $serialKey,
        string $hwid,
        string $tier,
        int $seats,
        int $validDays,
        string $productVersion,
        array $features
    ): array {
        $now = time();

        return [
            'sub' => $serialKey,
            'hwid' => $hwid,
            'tier' => $tier,
            'seats' => $seats,
            'productVersion' => $productVersion,
            'features' => $features,
            'iat' => $now,
            'exp' => $now + ($validDays * 86400),
        ];
    }

    /** Verifies an offline activation token against machine HWID with Ed25519 or HMAC fallback. */
    public function verifyOfflineToken(string $token, string $machineHwid, ?string $publicKeyBase64 = null): array
    {
        $parts = explode('.', $token);
        if (count($parts) !== 3) {
            return ['isValid' => false, 'payload' => null, 'reason' => 'MalformedToken'];
        }
        $header = json_decode($this->base64UrlDecode($parts[0]), true);
        if (($header['alg'] ?? '') === 'HS256') {
            return $this->verifyHmacToken($parts, $machineHwid);
        }
        $pubKey = $publicKeyBase64 ?: $this->defaultEd25519KeyPair()['public'];

        return $this->verifyEd25519Token($token, $machineHwid, $pubKey);
    }

    /** Verifies Ed25519 signed token using public key. */
    public function verifyEd25519Token(string $token, string $machineHwid, string $publicKeyBase64): array
    {
        $parts = explode('.', $token);
        if (count($parts) !== 3) {
            return ['isValid' => false, 'payload' => null, 'reason' => 'MalformedToken'];
        }
        $hasSignature = $this->checkDetachedSignature($parts, $publicKeyBase64);
        if (!$hasSignature) {
            return ['isValid' => false, 'payload' => null, 'reason' => 'InvalidSignature'];
        }

        return $this->validateClaims($parts[1], $machineHwid);
    }

    /** Checks detached Ed25519 signature. */
    private function checkDetachedSignature(array $parts, string $publicKeyBase64): bool
    {
        $rawSignature = $this->base64UrlDecode($parts[2]);
        $publicKey = base64_decode($publicKeyBase64);

        return sodium_crypto_sign_verify_detached($rawSignature, "{$parts[0]}.{$parts[1]}", $publicKey);
    }

    /** Validates payload claims match machine HWID and expiration. */
    private function validateClaims(string $encodedPayload, string $machineHwid): array
    {
        $payload = json_decode($this->base64UrlDecode($encodedPayload), true);
        if (!is_array($payload) || strcasecmp((string) ($payload['hwid'] ?? ''), $machineHwid) !== 0) {
            return ['isValid' => false, 'payload' => null, 'reason' => 'HwidMismatch'];
        }
        if (($payload['exp'] ?? 0) < time()) {
            return ['isValid' => false, 'payload' => $payload, 'reason' => 'TokenExpired'];
        }

        return ['isValid' => true, 'payload' => $payload, 'reason' => null];
    }

    /** Verifies legacy HMAC token. */
    private function verifyHmacToken(array $parts, string $machineHwid): array
    {
        $expected = $this->base64UrlEncode(hash_hmac('sha256', "{$parts[0]}.{$parts[1]}", self::SECRET, true));
        if (!hash_equals($expected, $parts[2])) {
            return ['isValid' => false, 'payload' => null, 'reason' => 'InvalidSignature'];
        }

        return $this->validateClaims($parts[1], $machineHwid);
    }

    /** Base64-url encode string. */
    private function base64UrlEncode(string $data): string
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }

    /** Base64-url decode string. */
    private function base64UrlDecode(string $data): string
    {
        return (string) base64_decode(strtr($data, '-_', '+/'));
    }
}
