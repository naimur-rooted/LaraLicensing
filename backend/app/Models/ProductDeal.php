<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Eloquent model for ProductDeals table in Root DB.
 */
class ProductDeal extends Model
{
    protected $connection = 'root';
    protected $table = 'ProductDeals';
    protected $primaryKey = 'ProductDealId';

    protected $fillable = [
        'productId',
        'dealName',
        'dealCode',
        'billingInterval',
        'priceCents',
        'allowedMachineSeats',
        'allowedDomains',
        'isDecoyTarget',
        'features',
        'isActive',
    ];

    protected $casts = [
        'priceCents' => 'integer',
        'allowedMachineSeats' => 'integer',
        'allowedDomains' => 'integer',
        'isDecoyTarget' => 'boolean',
        'features' => 'array',
        'isActive' => 'boolean',
    ];

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'productId', 'ProductId');
    }
}
