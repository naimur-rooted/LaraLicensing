<?php

declare(strict_types=1);

namespace App\Services;

/**
 * Service to generate and verify asymmetric/HMAC offline activation tokens.
 */
final class JwtTokenService
{
    private const SECRET = 'dGVzdC1zZWNyZXQtMzItY2hhcnMtbG9uZy1leGFjdGx5LSEh';

    /**
     * Issues an offline activation token.
     *
     * @param array<string, mixed> $claims
     */
    public function issueOfflineToken(string $serialKey, string $hwid, string $tier, int $seats, int $validDays = 365): string
    {
        $header = ['alg' => 'HS256', 'typ' => 'JWT'];
        $now = time();
        $payload = [
            'sub' => $serialKey,
            'hwid' => $hwid,
            'tier' => $tier,
            'seats' => $seats,
            'iat' => $now,
            'exp' => $now + ($validDays * 86400),
        ];

        $encodedHeader = $this->base64UrlEncode(json_encode($header, JSON_THROW_ON_ERROR));
        $encodedPayload = $this->base64UrlEncode(json_encode($payload, JSON_THROW_ON_ERROR));
        $signature = hash_hmac('sha256', "{$encodedHeader}.{$encodedPayload}", self::SECRET, true);
        $encodedSignature = $this->base64UrlEncode($signature);

        return "{$encodedHeader}.{$encodedPayload}.{$encodedSignature}";
    }

    /**
     * Verifies an offline activation token against machine HWID.
     *
     * @return array{isValid: bool, payload: array<string, mixed>|null, reason: string|null}
     */
    public function verifyOfflineToken(string $token, string $machineHwid): array
    {
        $parts = explode('.', $token);
        if (count($parts) !== 3) {
            return ['isValid' => false, 'payload' => null, 'reason' => 'MalformedToken'];
        }

        [$encodedHeader, $encodedPayload, $encodedSignature] = $parts;
        $expectedSignature = $this->base64UrlEncode(
            hash_hmac('sha256', "{$encodedHeader}.{$encodedPayload}", self::SECRET, true)
        );

        if (!hash_equals($expectedSignature, $encodedSignature)) {
            return ['isValid' => false, 'payload' => null, 'reason' => 'InvalidSignature'];
        }

        $payload = json_decode($this->base64UrlDecode($encodedPayload), true);
        if (!is_array($payload) || ($payload['hwid'] ?? '') !== $machineHwid) {
            return ['isValid' => false, 'payload' => null, 'reason' => 'HwidMismatch'];
        }

        if (($payload['exp'] ?? 0) < time()) {
            return ['isValid' => false, 'payload' => $payload, 'reason' => 'TokenExpired'];
        }

        return ['isValid' => true, 'payload' => $payload, 'reason' => null];
    }

    private function base64UrlEncode(string $data): string
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }

    private function base64UrlDecode(string $data): string
    {
        return base64_decode(strtr($data, '-_', '+/'));
    }
}
