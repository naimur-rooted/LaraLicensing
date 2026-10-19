<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Exceptions\RateLimitException;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Symfony\Component\HttpFoundation\Response;

/**
 * Leaky-bucket rate limiter for serial lookup endpoints.
 */
final class ThrottleLicenseLookups
{
    private const MAX_ATTEMPTS = 60;
    private const DECAY_SECONDS = 60;

    public function handle(Request $request, Closure $next): Response
    {
        $ip = $request->ip() ?? 'unknown';
        $key = 'throttle:license_lookup:' . $ip;

        if (RateLimiter::tooManyAttempts($key, self::MAX_ATTEMPTS)) {
            $retryAfter = RateLimiter::availableIn($key);
            throw RateLimitException::rateLimited(
                'Too many license lookups. Try again in ' . $retryAfter . ' seconds.',
                [['Field' => 'Serial', 'Rule' => 'RateLimited', 'Value' => (string) $retryAfter]],
                headers: ['Retry-After' => (string) $retryAfter]
            );
        }

        RateLimiter::hit($key, self::DECAY_SECONDS);
        $response = $next($request);
        $response->headers->set('X-RateLimit-Limit', (string) self::MAX_ATTEMPTS);
        $response->headers->set('X-RateLimit-Remaining', (string) RateLimiter::remaining($key, self::MAX_ATTEMPTS));

        return $response;
    }
}
