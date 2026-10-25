<?php

declare(strict_types=1);

namespace App\Http\Controllers\Public;

use App\Support\ApiEnvelope;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * v0.300.0. Unauthenticated readiness probe: GET /Api/Public/Health.
 */
final class HealthController
{
    private const ROOT_CONNECTION = 'root';

    public function __invoke(Request $request): JsonResponse
    {
        $requestId = (string) ($request->attributes->get('lara.request_id') ?: $request->headers->get('X-Request-Id', ''));
        $rootOk = $this->pingRoot();
        $payload = [
            'App' => (string) config('app.name', 'Licensing Portal'),
            'Version' => (string) config('app.version', 'dev'),
            'RootDb' => $rootOk ? 'ok' : 'down',
            'Time' => now()->toIso8601String(),
        ];
        if ($rootOk) {
            Log::info('public.health.ok', ['RequestId' => $requestId]);

            return ApiEnvelope::success([$payload], $requestId);
        }

        return $this->buildFailureResponse($requestId, $payload);
    }

    private function buildFailureResponse(string $requestId, array $payload): JsonResponse
    {
        Log::error('public.health.root_down', ['RequestId' => $requestId]);

        return ApiEnvelope::failure(
            errorCode: 'ServiceUnavailable',
            errorMessage: 'Root database is unreachable.',
            requestId: $requestId,
            httpCode: 503,
            message: 'Service Unavailable',
            extraAttributes: ['Category' => 'Internal', 'Payload' => $payload],
        );
    }

    private function pingRoot(): bool
    {
        try {
            DB::connection(self::ROOT_CONNECTION)->selectOne('select 1 as ok');

            return true;
        } catch (Throwable $e) {
            Log::error('public.health.root_ping_failed', ['Exception' => $e::class, 'Message' => $e->getMessage()]);

            return false;
        }
    }
}
