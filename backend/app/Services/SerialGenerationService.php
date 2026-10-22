<?php

declare(strict_types=1);

namespace App\Services;

use App\Exceptions\DomainConflictException;
use App\Models\License;
use App\Models\Serial;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Service for deterministic, collision-resilient branded serial generation.
 */
class SerialGenerationService
{
    private const MAX_COLLISION_RETRIES = 5;
    private const DEFAULT_VERSION = 'V1';
    private const CHARACTERS = '23456789ABCDEFGHJKLMNPQRSTUVWXYZ';

    private const CATEGORY_MAP = [
        1 => 'D',
        2 => 'W',
        3 => 'M',
        4 => 'Y',
        5 => 'L',
        6 => 'X',
        7 => 'K',
    ];

    private const CATEGORY_NAME_MAP = [
        'DAILY' => 'D',
        'WEEKLY' => 'W',
        'MONTHLY' => 'M',
        'YEARLY' => 'Y',
        'ANNUAL' => 'Y',
        'LIFETIME' => 'L',
        'LTD' => 'L',
        'DEVELOPER' => 'X',
        'KEY' => 'K',
        'STANDARD' => 'K',
    ];

    /**
     * Generate a unique branded serial key.
     */
    public function generate(string $prefix, int|string $category, string $version = self::DEFAULT_VERSION): string
    {
        $cleanPrefix = strtoupper(trim($prefix));
        $categoryCode = $this->resolveCategoryCode($category);
        $cleanVersion = strtoupper(trim($version !== '' ? $version : self::DEFAULT_VERSION));

        for ($attempt = 1; $attempt <= self::MAX_COLLISION_RETRIES; $attempt++) {
            $randomPart = $this->generateEntropyGroups();
            $candidate = sprintf('%s-%s-%s-%s', $cleanPrefix, $categoryCode, $cleanVersion, $randomPart);

            if ($this->isSerialUnique($candidate)) {
                return $candidate;
            }
        }

        throw DomainConflictException::conflict(
            'SerialCollision',
            'Failed to generate unique serial after ' . self::MAX_COLLISION_RETRIES . ' attempts.'
        );
    }

    private function resolveCategoryCode(int|string $category): string
    {
        if (is_int($category) && isset(self::CATEGORY_MAP[$category])) {
            return self::CATEGORY_MAP[$category];
        }

        $upper = strtoupper(trim((string) $category));
        if (isset(self::CATEGORY_NAME_MAP[$upper])) {
            return self::CATEGORY_NAME_MAP[$upper];
        }

        if (strlen($upper) === 1 && in_array($upper, self::CATEGORY_MAP, true)) {
            return $upper;
        }

        return 'M';
    }

    private function generateEntropyGroups(): string
    {
        $group1 = $this->randomAlphanumericBlock(4);
        $group2 = $this->randomAlphanumericBlock(4);
        $group3 = $this->randomAlphanumericBlock(4);

        return sprintf('%s-%s-%s', $group1, $group2, $group3);
    }

    private function randomAlphanumericBlock(int $length): string
    {
        $result = '';
        $maxIndex = strlen(self::CHARACTERS) - 1;
        $bytes = random_bytes($length);

        for ($i = 0; $i < $length; $i++) {
            $index = ord($bytes[$i]) % ($maxIndex + 1);
            $result .= self::CHARACTERS[$index];
        }

        return $result;
    }

    private function isSerialUnique(string $serialValue): bool
    {
        try {
            $inLicenses = License::query()->where('LicenseKey', $serialValue)->exists();
            if ($inLicenses) {
                return false;
            }

            $inSerials = Serial::query()->where('SerialValue', $serialValue)->exists();
            if ($inSerials) {
                return false;
            }

            $inRoot = DB::connection('root')->table('Licenses')->where('LicenseKey', $serialValue)->exists();
            if ($inRoot) {
                return false;
            }
        } catch (Throwable) {
            // If connection not available, fallback to single check
        }

        return true;
    }
}
