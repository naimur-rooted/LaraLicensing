<?php

declare(strict_types=1);

namespace App\Http\Controllers\Reseller;

use App\Exceptions\DomainConflictException;
use App\Exceptions\NotFoundException;
use App\Exceptions\ValidationException;
use App\Models\License;
use App\Services\BindingService;
use App\Support\ApiEnvelope;
use App\Support\AuditWriter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Controller for downstream machine binding inspection and troubleshooting.
 */
final class MachineBindingController
{
    private const SHARD_CONNECTION = 'shard';
    private const CONN_ROOT = 'root';
    private const LICENSE_KEY_REGEX = '/^[A-Z0-9-]{4,80}$/';
    private const RELEASE_REASON_RESELLER = 'ResellerInitiated';
    private const LOG_RELEASE = 'reseller.binding.release';
    private const LOG_CLEAR_COOLDOWN = 'reseller.binding.clear_cooldown';

    public function index(Request $request, string $licenseKey): JsonResponse
    {
        $license = $this->requireOwnedLicense($request, $licenseKey);
        $conn = $this->resolveConnection();
        $rows = DB::connection($conn)
            ->table('MachineBindings')
            ->where('LicenseId', (int) $license->LicenseId)
            ->orderByDesc('MachineBindingId')
            ->get();
        $results = $rows->map(fn ($r): array => $this->projectMachine($r))->all();

        return ApiEnvelope::success($results, $this->requestId($request));
    }

    public function release(Request $request, string $licenseKey, string $bindingId): JsonResponse
    {
        $license = $this->requireOwnedLicense($request, $licenseKey);
        $id = (int) $bindingId;
        $conn = $this->resolveConnection();
        $result = DB::connection($conn)->transaction(
            fn (): array => $this->applyRelease($conn, (int) $license->LicenseId, $id, $this->requestId($request))
        );

        AuditWriter::write($request, 'MachineUnbound', 'MachineBindings', $id, [
            'LicenseId' => (int) $license->LicenseId,
            'LicenseKey' => (string) $license->LicenseKey,
            'ReleaseReason' => self::RELEASE_REASON_RESELLER,
        ]);

        return ApiEnvelope::success([$result], $this->requestId($request));
    }

    public function clearCooldown(Request $request, string $licenseKey, string $bindingId): JsonResponse
    {
        $license = $this->requireOwnedLicense($request, $licenseKey);
        $id = (int) $bindingId;
        $conn = $this->resolveConnection();
        $result = DB::connection($conn)->transaction(
            fn (): array => $this->applyClearCooldown($conn, (int) $license->LicenseId, $id, $this->requestId($request))
        );

        AuditWriter::write($request, 'MachineCooldownCleared', 'MachineBindings', $id, [
            'LicenseId' => (int) $license->LicenseId,
            'LicenseKey' => (string) $license->LicenseKey,
        ]);

        return ApiEnvelope::success([$result], $this->requestId($request));
    }

    private function applyRelease(string $conn, int $licenseId, int $bindingId, string $requestId): array
    {
        $now = Carbon::now();
        $cooldownUntil = $now->copy()->addMinutes(BindingService::rebindCooldownMinutes());
        $row = $this->lookupBindingForUpdate($conn, $licenseId, $bindingId);

        if ($row->ReleasedAt !== null) {
            throw DomainConflictException::conflict('LicenseConflict', 'Machine binding is already released.');
        }

        DB::connection($conn)->table('MachineBindings')
            ->where('MachineBindingId', $bindingId)
            ->update([
                'ReleasedAt' => $now,
                'RebindCooldownUntil' => $cooldownUntil,
                'UpdatedAt' => $now,
            ]);

        Log::info(self::LOG_RELEASE, ['requestId' => $requestId, 'licenseId' => $licenseId, 'machineBindingId' => $bindingId]);

        return $this->projectMachine((object) array_merge((array) $row, [
            'ReleasedAt' => (string) $now,
            'RebindCooldownUntil' => (string) $cooldownUntil,
            'UpdatedAt' => (string) $now,
        ]));
    }

    private function applyClearCooldown(string $conn, int $licenseId, int $bindingId, string $requestId): array
    {
        $now = Carbon::now();
        $row = $this->lookupBindingForUpdate($conn, $licenseId, $bindingId);

        DB::connection($conn)->table('MachineBindings')
            ->where('MachineBindingId', $bindingId)
            ->update([
                'RebindCooldownUntil' => null,
                'UpdatedAt' => $now,
            ]);

        Log::info(self::LOG_CLEAR_COOLDOWN, ['requestId' => $requestId, 'licenseId' => $licenseId, 'machineBindingId' => $bindingId]);

        return $this->projectMachine((object) array_merge((array) $row, [
            'RebindCooldownUntil' => null,
            'UpdatedAt' => (string) $now,
        ]));
    }

    private function lookupBindingForUpdate(string $conn, int $licenseId, int $bindingId): object
    {
        $row = DB::connection($conn)->table('MachineBindings')
            ->where('MachineBindingId', $bindingId)
            ->where('LicenseId', $licenseId)
            ->first();

        if ($row === null) {
            throw NotFoundException::notFound('BindingNotFound', 'Machine binding not found.');
        }

        return $row;
    }

    private function requireOwnedLicense(Request $request, string $licenseKey): License
    {
        if (preg_match(self::LICENSE_KEY_REGEX, $licenseKey) !== 1) {
            throw ValidationException::validationFailed('Invalid LicenseKey shape.');
        }

        $resellerId = (int) ($request->attributes->get('ResellerId') ?? 1);
        $license = License::query()->where('LicenseKey', $licenseKey)->first();

        if ($license === null) {
            throw NotFoundException::notFound('LicenseNotFound', 'License not found.');
        }

        if ($resellerId > 0 && (int) $license->ResellerId !== $resellerId) {
            throw NotFoundException::notFound('LicenseNotFound', 'License not owned by reseller.');
        }

        return $license;
    }

    private function resolveConnection(): string
    {
        try {
            DB::connection(self::SHARD_CONNECTION)->getPdo();
            return self::SHARD_CONNECTION;
        } catch (Throwable) {
            return self::CONN_ROOT;
        }
    }

    private function projectMachine(object $row): array
    {
        $cooldown = isset($row->RebindCooldownUntil) && $row->RebindCooldownUntil !== null
            ? Carbon::parse($row->RebindCooldownUntil)
            : null;
        $isCooldownActive = $cooldown !== null && $cooldown->isFuture();

        return [
            'MachineBindingId' => (int) $row->MachineBindingId,
            'LicenseId' => (int) $row->LicenseId,
            'FingerprintHash' => (string) $row->FingerprintHash,
            'IP' => (string) ($row->IpAddress ?? '127.0.0.1'),
            'Hostname' => (string) ($row->Hostname ?? 'Workstation'),
            'FirstSeenAt' => (string) ($row->FirstSeenAt ?? ''),
            'LastSeenAt' => (string) ($row->LastSeenAt ?? ''),
            'ReleasedAt' => isset($row->ReleasedAt) && $row->ReleasedAt !== null ? (string) $row->ReleasedAt : null,
            'RebindCooldownUntil' => isset($row->RebindCooldownUntil) && $row->RebindCooldownUntil !== null ? (string) $row->RebindCooldownUntil : null,
            'IsActive' => !isset($row->ReleasedAt) || $row->ReleasedAt === null,
            'IsCooldownActive' => $isCooldownActive,
        ];
    }

    private function requestId(Request $request): string
    {
        return (string) ($request->headers->get('X-Request-Id') ?? 'req-' . bin2hex(random_bytes(4)));
    }
}
