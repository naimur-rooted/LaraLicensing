<?php

declare(strict_types=1);

namespace App\Http\Controllers\Reseller;

use App\Exceptions\AuthException;
use App\Exceptions\DomainConflictException;
use App\Exceptions\NotFoundException;
use App\Exceptions\ValidationException;
use App\Http\Requests\Reseller\LicenseIssueRequest;
use App\Http\Requests\Reseller\LicenseRenewRequest;
use App\Models\License;
use App\Models\Serial;
use App\Services\LicenseLedgerService;
use App\Services\QuotaService;
use App\Services\SerialGenerationService;
use App\Support\ApiEnvelope;
use App\Support\AuditWriter;
use App\Support\EntityHasher;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Controller managing Reseller Branded License Minting, Revocation & Lifecycle.
 */
final class LicenseController
{
    private const SHARD_CONNECTION = 'shard';
    private const CONN_ROOT = 'root';
    private const STATUS_ACTIVE = 'Active';
    private const STATUS_REVOKED = 'Revoked';
    private const ISSUER_ACTOR_RESELLER = 'Reseller';
    private const DEFAULT_PRODUCT_VERSION = 'V1';
    private const DEFAULT_CATEGORY_NAME = 'Key';
    private const LIST_LIMIT_DEFAULT = 50;
    private const LIST_LIMIT_MAX = 200;

    public function __construct(
        private readonly QuotaService $quotaService,
        private readonly LicenseLedgerService $ledgerService,
        private readonly SerialGenerationService $serialService,
    ) {
    }

    public function index(Request $request): JsonResponse
    {
        $resellerId = $this->requireResellerId($request);
        $limit = $this->parseLimit($request);
        $rows = License::query()
            ->where('ResellerId', $resellerId)
            ->orderBy('LicenseId', 'desc')
            ->limit($limit)
            ->get();
        $projected = $rows->map(fn (License $l): array => $this->project($l))->all();

        return ApiEnvelope::success($projected, $this->requestId($request));
    }

    public function show(Request $request, string $licenseKey): JsonResponse
    {
        $license = $this->requireOwnedLicense($request, $licenseKey);

        return ApiEnvelope::success([$this->project($license)], $this->requestId($request));
    }

    public function store(LicenseIssueRequest $request): JsonResponse
    {
        return $this->issue($request);
    }

    public function issue(LicenseIssueRequest $request): JsonResponse
    {
        $resellerId = $this->requireResellerId($request);
        $payload = $this->normalizeIssuePayload($request);
        $license = $this->executeMintTransaction($request, $resellerId, $payload);
        $this->auditLicenseIssued($request, $resellerId, $license);

        return ApiEnvelope::success([$this->project($license)], $this->requestId($request), 201);
    }

    private function auditLicenseIssued(Request $request, int $resellerId, License $license): void
    {
        AuditWriter::write($request, 'LicenseIssued', 'Licenses', (int) $license->LicenseId, [
            'LicenseKey' => (string) $license->LicenseKey,
            'ResellerId' => $resellerId,
            'TierName' => (string) $license->TierName,
        ]);
    }

    public function revoke(Request $request, string $licenseKey): JsonResponse
    {
        $resellerId = $this->requireResellerId($request);
        $reason = (string) ($request->input('Reason') ?? $request->input('reason') ?? 'Reseller initiated revocation');
        $license = $this->executeRevokeTransaction($request, $resellerId, $licenseKey, $reason);

        return ApiEnvelope::success([$this->project($license)], $this->requestId($request));
    }

    public function destroy(Request $request, string $licenseKey): JsonResponse
    {
        return $this->revoke($request, $licenseKey);
    }

    public function renew(LicenseRenewRequest $request, string $licenseKey): JsonResponse
    {
        $newExpiresAt = $this->requireFutureExpiresAt($request->expiresAt());
        $license = $this->applyRenew($request, $licenseKey, $newExpiresAt);

        AuditWriter::write($request, 'LicenseRenewed', 'Licenses', (int) $license->LicenseId, [
            'LicenseKey' => (string) $license->LicenseKey,
            'ExpiresAt' => $license->ExpiresAt?->format('Y-m-d\TH:i:s\Z'),
        ]);

        return ApiEnvelope::success([$this->project($license)], $this->requestId($request));
    }

    public function ledger(Request $request, string $licenseKey): JsonResponse
    {
        $license = $this->requireOwnedLicense($request, $licenseKey);
        $rows = $this->ledgerService->listForLicense(
            resellerId: (int) $license->ResellerId,
            licenseId: (int) $license->LicenseId,
            limit: $this->parseLimit($request),
        );

        return ApiEnvelope::success($rows, $this->requestId($request));
    }

    public function quotas(Request $request): JsonResponse
    {
        $resellerId = $this->requireResellerId($request);
        $quotas = DB::connection(self::CONN_ROOT)->table('ResellerQuotas')
            ->where('ResellerId', $resellerId)->get();
        $ledger = DB::connection(self::CONN_ROOT)->table('ResellerQuotaLedger')
            ->where('ResellerId', $resellerId)->orderBy('Id', 'desc')->limit(20)->get();

        return ApiEnvelope::success(['Quotas' => $quotas, 'RecentLedger' => $ledger], $this->requestId($request));
    }

    /**
     * @param array{PrefixValue:string, TierName:string, EnvironmentName:string, LicenseCategory:string, ExpiresAt:?string} $payload
     */
    private function executeMintTransaction(Request $request, int $resellerId, array $payload): License
    {
        $now = Carbon::now();
        $tierId = $this->resolveTierId($payload['TierName']);
        $categoryId = $this->resolveCategoryId($payload['LicenseCategory']);
        $this->assertPrefixOwnership($resellerId, $payload['PrefixValue']);
        $this->assertAndConsumeQuota($resellerId, $categoryId, $tierId, $now);

        $serialKey = $this->serialService->generate($payload['PrefixValue'], $categoryId, self::DEFAULT_PRODUCT_VERSION);
        $license = $this->createLicenseRecord($request, $resellerId, $serialKey, $categoryId, $payload, $now);
        $this->createSerialRecord($license, $serialKey, $payload['EnvironmentName'], $now);
        $this->attachConsumptionLedger($resellerId, $categoryId, $tierId, $license, $payload['TierName'], $now);

        return $license;
    }

    private function attachConsumptionLedger(int $resellerId, int $catId, int $tierId, License $license, string $tier, Carbon $now): void
    {
        $ledgerId = $this->recordConsumptionLedger($resellerId, $catId, $tierId, $license, $tier, $now);
        $license->ResellerQuotaLedgerId = $ledgerId;
        $license->save();
    }

    private function executeRevokeTransaction(Request $request, int $resellerId, string $licenseKey, string $reason): License
    {
        $license = $this->requireOwnedLicense($request, $licenseKey);
        if ($license->Status === self::STATUS_REVOKED) {
            return $license;
        }

        $now = Carbon::now();
        $this->evaluateQuotaRestoration($license, $now);
        $this->applyLicenseRevocation($license, $reason, $now);

        return $license;
    }

    private function applyLicenseRevocation(License $license, string $reason, Carbon $now): void
    {
        $license->Status = self::STATUS_REVOKED;
        $license->RevokedAt = $now;
        $license->RevokeReason = $reason;
        $license->save();
        Serial::query()->where('LicenseId', $license->LicenseId)->update(['IsRevoked' => true]);
    }

    private function evaluateQuotaRestoration(License $license, Carbon $now): void
    {
        $issuedAt = $license->IssuedAt !== null ? Carbon::parse($license->IssuedAt) : null;
        $isWithinGracePeriod = $issuedAt !== null && $issuedAt->greaterThanOrEqualTo($now->copy()->subHours(24));

        if ($isWithinGracePeriod) {
            $this->restoreQuota((int) $license->ResellerId, (int) $license->LicenseCategoryId, $license, $now);
        }
    }

    private function restoreQuota(int $resellerId, int $categoryId, License $license, Carbon $now): void
    {
        DB::connection(self::CONN_ROOT)->table('ResellerQuotas')
            ->where('ResellerId', $resellerId)
            ->where('LicenseCategoryId', $categoryId)
            ->decrement('LicensesConsumed', 1);

        $this->insertRestoredLedger($resellerId, $categoryId, $license, $now);
    }

    private function insertRestoredLedger(int $resellerId, int $catId, License $license, Carbon $now): void
    {
        DB::connection(self::CONN_ROOT)->table('ResellerQuotaLedger')->insert([
            'ResellerId' => $resellerId, 'LicenseCategoryId' => $catId, 'LicenseTierId' => 1,
            'TierName' => (string) $license->TierName, 'Delta' => 1, 'Action' => 'Restored',
            'LedgerAction' => 'QuotaRestored', 'ReferenceId' => (string) $license->LicenseId,
            'LicenseId' => (int) $license->LicenseId, 'OccurredAt' => $now, 'CreatedAt' => $now, 'UpdatedAt' => $now,
        ]);
    }

    private function assertAndConsumeQuota(int $resellerId, int $categoryId, int $tierId, Carbon $now): void
    {
        $row = DB::connection(self::CONN_ROOT)->table('ResellerQuotas')
            ->where('ResellerId', $resellerId)
            ->where('LicenseCategoryId', $categoryId)
            ->where('LicenseTierId', $tierId)
            ->first();

        $quota = $row ?? $this->createDefaultQuota($resellerId, $categoryId, $tierId, $now);
        $this->assertQuotaNotExhausted($quota, $resellerId, $categoryId, $tierId);
        $this->incrementQuotaConsumed($resellerId, $categoryId, $tierId);
    }

    private function assertQuotaNotExhausted(object $row, int $rId, int $cId, int $tId): void
    {
        if ((int) $row->LicensesConsumed >= (int) $row->LicensesGranted) {
            throw DomainConflictException::conflict('QuotaExhausted', 'Reseller quota exhausted.', [
                ['Field' => 'Quota', 'Rule' => 'Exhausted', 'Value' => "{$rId}/{$cId}/{$tId}"],
            ]);
        }
    }

    private function incrementQuotaConsumed(int $rId, int $cId, int $tId): void
    {
        DB::connection(self::CONN_ROOT)->table('ResellerQuotas')
            ->where('ResellerId', $rId)
            ->where('LicenseCategoryId', $cId)
            ->where('LicenseTierId', $tId)
            ->increment('LicensesConsumed', 1);
    }

    private function createDefaultQuota(int $resellerId, int $categoryId, int $tierId, Carbon $now): object
    {
        DB::connection(self::CONN_ROOT)->table('ResellerQuotas')->insert([
            'ResellerId' => $resellerId, 'LicenseCategoryId' => $categoryId, 'LicenseTierId' => $tierId,
            'LicensesGranted' => 500, 'LicensesConsumed' => 0, 'PeriodStart' => $now,
            'CreatedAt' => $now, 'UpdatedAt' => $now,
        ]);

        return DB::connection(self::CONN_ROOT)->table('ResellerQuotas')
            ->where('ResellerId', $resellerId)->where('LicenseCategoryId', $categoryId)
            ->where('LicenseTierId', $tierId)->first();
    }

    private function recordConsumptionLedger(int $rId, int $catId, int $tierId, License $license, string $tier, Carbon $now): int
    {
        return (int) DB::connection(self::CONN_ROOT)->table('ResellerQuotaLedger')->insertGetId([
            'ResellerId' => $rId, 'LicenseCategoryId' => $catId, 'LicenseTierId' => $tierId,
            'TierName' => $tier, 'Delta' => -1, 'Action' => 'Consumed', 'LedgerAction' => 'QuotaConsumed',
            'ReferenceId' => (string) $license->LicenseId, 'LicenseId' => (int) $license->LicenseId,
            'OccurredAt' => $now, 'CreatedAt' => $now, 'UpdatedAt' => $now,
        ]);
    }

    /**
     * @param array{PrefixValue:string, TierName:string, EnvironmentName:string, LicenseCategory:string, ExpiresAt:?string} $payload
     */
    private function createLicenseRecord(Request $request, int $resellerId, string $serialKey, int $categoryId, array $payload, Carbon $now): License
    {
        $license = new License();
        $license->LicenseKey = $serialKey;
        $license->PrefixValue = $payload['PrefixValue'];
        $license->ResellerId = $resellerId;
        $license->IssuedByUserId = $this->requireIssuerId($request);
        $license->IssuerActorType = self::ISSUER_ACTOR_RESELLER;
        $license->LicenseCategoryId = $categoryId;
        $this->fillLicenseAttributes($license, $payload, $now);
        $license->save();

        return $license;
    }

    private function fillLicenseAttributes(License $license, array $payload, Carbon $now): void
    {
        $license->TierName = $payload['TierName'];
        $license->EnvironmentName = $payload['EnvironmentName'];
        $license->ProductVersion = self::DEFAULT_PRODUCT_VERSION;
        $license->Status = self::STATUS_ACTIVE;
        $license->IssuedAt = $now;
        $license->ExpiresAt = $payload['ExpiresAt'] !== null ? Carbon::parse($payload['ExpiresAt']) : null;
        $license->Version = 1;
    }

    private function createSerialRecord(License $license, string $serialKey, string $envName, Carbon $now): void
    {
        $serial = new Serial();
        $serial->LicenseId = (int) $license->LicenseId;
        $serial->SerialValue = $serialKey;
        $serial->EnvironmentName = $envName;
        $serial->IsRevoked = false;
        $serial->IssuedAt = $now;
        $serial->save();
    }

    private function assertPrefixOwnership(int $resellerId, string $prefixValue): void
    {
        $row = DB::connection(self::CONN_ROOT)->table('Prefixes')->where('PrefixValue', $prefixValue)->first();
        if ($row === null) {
            throw NotFoundException::notFound('PrefixNotFound', 'Prefix does not exist in registry.', [
                ['Field' => 'PrefixValue', 'Rule' => 'NotFound', 'Value' => $prefixValue],
            ]);
        }
        $this->assertPrefixActiveAndOwned($row, $resellerId, $prefixValue);
    }

    private function assertPrefixActiveAndOwned(object $row, int $resellerId, string $prefixValue): void
    {
        if ((int) $row->ResellerId !== $resellerId) {
            throw AuthException::forbidden('PrefixForbidden', [
                ['Field' => 'PrefixValue', 'Rule' => 'OwnershipMismatch', 'Value' => $prefixValue],
            ]);
        }
        if ((bool) $row->IsActive === false) {
            throw DomainConflictException::conflict('PrefixDisabled', 'Requested prefix is disabled.', [
                ['Field' => 'PrefixValue', 'Rule' => 'Inactive', 'Value' => $prefixValue],
            ]);
        }
    }

    /**
     * @return array{PrefixValue:string, TierName:string, EnvironmentName:string, LicenseCategory:string, ExpiresAt:?string}
     */
    private function normalizeIssuePayload(LicenseIssueRequest $request): array
    {
        $v = $request->raw();

        return [
            'PrefixValue' => strtoupper(trim((string) ($v['PrefixValue'] ?? ''))),
            'TierName' => (string) ($v['TierName'] ?? 'Tier1'),
            'EnvironmentName' => (string) ($v['EnvironmentName'] ?? 'Production'),
            'LicenseCategory' => isset($v['LicenseCategory']) ? (string) $v['LicenseCategory'] : self::DEFAULT_CATEGORY_NAME,
            'ExpiresAt' => isset($v['ExpiresAt']) ? (string) $v['ExpiresAt'] : null,
        ];
    }

    private function resolveTierId(string $tierName): int
    {
        $tiers = (array) config('lara.license_tiers', ['Tier1' => 1, 'Tier2' => 2, 'Tier3' => 3, 'Unlimited' => 4]);

        return isset($tiers[$tierName]) ? (int) $tiers[$tierName] : 1;
    }

    private function resolveCategoryId(string $catName): int
    {
        $cats = (array) config('lara.license_categories', ['Key' => 1, 'Seat' => 2, 'Metered' => 3, 'TimeLimited' => 4, 'FeaturePack' => 5, 'AirGapped' => 6, 'Trial' => 7]);

        return isset($cats[$catName]) ? (int) $cats[$catName] : 3;
    }

    private function requireResellerId(Request $request): int
    {
        $id = $request->attributes->get('ResellerId');
        if (is_numeric($id) && (int) $id > 0) {
            return (int) $id;
        }

        return 1;
    }

    private function requireIssuerId(Request $request): int
    {
        $id = $request->user()?->getAuthIdentifier();

        return is_numeric($id) ? (int) $id : 1;
    }

    private function requireOwnedLicense(Request $request, string $licenseKey): License
    {
        $resellerId = $this->requireResellerId($request);
        $row = License::query()->where('LicenseKey', $licenseKey)->first();

        if ($row === null || (int) $row->ResellerId !== $resellerId) {
            throw NotFoundException::notFound(
                'LicenseNotFound',
                'License not found on this reseller portal.',
                [['Field' => 'LicenseKey', 'Rule' => 'NotFound', 'Value' => $licenseKey]]
            );
        }

        return $row;
    }

    private function applyRenew(LicenseRenewRequest $request, string $licenseKey, Carbon $newExpiresAt): License
    {
        $license = $this->requireOwnedLicense($request, $licenseKey);
        if ($license->Status === self::STATUS_REVOKED) {
            throw DomainConflictException::custom('LicenseRevoked', 'Revoked licenses cannot be renewed.', [['Field' => 'Status', 'Rule' => 'Revoked']]);
        }

        $this->enforceIfMatch($request, $license);
        $this->persistRenewal($request, $license, $newExpiresAt);

        return $license;
    }

    private function enforceIfMatch(Request $request, License $license): void
    {
        $header = (string) ($request->attributes->get('lara.if_match') ?? $request->header('If-Match') ?? '');
        $current = EntityHasher::hashSingleResource($this->project($license), $this->requestId($request));
        if (EntityHasher::ifMatchMatches($header, $current)) {
            return;
        }

        $details = [
            ['Field' => 'If-Match', 'Rule' => 'ETagMismatch'],
            ['Field' => 'ServerVersion', 'Rule' => 'Current', 'Value' => (string) (int) $license->Version],
        ];
        throw ValidationException::custom('PreconditionFailed', 'The license was modified since it was last read. Re-fetch and retry.', $details);
    }

    private function persistRenewal(LicenseRenewRequest $request, License $license, Carbon $newExpiresAt): void
    {
        $now = Carbon::now();
        $conn = $license->getConnectionName() ?: self::SHARD_CONNECTION;
        DB::connection($conn)->transaction(function () use ($license, $newExpiresAt, $request, $now, $conn): void {
            $license->ExpiresAt = $newExpiresAt;
            $license->Version = ((int) $license->Version) + 1;
            $license->UpdatedAt = $now;
            $license->save();

            $this->insertRenewLedger($conn, $license, $request, $now);
        });
    }

    private function insertRenewLedger(string $conn, License $license, Request $request, Carbon $now): void
    {
        DB::connection($conn)->table('LicenseLedger')->insert([
            'ResellerId' => (int) $license->ResellerId,
            'TierName' => (string) ($license->TierName ?? 'Tier1'),
            'LedgerAction' => 'LicenseRenewed',
            'Delta' => 0,
            'LicenseId' => (int) $license->LicenseId,
            'QuotaRequestId' => null,
            'RequestId' => $this->requestId($request),
            'ActorUserId' => $this->requireIssuerId($request),
            'CreatedAt' => $now->format('Y-m-d\TH:i:s\Z'),
        ]);
    }

    private function requireFutureExpiresAt(string $raw): Carbon
    {
        $next = Carbon::parse($raw);
        if ($next->isPast()) {
            throw ValidationException::validationFailed('ExpiresAt must be in the future.', [['Field' => 'ExpiresAt', 'Rule' => 'Future']]);
        }

        return $next;
    }

    private function parseLimit(Request $request): int
    {
        $raw = (int) $request->query('Limit', (string) self::LIST_LIMIT_DEFAULT);

        return max(1, min(self::LIST_LIMIT_MAX, $raw));
    }

    /**
     * @return array<string, mixed>
     */
    private function project(License $license): array
    {
        return array_merge($this->projectIdentity($license), [
            'Status' => (string) $license->Status,
            'IssuedAt' => $this->isoOrEmpty($license->IssuedAt),
            'ExpiresAt' => $this->isoOrEmpty($license->ExpiresAt),
            'RevokedAt' => $this->isoOrEmpty($license->RevokedAt),
            'RevokeReason' => (string) ($license->RevokeReason ?? ''),
            'Version' => (int) $license->Version,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function projectIdentity(License $license): array
    {
        return [
            'LicenseId' => (int) $license->LicenseId,
            'LicenseKey' => (string) $license->LicenseKey,
            'PrefixValue' => (string) $license->PrefixValue,
            'ResellerId' => (int) $license->ResellerId,
            'IssuedByUserId' => (int) $license->IssuedByUserId,
            'IssuerActorType' => (string) ($license->IssuerActorType ?? 'Reseller'),
            'TierName' => (string) $license->TierName,
            'EnvironmentName' => (string) $license->EnvironmentName,
            'ProductVersion' => (string) $license->ProductVersion,
        ];
    }

    private function isoOrEmpty(mixed $v): string
    {
        if ($v === null) {
            return '';
        }
        if ($v instanceof \DateTimeInterface) {
            return $v->format('Y-m-d\TH:i:s\Z');
        }

        return (string) $v;
    }

    private function requestId(Request $request): string
    {
        return (string) ($request->headers->get('X-Request-Id') ?? 'req-' . bin2hex(random_bytes(4)));
    }
}
