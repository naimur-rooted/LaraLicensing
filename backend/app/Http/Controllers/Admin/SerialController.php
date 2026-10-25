<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Exceptions\NotFoundException;
use App\Http\Controllers\Controller;
use App\Models\Serial;
use App\Support\ApiEnvelope;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class SerialController extends Controller
{
    public function show(Request $request, string $id): JsonResponse
    {
        $serial = Serial::query()->find($id);
        if ($serial === null) {
            throw NotFoundException::custom('SerialNotFound', 'Serial not found.');
        }

        return ApiEnvelope::success([
            'Id' => (int) $serial->getKey(),
            'SerialId' => (int) $serial->getKey(),
            'LicenseId' => (int) $serial->LicenseId,
            'Status' => $serial->IsRevoked ? 'revoked' : 'active',
        ], (string) ($request->headers->get('X-Request-Id') ?? ''));
    }
}
