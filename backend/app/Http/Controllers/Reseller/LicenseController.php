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

        AuditWriter::write($request, 'LicenseIssued', 'Licenses', (int) $license->LicenseId, [
            'LicenseKey' => (string) $license->LicenseKey,
            'ResellerId' => $resellerId,
            'TierName' => (string) $license->TierName,
        ]);

        return ApiEnvelope::success(
            results: [$this->project($license)],
            requestId: $this->requestId($request),
            httpCode: 201,
        );
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
            ->where('ResellerId', $resellerId)
            ->get();
        $ledger = DB::connection(self::CONN_ROOT)->table('ResellerQuotaLedger')
            ->where('ResellerId', $resellerId)
            ->orderBy('Id', 'desc')
            ->limit(20)
            ->get();

        return ApiEnvelope::success([
            'Quotas' => $quotas,
            'RecentLedger' => $ledger,
        ], $this->requestId($request));
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
        $ledgerId = $this->recordConsumptionLedger($resellerId, $categoryId, $tierId, $license, $payload['TierName'], $now);

        $license->ResellerQuotaLedgerId = $ledgerId;
        $license->save();

        return $license;
    }

    private function executeRevokeTransaction(Request $request, int $resellerId, string $licenseKey, string $reason): License
    {
        $license = $this->requireOwnedLicense($request, $licenseKey);
        if ($license->Status === self::STATUS_REVOKED) {
            return $license;
        }

        $now = Carbon::now();
        $this->evaluateQuotaRestoration($license, $now);

        $license->Status = self::STATUS_REVOKED;
        $license->RevokedAt = $now;
        $license->RevokeReason = $reason;
        $license->save();

        Serial::query()->where('LicenseId', $license->LicenseId)->update(['IsRevoked' => true]);

        return $license;
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

        DB::connection(self::CONN_ROOT)->table('ResellerQuotaLedger')->insert([
            'ResellerId' => $resellerId,
            'LicenseCategoryId' => $categoryId,
            'LicenseTierId' => 1,
            'TierName' => (string) $license->TierName,
            'Delta' => 1,
            'Action' => 'Restored',
            'LedgerAction' => 'QuotaRestored',
            'ReferenceId' => (string) $license->LicenseId,
            'LicenseId' => (int) $license->LicenseId,
            'OccurredAt' => $now,
            'CreatedAt' => $now,
            'UpdatedAt' => $now,
        ]);
    }

    private function assertAndConsumeQuota(int $resellerId, int $categoryId, int $tierId, Carbon $now): void
    {
        $row = DB::connection(self::CONN_ROOT)->table('ResellerQuotas')
            ->where('ResellerId', $resellerId)
            ->where('LicenseCategoryId', $categoryId)
            ->where('LicenseTierId', $tierId)
            ->first();

        if ($row === null) {
            $row = $this->createDefaultQuota($resellerId, $categoryId, $tierId, $now);
        }

        if ((int) $row->LicensesConsumed >= (int) $row->LicensesGranted) {
            throw DomainConflictException::conflict(
                'QuotaExhausted',
                'Reseller quota exhausted. Consume allowance exceeded granted pool.',
                [['Field' => 'Quota', 'Rule' => 'Exhausted', 'Value' => "{$resellerId}/{$categoryId}/{$tierId}"]]
            );
        }

        DB::connection(self::CONN_ROOT)->table('ResellerQuotas')
            ->where('ResellerId', $resellerId)
            ->where('LicenseCategoryId', $categoryId)
            ->where('LicenseTierId', $tierId)
            ->increment('LicensesConsumed', 1);
    }

    private function createDefaultQuota(int $resellerId, int $categoryId, int $tierId, Carbon $now): object
    {
        DB::connection(self::CONN_ROOT)->table('ResellerQuotas')->insert([
            'ResellerId' => $resellerId,
            'LicenseCategoryId' => $categoryId,
            'LicenseTierId' => $tierId,
            'LicensesGranted' => 500,
            'LicensesConsumed' => 0,
            'PeriodStart' => $now,
            'CreatedAt' => $now,
            'UpdatedAt' => $now,
        ]);

        return DB::connection(self::CONN_ROOT)->table('ResellerQuotas')
            ->where('ResellerId', $resellerId)
            ->where('LicenseCategoryId', $categoryId)
            ->where('LicenseTierId', $tierId)
            ->first();
    }

    private function recordConsumptionLedger(int $resellerId, int $categoryId, int $tierId, License $license, string $tierName, Carbon $now): int
    {
        return (int) DB::connection(self::CONN_ROOT)->table('ResellerQuotaLedger')->insertGetId([
            'ResellerId' => $resellerId,
            'LicenseCategoryId' => $categoryId,
            'LicenseTierId' => $tierId,
            'TierName' => $tierName,
            'Delta' => -1,
            'Action' => 'Consumed',
            'LedgerAction' => 'QuotaConsumed',
            'ReferenceId' => (string) $license->LicenseId,
            'LicenseId' => (int) $license->LicenseId,
            'OccurredAt' => $now,
            'CreatedAt' => $now,
            'UpdatedAt' => $now,
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
        $license->TierName = $payload['TierName'];
        $license->EnvironmentName = $payload['EnvironmentName'];
        $license->ProductVersion = self::DEFAULT_PRODUCT_VERSION;
        $license->Status = self::STATUS_ACTIVE;
        $license->IssuedAt = $now;
        $license->ExpiresAt = $payload['ExpiresAt'] !== null ? Carbon::parse($payload['ExpiresAt']) : null;
        $license->Version = 1;
        $license->save();

        return $license;
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
        $row = DB::connection(self::CONN_ROOT)->table('Prefixes')
            ->where('PrefixValue', $prefixValue)
            ->first();

        if ($row === null) {
            throw NotFoundException::notFound(
                'PrefixNotFound',
                'Prefix does not exist in registry.',
                [['Field' => 'PrefixValue', 'Rule' => 'NotFound', 'Value' => $prefixValue]]
            );
        }

        if ((int) $row->ResellerId !== $resellerId) {
            throw AuthException::forbidden(
                'PrefixForbidden',
                [['Field' => 'PrefixValue', 'Rule' => 'OwnershipMismatch', 'Value' => $prefixValue]]
            );
        }

        if ((bool) $row->IsActive === false) {
            throw DomainConflictException::conflict(
                'PrefixDisabled',
                'Requested prefix is disabled.',
                [['Field' => 'PrefixValue', 'Rule' => 'Inactive', 'Value' => $prefixValue]]
            );
        }
    }

    /**
     * @return array{PrefixValue:string, TierName:string, EnvironmentName:string, LicenseCategory:string, ExpiresAt:?string}
     */
    private function normalizeIssuePayload(LicenseIssueRequest $request): array
    {
        $v = $request->raw();
        $prefix = strtoupper(trim((string) ($v['PrefixValue'] ?? '')));
        $tierName = (string) ($v['TierName'] ?? 'Tier1');
        $envName = (string) ($v['EnvironmentName'] ?? 'Production');
        $catName = isset($v['LicenseCategory']) && $v['LicenseCategory'] !== null ? (string) $v['LicenseCategory'] : self::DEFAULT_CATEGORY_NAME;
        $expires = isset($v['ExpiresAt']) && $v['ExpiresAt'] !== null ? (string) $v['ExpiresAt'] : null;

        return [
            'PrefixValue' => $prefix,
            'TierName' => $tierName,
            'EnvironmentName' => $envName,
            'LicenseCategory' => $catName,
            'ExpiresAt' => $expires,
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

        $license->ExpiresAt = $newExpiresAt;
        $license->Version = ((int) $license->Version) + 1;
        $license->UpdatedAt = Carbon::now();
        $license->save();

        return $license;
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
            'Status' => (string) $license->Status,
            'IssuedAt' => $this->isoOrEmpty($license->IssuedAt),
            'ExpiresAt' => $this->isoOrEmpty($license->ExpiresAt),
            'RevokedAt' => $this->isoOrEmpty($license->RevokedAt),
            'RevokeReason' => (string) ($license->RevokeReason ?? ''),
            'Version' => (int) $license->Version,
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
