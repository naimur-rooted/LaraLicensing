<?php

namespace App\Exceptions;

use Throwable;

final class NotFoundException extends LaraException
{
    public static function notFound(string $msgOrCode = 'NotFound', string $message = 'Not Found', array $details = [], ?Throwable $previous = null): static
    {
        $hasCustomCode = $msgOrCode !== 'NotFound' && config("lara.error_http_status.{$msgOrCode}") !== null;
        $code = $hasCustomCode ? $msgOrCode : 'NotFound';
        $msg = $hasCustomCode ? $message : $msgOrCode;

        return new static($code, 404, $msg, self::newErrorId(), $details, $previous);
    }
}
