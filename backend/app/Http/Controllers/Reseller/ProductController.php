<?php

declare(strict_types=1);

namespace App\Http\Controllers\Reseller;

use App\Models\Product;
use App\Support\ApiEnvelope;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Controller providing active platform products catalogue to Resellers.
 */
final class ProductController
{
    public function index(Request $request): JsonResponse
    {
        $products = Product::query()
            ->where('isActive', true)
            ->with(['deals' => function ($query) {
                $query->where('isActive', true)->select([
                    'ProductDealId',
                    'productId',
                    'dealName',
                    'dealCode',
                    'billingInterval',
                    'allowedMachineSeats',
                ]);
            }])
            ->select([
                'ProductId',
                'productName',
                'productSlug',
                'category',
                'description',
                'checkIntervalDays',
                'isActive',
            ])
            ->get();

        $requestId = (string) $request->attributes->get('X-Request-Id', '');

        return ApiEnvelope::success($products->toArray(), $requestId);
    }
}
