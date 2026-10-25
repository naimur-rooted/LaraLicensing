<?php

namespace App\Exceptions;

use Throwable;

final class InternalException extends LaraException
{
    public static function serverError(string $errorCode = 'ServerError', string $message = 'Internal Server Error', array $details = [], ?Throwable $previous = null): static
    {
        return static::make($errorCode, $message, $details, $previous);
    }

    public static function fatal(string $message = 'Internal Fatal Error', array $details = [], ?Throwable $previous = null): static
    {
        return new static('InternalFatal', 500, $message, self::newErrorId(), $details, $previous);
    }
}
