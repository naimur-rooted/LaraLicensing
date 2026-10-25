<?php

namespace App\Exceptions;

use Throwable;

final class DomainConflictException extends LaraException
{
    public static function conflict(string $msgOrCode = 'DomainConflict', string $message = 'Conflict', array $details = [], ?Throwable $previous = null): static
    {
        $hasCustomCode = $msgOrCode !== 'DomainConflict' && config("lara.error_http_status.{$msgOrCode}") !== null;
        $code = $hasCustomCode ? $msgOrCode : 'DomainConflict';
        $msg = $hasCustomCode ? $message : $msgOrCode;
        $status = $hasCustomCode ? (int) (config("lara.error_http_status.{$code}.status") ?? 409) : 409;

        return new static($code, $status, $msg, self::newErrorId(), $details, $previous);
    }
}
