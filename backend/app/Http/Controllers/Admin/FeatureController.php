<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Support\ApiEnvelope;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Controller exposing the feature catalog matrix endpoints (spec 45 v1.0.0).
 */
final class FeatureController extends Controller
{
    private const ROOT_CONNECTION = 'root';
    private const FEATURES_TABLE = 'Features';
    private const TIERS_TABLE = 'LicenseTiers';
    private const TIER_FEATURES_TABLE = 'TierFeatures';

    public function index(Request $request): JsonResponse
    {
        $tiers = $this->fetchTiers();
        $features = $this->fetchFeatures();
        $cells = $this->buildCellsMap();
        $tierAxis = $this->buildTierAxis($tiers);
        $items = $this->buildFeatureItems($features);
        $matrix = $this->buildMatrixResults($features, $tierAxis, $cells);

        return ApiEnvelope::success(
            results: ['Items' => $items, 'Matrix' => $matrix],
            requestId: (string) ($request->headers->get('X-Request-Id') ?? ''),
            extraAttributes: ['Tiers' => $tierAxis],
        );
    }

    private function fetchTiers(): \Illuminate\Support\Collection
    {
        if (!DB::connection(self::ROOT_CONNECTION)->getSchemaBuilder()->hasTable(self::TIERS_TABLE)) {
            return collect();
        }
        return DB::connection(self::ROOT_CONNECTION)->table(self::TIERS_TABLE)->orderBy('TierOrdinal')->get();
    }

    private function fetchFeatures(): \Illuminate\Support\Collection
    {
        if (!DB::connection(self::ROOT_CONNECTION)->getSchemaBuilder()->hasTable(self::FEATURES_TABLE)) {
            return collect();
        }
        return DB::connection(self::ROOT_CONNECTION)->table(self::FEATURES_TABLE)->get();
    }

    private function buildFeatureItems(\Illuminate\Support\Collection $features): array
    {
        return $features->map(static function ($f): array {
            $key = (string) ($f->FeatureKey ?? $f->Slug ?? '');
            return [
                'FeatureId' => (int) ($f->FeatureId ?? 0),
                'FeatureKey' => $key,
                'Slug' => $key,
                'Name' => (string) ($f->Name ?? $key),
                'Description' => (string) ($f->Description ?? ''),
                'ValueType' => (string) ($f->ValueType ?? 'boolean'),
            ];
        })->sortBy('Slug')->values()->all();
    }

    private function buildCellsMap(): array
    {
        if (!DB::connection(self::ROOT_CONNECTION)->getSchemaBuilder()->hasTable(self::TIER_FEATURES_TABLE)) {
            return [];
        }
        $assignments = DB::connection(self::ROOT_CONNECTION)->table(self::TIER_FEATURES_TABLE)->orderBy('FeatureId')->orderBy('LicenseTierId')->get();
        $cells = [];
        foreach ($assignments as $row) {
            $key = (int) $row->FeatureId . '|' . (int) $row->LicenseTierId;
            $cells[$key] = [
                'Value' => json_decode((string) ($row->ValueScalar ?? $row->Value ?? ''), true),
                'UpdatedAt' => isset($row->UpdatedAt) ? (string) $row->UpdatedAt : null,
            ];
        }
        return $cells;
    }

    private function buildTierAxis(\Illuminate\Support\Collection $tiers): array
    {
        return $tiers->map(static fn ($t): array => [
            'LicenseTierId' => (int) $t->LicenseTierId,
            'TierName' => (string) $t->TierName,
            'TierOrdinal' => (int) $t->TierOrdinal,
        ])->all();
    }

    private function buildMatrixResults(\Illuminate\Support\Collection $features, array $tierAxis, array $cells): array
    {
        return $features->map(static function ($f) use ($tierAxis, $cells): array {
            $featureId = (int) $f->FeatureId;
            $row = [];
            foreach ($tierAxis as $tier) {
                $row[(string) $tier['LicenseTierId']] = $cells[$featureId . '|' . $tier['LicenseTierId']] ?? null;
            }
            return [
                'FeatureId' => $featureId,
                'FeatureKey' => (string) ($f->FeatureKey ?? ''),
                'ValueType' => (string) ($f->ValueType ?? 'boolean'),
                'Cells' => $row,
                'AssignedTierCount' => count(array_filter($row, static fn ($c): bool => $c !== null)),
            ];
        })->all();
    }
}
