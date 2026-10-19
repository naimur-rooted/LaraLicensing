<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Eloquent model for Products table in Root DB.
 */
class Product extends Model
{
    protected $connection = 'root';
    protected $table = 'Products';
    protected $primaryKey = 'ProductId';

    protected $fillable = [
        'userId',
        'productSlug',
        'productName',
        'category',
        'description',
        'ed25519PublicKey',
        'ed25519PrivateKey',
        'webhookSecret',
        'webhookUrl',
        'checkIntervalDays',
        'isActive',
    ];

    protected $casts = [
        'checkIntervalDays' => 'integer',
        'isActive' => 'boolean',
    ];

    public function deals(): HasMany
    {
        return $this->hasMany(ProductDeal::class, 'productId', 'ProductId');
    }
}
