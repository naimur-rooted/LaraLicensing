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

    // Never serialize the private key or webhook secret in API responses.
    // ProductController returns the model directly, so without this the
    // private key and webhook secret would leak to any caller of show/index.
    protected $hidden = [
        'ed25519PrivateKey',
        'webhookSecret',
    ];

    public function deals(): HasMany
    {
        return $this->hasMany(ProductDeal::class, 'productId', 'ProductId');
    }
}
