<?php

declare(strict_types=1);

namespace App\Services;

use App\Exceptions\ValidationException;
use App\Models\PricingPlan;
use App\Models\PricingPlanPriceVersion;
use App\Support\AuditWriter;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/** Versioned PricingPlan price changes: a price change appends a snapshot, never overwrites history. */
final class PricingPlanPriceVersionService
{
    private const ACTION = 'PricingPlanPriceChanged';

    /**
     * @param array<string, mixed> $data
     */
    public function applyPriceChange(Request $request, PricingPlan $plan, array $data, array $input): PricingPlan
    {
        $changed = $this->isPriceChanged($plan, $data);
        if ($changed === false) {
            $plan->update($data);

            return $plan->fresh();
        }

        return $this->applyVersioned($request, $plan, $data, $input);
    }

    public function ensureInitialVersion(PricingPlan $plan): void
    {
        $count = PricingPlanPriceVersion::query()->where('PricingPlanId', (int) $plan->getKey())->count();
        if ($count > 0) {
            return;
        }

        $effective = $plan->created_at instanceof Carbon ? $plan->created_at : Carbon::now();
        $this->snapshot($plan, $plan->toArray(), 1, $effective);
    }

    /**
     * @param array<string, mixed> $data
     */
    public function isPriceChanged(PricingPlan $plan, array $data): bool
    {
        return (int) $plan->retailPriceCents !== (int) $data['retailPriceCents'] || (int) $plan->discountedPriceCents !== (int) $data['discountedPriceCents'];
    }

    private function applyVersioned(Request $request, PricingPlan $plan, array $data, array $input): PricingPlan
    {
        $effective = $this->effectiveFrom($input);
        $this->ensureInitialVersion($plan);
        $version = $this->nextVersionNumber((int) $plan->getKey());
        $this->runVersionedUpdate($plan, $data, $version, $effective);
        $this->auditPriceChange($request, $plan, $version, $effective);

        return $plan->fresh();
    }

    private function runVersionedUpdate(PricingPlan $plan, array $data, int $version, Carbon $effective): void
    {
        DB::connection('root')->transaction(function () use ($plan, $data, $version, $effective): void {
            $this->snapshot($plan, $data, $version, $effective);
            $plan->update($data);
        });
    }

    private function snapshot(PricingPlan $plan, array $data, int $version, Carbon $effective): PricingPlanPriceVersion
    {
        return PricingPlanPriceVersion::create([
            'PricingPlanId' => (int) $plan->getKey(), 'versionNumber' => $version, 'planCode' => (string) $data['planCode'],
            'planName' => (string) $data['planName'], 'billingInterval' => (string) $data['billingInterval'],
            'retailPriceCents' => (int) $data['retailPriceCents'], 'discountedPriceCents' => (int) $data['discountedPriceCents'],
            'allowedMachineSlots' => (int) $data['allowedMachineSlots'], 'isBestValue' => (bool) ($data['isBestValue'] ?? $plan->isBestValue),
            'isActive' => (bool) ($data['isActive'] ?? $plan->isActive), 'effectiveFrom' => $effective,
        ]);
    }

    private function nextVersionNumber(int $planId): int
    {
        $max = PricingPlanPriceVersion::query()->where('PricingPlanId', $planId)->max('versionNumber');
        if ($max === null) {
            return 1;
        }

        return (int) $max + 1;
    }

    private function effectiveFrom(array $input): Carbon
    {
        $raw = $input['effectiveFrom'] ?? null;
        if ($raw === null) {
            return Carbon::now();
        }
        try {
            $parsed = Carbon::parse((string) $raw);
        } catch (\Throwable) {
            throw ValidationException::invalidInput('effectiveFrom must be a valid date.');
        }
        if ($parsed->isFuture()) {
            throw ValidationException::invalidInput('effectiveFrom must not be in the future.');
        }

        return $parsed;
    }

    private function auditPriceChange(Request $request, PricingPlan $plan, int $version, Carbon $effective): void
    {
        AuditWriter::write($request, self::ACTION, 'PricingPlan', (int) $plan->getKey(), [
            'versionNumber' => $version, 'effectiveFrom' => $effective->toIso8601String(),
            'retailPriceCents' => (int) $plan->retailPriceCents, 'discountedPriceCents' => (int) $plan->discountedPriceCents,
        ]);
    }
}
