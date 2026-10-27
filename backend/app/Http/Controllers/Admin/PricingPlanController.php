<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Exceptions\NotFoundException;
use App\Exceptions\ValidationException;
use App\Http\Controllers\Controller;
use App\Models\PricingPlan;
use App\Support\ApiEnvelope;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Admin CRUD for platform PricingPlans (Root DB).
 *
 * Previously only a public read path existed (GET /Pricing/Plans, active
 * plans only). This controller lives inside the existing Admin group
 * (auth:sanctum + session.active + require.role:Admin|SuperAdmin), so no
 * new auth system is invented. It reuses the existing PricingPlan model
 * and its fillable fields; no new package system is introduced.
 */
final class PricingPlanController extends Controller
{
    private const ALLOWED_INTERVALS = ['daily', 'weekly', 'monthly', 'quarterly', 'yearly', 'biennial', 'lifetime'];

    public function index(Request $request): JsonResponse
    {
        $plans = PricingPlan::query()->orderBy('PricingPlanId')->get();

        return ApiEnvelope::success(
            results: ['Items' => $plans->all()],
            requestId: (string) ($request->headers->get('X-Request-Id') ?? ''),
        );
    }

    public function show(Request $request, int $PricingPlanId): JsonResponse
    {
        $plan = $this->findOrFail($PricingPlanId);

        return ApiEnvelope::success(
            results: ['Item' => $plan],
            requestId: (string) ($request->headers->get('X-Request-Id') ?? ''),
        );
    }

    public function store(Request $request): JsonResponse
    {
        $data = $this->validatePlan($request->all(), null);
        $plan = PricingPlan::create($data);

        return ApiEnvelope::success(
            results: ['Item' => $plan],
            requestId: (string) ($request->headers->get('X-Request-Id') ?? ''),
            httpCode: 201,
        );
    }

    public function update(Request $request, int $PricingPlanId): JsonResponse
    {
        $plan = $this->findOrFail($PricingPlanId);
        $data = $this->validatePlan($request->all(), $plan);
        $plan->update($data);

        return ApiEnvelope::success(
            results: ['Item' => $plan->fresh()],
            requestId: (string) ($request->headers->get('X-Request-Id') ?? ''),
        );
    }

    public function destroy(Request $request, int $PricingPlanId): JsonResponse
    {
        $plan = $this->findOrFail($PricingPlanId);
        $plan->delete();

        return ApiEnvelope::success(
            results: ['Deleted' => true, 'PricingPlanId' => $PricingPlanId],
            requestId: (string) ($request->headers->get('X-Request-Id') ?? ''),
        );
    }

    private function findOrFail(int $id): PricingPlan
    {
        $plan = PricingPlan::query()->where('PricingPlanId', $id)->first();
        if ($plan === null) {
            throw NotFoundException::notFound("PricingPlan [{$id}] not found.");
        }

        return $plan;
    }

    /**
     * @param array<string,mixed> $input
     * @return array<string,mixed>
     */
    private function validatePlan(array $input, ?PricingPlan $existing): array
    {
        $isCreate = $existing === null;
        $merged = $existing !== null ? array_merge($existing->toArray(), $input) : $input;

        $planCode = trim((string) ($merged['planCode'] ?? ''));
        if ($planCode === '') {
            throw ValidationException::invalidInput('planCode is required.');
        }
        $conflict = PricingPlan::query()->where('planCode', $planCode);
        if ($existing !== null) {
            $conflict->where('PricingPlanId', '!=', (int) $existing->PricingPlanId);
        }
        if ($conflict->exists()) {
            throw ValidationException::invalidInput("planCode [{$planCode}] is already in use.");
        }

        $planName = trim((string) ($merged['planName'] ?? ''));
        if ($planName === '') {
            throw ValidationException::invalidInput('planName is required.');
        }

        $interval = strtolower(trim((string) ($merged['billingInterval'] ?? '')));
        if (!in_array($interval, self::ALLOWED_INTERVALS, true)) {
            throw ValidationException::invalidInput("billingInterval [{$interval}] is not allowed.");
        }

        $retail = $merged['retailPriceCents'] ?? null;
        $discounted = $merged['discountedPriceCents'] ?? null;
        $slots = $merged['allowedMachineSlots'] ?? null;
        if (!is_numeric($retail) || (int) $retail < 0) {
            throw ValidationException::invalidInput('retailPriceCents must be >= 0.');
        }
        if (!is_numeric($discounted) || (int) $discounted < 0) {
            throw ValidationException::invalidInput('discountedPriceCents must be >= 0.');
        }
        if (!is_numeric($slots) || (int) $slots < 1) {
            throw ValidationException::invalidInput('allowedMachineSlots must be >= 1.');
        }

        $data = [
            'planCode' => $planCode,
            'planName' => $planName,
            'billingInterval' => $interval,
            'retailPriceCents' => (int) $retail,
            'discountedPriceCents' => (int) $discounted,
            'allowedMachineSlots' => (int) $slots,
        ];
        if ($isCreate || array_key_exists('isBestValue', $input)) {
            $data['isBestValue'] = (bool) ($merged['isBestValue'] ?? false);
        }
        if ($isCreate || array_key_exists('isActive', $input)) {
            $data['isActive'] = (bool) ($merged['isActive'] ?? true);
        }

        return $data;
    }
}
