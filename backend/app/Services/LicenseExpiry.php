<?php

declare(strict_types=1);

namespace App\Services;

use Illuminate\Support\Carbon;

/**
 * Derives a licence expiry from a deal billing interval.
 * Decision 3A: daily +1 day, weekly +7 days, monthly +1 month,
 * quarterly +3 months, yearly +1 year, biennial +2 years,
 * lifetime = no expiry (null). Returns ISO-8601 string or null.
 */
class LicenseExpiry
{
    public static function derive(string $interval, ?Carbon $issuedAt = null): ?string
    {
        $base = $issuedAt ?? Carbon::now();
        $expiry = match ($interval) {
            'daily' => $base->copy()->addDay(),
            'weekly' => $base->copy()->addWeek(),
            'monthly' => $base->copy()->addMonth(),
            'quarterly' => $base->copy()->addMonths(3),
            'yearly' => $base->copy()->addYear(),
            'biennial' => $base->copy()->addYears(2),
            'lifetime' => null,
            default => null,
        };

        return $expiry?->toISOString();
    }

    public static function isExpired(?string $expiresAt, ?Carbon $now = null): bool
    {
        if ($expiresAt === null || $expiresAt === '') {
            return false;
        }

        return Carbon::parse($expiresAt)->isPast();
    }
}
