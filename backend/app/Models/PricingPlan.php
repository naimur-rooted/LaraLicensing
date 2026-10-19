<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Root-DB commercial pricing tier row.
 */
final class PricingPlan extends Model
{
    protected $connection = 'root';
    protected $table = 'PricingPlans';
    protected $primaryKey = 'PricingPlanId';

    protected $fillable = [
        'planCode',
        'planName',
        'billingInterval',
        'retailPriceCents',
        'discountedPriceCents',
        'allowedMachineSlots',
        'isBestValue',
        'isActive',
    ];

    protected $casts = [
        'PricingPlanId' => 'int',
        'retailPriceCents' => 'int',
        'discountedPriceCents' => 'int',
        'allowedMachineSlots' => 'int',
        'isBestValue' => 'bool',
        'isActive' => 'bool',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];
}
