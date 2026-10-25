<?php

namespace App\Exceptions;

use Throwable;

final class RateLimitException extends LaraException
{
    public static function rateLimited(
        int|string $retryAfterOrMsg = 60,
        string|array $message = 'Too Many Requests',
        array $details = [],
        ?Throwable $previous = null,
        array $headers = [],
    ): static {
        $details = is_array($message) ? $message : $details;
        $msg = is_string($retryAfterOrMsg) ? $retryAfterOrMsg : (is_string($message) ? $message : 'Too Many Requests');
        $retry = is_int($retryAfterOrMsg) ? $retryAfterOrMsg : 60;
        if (isset($headers['Retry-After'])) {
            $retry = (int) $headers['Retry-After'];
        }
        $headers['Retry-After'] = (string) $retry;

        return static::make('RateLimited', $msg, $details, $previous, $headers);
    }
}
