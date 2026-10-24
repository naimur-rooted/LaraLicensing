<?php

declare(strict_types=1);

namespace App\Http\Controllers\Reseller;

use App\Exceptions\DomainConflictException;
use App\Exceptions\ValidationException;
use App\Models\Prefix;
use App\Support\ApiEnvelope;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

/**
 * Controller managing Reseller Vanity Prefixes.
 */
final class PrefixController
{
    private const PREFIX_REGEX = '/^[A-Z0-9]{3,12}$/';

    public function index(Request $request): JsonResponse
    {
        $resellerId = $this->resolveResellerId($request);
        $rows = Prefix::query()
            ->where('ResellerId', $resellerId)
            ->where('IsActive', true)
            ->orderBy('PrefixId')
            ->get();
        $projections = $rows->map(fn (Prefix $p): array => $this->project($p))->all();

        return ApiEnvelope::success($projections, $this->requestId($request));
    }

    public function store(Request $request): JsonResponse
    {
        $resellerId = $this->resolveResellerId($request);
        $prefixValue = $this->validateAndExtractPrefix($request);
        $this->assertPrefixIsUnique($prefixValue);
        $prefix = $this->createPrefixRecord($resellerId, $prefixValue);

        return ApiEnvelope::success([$this->project($prefix)], $this->requestId($request), 201);
    }

    private function createPrefixRecord(int $resellerId, string $prefixValue): Prefix
    {
        $prefix = new Prefix();
        $prefix->ResellerId = $resellerId;
        $prefix->PrefixValue = $prefixValue;
        $prefix->IsActive = true;
        $prefix->CreatedAt = Carbon::now();
        $prefix->UpdatedAt = Carbon::now();
        $prefix->save();

        return $prefix;
    }

    private function validateAndExtractPrefix(Request $request): string
    {
        $raw = (string) ($request->input('PrefixValue') ?? $request->input('prefixValue') ?? '');
        $prefixValue = strtoupper(trim($raw));

        if (preg_match(self::PREFIX_REGEX, $prefixValue) !== 1) {
            throw ValidationException::validationFailed(
                'Prefix must be 3 to 12 uppercase alphanumeric characters.',
                [['Field' => 'PrefixValue', 'Rule' => 'Regex', 'Value' => $prefixValue]]
            );
        }

        return $prefixValue;
    }

    private function assertPrefixIsUnique(string $prefixValue): void
    {
        $exists = Prefix::query()->where('PrefixValue', $prefixValue)->exists();
        if ($exists) {
            throw DomainConflictException::conflict(
                'PrefixConflict',
                "Prefix '{$prefixValue}' is already registered.",
                [['Field' => 'PrefixValue', 'Rule' => 'Unique', 'Value' => $prefixValue]]
            );
        }
    }

    private function resolveResellerId(Request $request): int
    {
        $resellerId = $request->attributes->get('ResellerId');
        if (is_numeric($resellerId) && (int) $resellerId > 0) {
            return (int) $resellerId;
        }

        return 1;
    }

    /**
     * @return array<string, mixed>
     */
    private function project(Prefix $prefix): array
    {
        return [
            'PrefixId' => (int) $prefix->PrefixId,
            'ResellerId' => (int) $prefix->ResellerId,
            'PrefixValue' => (string) $prefix->PrefixValue,
            'IsActive' => (bool) $prefix->IsActive,
            'CreatedAt' => $prefix->CreatedAt?->toIso8601String() ?? '',
            'UpdatedAt' => $prefix->UpdatedAt?->toIso8601String() ?? '',
        ];
    }

    private function requestId(Request $request): string
    {
        return (string) ($request->headers->get('X-Request-Id') ?? 'req-' . bin2hex(random_bytes(4)));
    }
}
