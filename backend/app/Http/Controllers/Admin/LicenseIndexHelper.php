<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Db\ShardResolver;
use App\Exceptions\AuthException;
use App\Exceptions\ValidationException;
use App\Exceptions\RateLimitException;
use App\Exceptions\NotFoundException;
use App\Exceptions\DomainConflictException;
use App\Exceptions\InternalException;
use App\Http\Requests\Admin\LicenseIssueRequest;
use App\Http\Requests\Admin\LicenseRevokeRequest;
use App\Http\Requests\Admin\LicenseUpdateRequest;
use App\Models\License;
use App\Models\Prefix;
use App\Models\Reseller;
use App\Support\ApiEnvelope;
use App\Support\AuditWriter;
use App\Support\FeatureValidator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

use Throwable;

final class LicenseIndexHelper
{
    public static function index(
        Request $request,
        ShardResolver $shardResolver,
        \Closure $isoFormatter,
        \Closure $requestIdGetter
    ): JsonResponse {
        $limit = max(1, min(200, (int) ($request->query('Limit', 100))));
        $resellerSlug = $request->query('ResellerSlug');
        $resellerId = $request->query('ResellerId');

        $query = Reseller::query()->where('IsActive', true);
        if ($resellerSlug !== null && $resellerSlug !== '') {
            $query->where('ResellerSlug', $resellerSlug);
        } elseif ($resellerId !== null && is_numeric($resellerId)) {
            $query->where('ResellerId', (int) $resellerId);
        }
        $resellers = $query->orderBy('ResellerSlug')->get();

        $collected = [];
        $warnings = [];

        foreach ($resellers as $reseller) {
            try {
                $shardResolver->bind((string) $reseller->ResellerSlug);
                $rows = License::query()
                    ->where('ResellerId', (int) $reseller->ResellerId)
                    ->orderBy('LicenseId', 'desc')
                    ->limit($limit)
                    ->get();

                foreach ($rows as $row) {
                    $collected[] = [
                        'LicenseId' => (int) $row->LicenseId,
                        'LicenseKey' => (string) $row->LicenseKey,
                        'PrefixValue' => (string) $row->PrefixValue,
                        'ResellerId' => (int) $row->ResellerId,
                        'ResellerSlug' => (string) $reseller->ResellerSlug,
                        'IssuedByUserId' => (int) $row->IssuedByUserId,
                        'IssuerActorType' => (string) ($row->IssuerActorType ?? 'Reseller'),
                        'LicenseCategoryId' => (int) ($row->LicenseCategoryId ?? 3),
                        'LicenseTierId' => (int) ($row->LicenseTierId ?? 1),
                        'TierName' => (string) $row->TierName,
                        'EnvironmentId' => (int) ($row->EnvironmentId ?? 1),
                        'EnvironmentName' => (string) $row->EnvironmentName,
                        'ProductVersion' => (string) $row->ProductVersion,
                        'Status' => (string) $row->Status,
                        'IsActive' => (string) $row->Status === 'Active',
                        'IsSingleUse' => false,
                        'IssuedAt' => $isoFormatter($row->IssuedAt),
                        'ExpiresAt' => $isoFormatter($row->ExpiresAt) ?: null,
                        'RevokedAt' => $isoFormatter($row->RevokedAt) ?: null,
                        'RevokeReason' => (string) ($row->RevokeReason ?? ''),
                        'Version' => (int) $row->Version,
                    ];
                }
            } catch (Throwable $e) {
                Log::warning('admin.licenses.index.shard_error', [
                    'requestId' => $requestIdGetter($request),
                    'resellerSlug' => (string) $reseller->ResellerSlug,
                    'error' => $e->getMessage(),
                ]);
                $warnings[] = ['ResellerSlug' => (string) $reseller->ResellerSlug, 'Error' => 'ShardUnavailable'];
            }
        }

        usort($collected, static fn (array $a, array $b): int => strcmp((string) ($b['IssuedAt'] ?? ''), (string) ($a['IssuedAt'] ?? '')));
        $projected = array_slice($collected, 0, $limit);

        return ApiEnvelope::success(
            results: $projected,
            requestId: $requestIdGetter($request),
            extraAttributes: ['Warnings' => $warnings, 'Total' => count($collected)],
        );
    }
}
