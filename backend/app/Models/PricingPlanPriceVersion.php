<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Immutable snapshot of a PricingPlan at one price version.
 *
 * The live PricingPlans row stays the current catalog price for new
 * sales. Each price change appends a snapshot here, so the previous
 * price is never overwritten and can be restored by a new version.
 */
final class PricingPlanPriceVersion extends Model
{
    protected $connection = 'root';
    protected $table = 'PricingPlanPriceVersions';
    protected $primaryKey = 'PricingPlanPriceVersionId';

    protected $fillable = [
        'PricingPlanId',
        'versionNumber',
        'planCode',
        'planName',
        'billingInterval',
        'retailPriceCents',
        'discountedPriceCents',
        'allowedMachineSlots',
        'isBestValue',
        'isActive',
        'effectiveFrom',
    ];

    protected $casts = [
        'PricingPlanId' => 'int',
        'versionNumber' => 'int',
        'retailPriceCents' => 'int',
        'discountedPriceCents' => 'int',
        'allowedMachineSlots' => 'int',
        'isBestValue' => 'bool',
        'isActive' => 'bool',
        'effectiveFrom' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];
}
