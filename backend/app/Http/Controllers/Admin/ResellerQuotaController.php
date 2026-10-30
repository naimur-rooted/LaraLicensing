<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Db\ShardResolver;
use App\Exceptions\AuthException;
use App\Exceptions\NotFoundException;
use App\Exceptions\ValidationException;
use App\Models\Reseller;
use App\Support\ApiEnvelope;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Spec: 02-spec/21-app/11-api-contracts/04-admin-contracts.md §Reseller quotas
 * Handles:
 *  - GET /Resellers/{ResellerId}/Quotas
 *  - GET /Resellers/{ResellerId}/QuotaLedger
 *  - GET /Resellers/{ResellerId}/QuotaRequests
 */
final class ResellerQuotaController
{
    private const CONN_ROOT = 'root';

    public function __construct(private readonly ShardResolver $resolver) {}

    public function index(Request $request, int $resellerId): JsonResponse
    {
        $this->authorizeResellerScope($request, $resellerId);
        $this->requireReseller($resellerId);
        $rows = $this->quotaQuery($request, $resellerId)->get();
        $projected = $rows->map($this->projectQuota(...))->all();

        return ApiEnvelope::success($projected, $this->requestId($request));
    }

    private function requireReseller(int $resellerId): Reseller
    {
        $reseller = Reseller::find($resellerId);
        if ($reseller === null) {
            throw NotFoundException::custom('ResellerNotFound', 'Reseller not found.');
        }

        return $reseller;
    }

    private function quotaQuery(Request $request, int $resellerId): object
    {
        $query = DB::connection(self::CONN_ROOT)->table('ResellerQuotas')
            ->where('ResellerId', $resellerId);
        if ($request->has('LicenseCategoryId')) {
            $query->where('LicenseCategoryId', (int) $request->query('LicenseCategoryId'));
        }
        if ($request->has('LicenseTierId')) {
            $query->where('LicenseTierId', (int) $request->query('LicenseTierId'));
        }

        return $query;
    }

    private function projectQuota(object $q): array
    {
        $granted = (int) $q->LicensesGranted;
        $consumed = (int) $q->LicensesConsumed;
        $periodStart = $q->PeriodStart ? Carbon::parse($q->PeriodStart)->toIso8601ZuluString() : Carbon::now()->toIso8601ZuluString();
        $periodEnd = $q->PeriodEnd ? Carbon::parse($q->PeriodEnd)->toIso8601ZuluString() : null;

        return [
            'ResellerId' => (int) $q->ResellerId,
            'LicenseCategoryId' => (int) $q->LicenseCategoryId,
            'LicenseTierId' => (int) $q->LicenseTierId,
            'LicensesGranted' => $granted,
            'LicensesConsumed' => $consumed,
            'LicensesRemaining' => max(0, $granted - $consumed),
            'PeriodStart' => $periodStart,
            'PeriodEnd' => $periodEnd,
        ];
    }

    public function ledger(Request $request, int $resellerId): JsonResponse
    {
        $this->authorizeResellerScope($request, $resellerId);
        $this->requireReseller($resellerId);
        $rows = $this->ledgerQuery($request, $resellerId)->get();
        $projected = $rows->map($this->projectLedger(...))->all();

        return ApiEnvelope::success($projected, $this->requestId($request));
    }

    private function ledgerQuery(Request $request, int $resellerId): object
    {
        $query = DB::connection(self::CONN_ROOT)->table('ResellerQuotaLedger')
            ->where('ResellerId', $resellerId);
        if ($request->has('LicenseCategoryId')) {
            $query->where('LicenseCategoryId', (int) $request->query('LicenseCategoryId'));
        }
        if ($request->has('LicenseTierId')) {
            $query->where('LicenseTierId', (int) $request->query('LicenseTierId'));
        }
        if ($request->has('LedgerAction')) {
            $query->where('LedgerAction', (string) $request->query('LedgerAction'));
        }

        return $query->orderBy('Id', 'desc')->limit($this->ledgerPageSize($request));
    }

    private function ledgerPageSize(Request $request): int
    {
        return min(100, max(1, (int) $request->query('PageSize', 100)));
    }

    private function projectLedger(object $l): array
    {
        $createdAt = $l->CreatedAt ? Carbon::parse($l->CreatedAt)->toIso8601ZuluString() : Carbon::now()->toIso8601ZuluString();

        return [
            'LedgerId' => (int) $l->Id,
            'ResellerId' => (int) $l->ResellerId,
            'LicenseCategoryId' => (int) ($l->LicenseCategoryId ?? 3),
            'LicenseTierId' => (int) ($l->LicenseTierId ?? 1),
            'LedgerAction' => (string) ($l->LedgerAction ?? ($l->Action ?? 'QuotaAdjusted')),
            'Delta' => (int) $l->Delta,
            'LicenseId' => $l->LicenseId ? (int) $l->LicenseId : null,
            'QuotaRequestId' => null,
            'RequestId' => (string) ($l->ReferenceId ?? 'seed-ref'),
            'ActorUserId' => 1,
            'CreatedAt' => $createdAt,
        ];
    }

    public function quotaRequests(Request $request, int $resellerId): JsonResponse
    {
        $this->authorizeResellerScope($request, $resellerId);
        $reseller = $this->requireReseller($resellerId);
        $this->resolver->bind($reseller->ResellerSlug);
        $rows = $this->quotaRequestQuery($request, $resellerId)->get();
        $projected = $rows->map($this->projectQuotaRequest(...))->all();

        return ApiEnvelope::success($projected, $this->requestId($request));
    }

    private function quotaRequestQuery(Request $request, int $resellerId): object
    {
        $query = DB::connection('shard')->table('QuotaRequests')
            ->where('ResellerId', $resellerId);
        if ($request->has('Status')) {
            $query->where('Status', (string) $request->query('Status'));
        }

        return $query->orderBy('QuotaRequestId', 'desc');
    }

    private function projectQuotaRequest(object $r): array
    {
        return [
            'QuotaRequestId' => (int) $r->QuotaRequestId,
            'ResellerId' => (int) $r->ResellerId,
            'LicenseCategoryId' => (int) $r->LicenseCategoryId,
            'LicenseTierId' => (int) $r->LicenseTierId,
            'RequestedDelta' => (int) $r->RequestedDelta,
            'ApprovedDelta' => $r->ApprovedDelta !== null ? (int) $r->ApprovedDelta : null,
            'Status' => (string) $r->Status,
            'SubmittedByUserId' => (int) $r->SubmittedByUserId,
            'SubmittedAt' => $r->SubmittedAt ? Carbon::parse($r->SubmittedAt)->toIso8601ZuluString() : Carbon::now()->toIso8601ZuluString(),
            'DecidedByUserId' => $r->DecidedByUserId !== null ? (int) $r->DecidedByUserId : null,
            'DecidedAt' => $r->DecidedAt ? Carbon::parse($r->DecidedAt)->toIso8601ZuluString() : null,
            'DenialReason' => $r->DenialReason ? (string) $r->DenialReason : null,
            'Justification' => $r->Justification ? (string) $r->Justification : null,
        ];
    }

    private function authorizeResellerScope(Request $request, int $targetResellerId): void
    {
        $user = $request->user();
        if ($user === null) {
            throw AuthException::custom('AuthUnauthenticated', 'Unauthenticated.');
        }

        $role = method_exists($user, 'getPrimaryRole') ? $user->getPrimaryRole() : 'EndUser';

        if (in_array($role, ['Admin', 'SuperAdmin'], true)) {
            return;
        }

        if ($role === 'Reseller') {
            $callerResellerId = (int) ($user->ResellerId ?? $user->TenantId ?? 0);
            if ($callerResellerId === $targetResellerId) {
                return;
            }
            throw AuthException::custom('AuthzPermissionDenied', 'Caller is not permitted to access this reseller resource.');
        }

        throw AuthException::custom('AuthzRoleDenied', 'Role is not authorized to access reseller quotas.');
    }

    private function requestId(Request $request): string
    {
        $id = $request->attributes->get('RequestId');
        if (is_string($id) && $id !== '') {
            return $id;
        }

        return 'req-' . bin2hex(random_bytes(8));
    }
}
