<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Support\ApiEnvelope;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

final class ResellerApplicationController
{
    /**
     * Public endpoint: submit a new reseller partner application.
     */
    public function submitPublic(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'CompanyName' => ['required', 'string', 'min:2', 'max:120'],
            'ContactEmail' => ['required', 'email', 'max:255'],
            'Website' => ['nullable', 'string', 'max:255'],
            'DesiredPrefix' => ['nullable', 'string', 'max:16'],
            'ExpectedVolume' => ['nullable', 'string', 'max:64'],
        ]);

        if ($validator->fails()) {
            return ApiEnvelope::failure(
                errorCode: 'ValidationFailed',
                errorMessage: 'Invalid application submission details.',
                httpStatus: 422,
                details: $validator->errors()->toArray(),
                requestId: (string) $request->attributes->get('X-Request-Id', '')
            );
        }

        $validated = $validator->validated();

        $appId = DB::connection('root')->table('ResellerApplications')->insertGetId([
            'CompanyName' => trim($validated['CompanyName']),
            'ContactEmail' => trim(strtolower($validated['ContactEmail'])),
            'Website' => $validated['Website'] ?? null,
            'DesiredPrefix' => !empty($validated['DesiredPrefix']) ? strtoupper(trim($validated['DesiredPrefix'])) : null,
            'ExpectedVolume' => $validated['ExpectedVolume'] ?? '100-500',
            'Status' => 'Pending',
            'CreatedAt' => now(),
            'UpdatedAt' => now(),
        ]);

        $created = DB::connection('root')->table('ResellerApplications')
            ->where('ApplicationId', $appId)
            ->first();

        return ApiEnvelope::success(
            results: [(array) $created],
            requestId: (string) $request->attributes->get('X-Request-Id', '')
        );
    }

    /**
     * Admin endpoint: list all pending and historical partner applications.
     */
    public function indexAdmin(Request $request): JsonResponse
    {
        $rows = DB::connection('root')->table('ResellerApplications')
            ->orderByDesc('ApplicationId')
            ->get();

        return ApiEnvelope::success(
            results: $rows->map(fn ($r) => (array) $r)->all(),
            requestId: (string) $request->attributes->get('X-Request-Id', '')
        );
    }

    /**
     * Admin endpoint: approve a pending application and automatically provision a Reseller.
     */
    public function approveAdmin(Request $request, int $applicationId): JsonResponse
    {
        $app = DB::connection('root')->table('ResellerApplications')
            ->where('ApplicationId', $applicationId)
            ->first();

        if (!$app) {
            return ApiEnvelope::failure(
                errorCode: 'NotFound',
                errorMessage: 'Application not found.',
                httpStatus: 404,
                requestId: (string) $request->attributes->get('X-Request-Id', '')
            );
        }

        // Generate a clean slug
        $baseSlug = strtolower(preg_replace('/[^a-zA-Z0-9]+/', '-', (string) $app->CompanyName));
        $baseSlug = trim($baseSlug, '-');
        if (empty($baseSlug)) {
            $baseSlug = 'reseller-' . $applicationId;
        }

        $resellerSlug = $baseSlug;
        $counter = 1;
        while (DB::connection('root')->table('Resellers')->where('ResellerSlug', $resellerSlug)->exists()) {
            $resellerSlug = $baseSlug . '-' . $counter;
            $counter++;
        }

        $adminUserId = (int) ($request->user()?->getAuthIdentifier() ?? 1);
        $temporaryPassword = 'Reseller-' . Str::upper(Str::random(4)) . '-' . rand(1000, 9999);

        // Transactional insert to Resellers, User creation, and update Application
        $resellerId = DB::connection('root')->transaction(function () use ($app, $resellerSlug, $applicationId, $adminUserId, $temporaryPassword) {
            $rId = DB::connection('root')->table('Resellers')->insertGetId([
                'ResellerName' => $app->CompanyName,
                'ResellerSlug' => $resellerSlug,
                'ContactEmail' => $app->ContactEmail,
                'IsActive' => 1,
                'CreatedAt' => now(),
                'UpdatedAt' => now(),
            ]);

            // Create or update portal user login for the Reseller
            $existingUser = DB::connection('root')->table('Users')->where('Email', $app->ContactEmail)->first();
            if ($existingUser) {
                DB::connection('root')->table('Users')->where('UserId', $existingUser->UserId)->update([
                    'TenantId' => $rId,
                    'PasswordHash' => Hash::make($temporaryPassword),
                    'IsActive' => 1,
                    'UpdatedAt' => now(),
                ]);
                $userId = $existingUser->UserId;
            } else {
                $userId = DB::connection('root')->table('Users')->insertGetId([
                    'Email' => $app->ContactEmail,
                    'PasswordHash' => Hash::make($temporaryPassword),
                    'TenantId' => $rId,
                    'IsActive' => 1,
                    'CreatedAt' => now(),
                    'UpdatedAt' => now(),
                ]);
            }

            // Assign Reseller role (RoleId: 3)
            $hasRole = DB::connection('root')->table('UserRoles')
                ->where('UserId', $userId)
                ->where('RoleId', 3)
                ->exists();
            if (!$hasRole) {
                DB::connection('root')->table('UserRoles')->insert([
                    'UserId' => $userId,
                    'RoleId' => 3,
                    'CreatedAt' => now(),
                ]);
            }

            // Add requested prefix if valid
            $prefix = !empty($app->DesiredPrefix) ? strtoupper($app->DesiredPrefix) : strtoupper(substr($resellerSlug, 0, 5));
            DB::connection('root')->table('Prefixes')->insert([
                'ResellerId' => $rId,
                'PrefixValue' => $prefix,
                'IsActive' => 1,
                'CreatedAt' => now(),
                'UpdatedAt' => now(),
            ]);

            // Initialize baseline Reseller Quota (500 starter pool)
            DB::connection('root')->table('ResellerQuotas')->insert([
                'ResellerId' => $rId,
                'LicenseCategoryId' => 3, // Commercial category
                'LicenseTierId' => 1,     // Starter tier
                'LicensesGranted' => 500,
                'LicensesConsumed' => 0,
                'PeriodStart' => now(),
                'CreatedAt' => now(),
                'UpdatedAt' => now(),
            ]);

            // Update application status with generated temporary password
            DB::connection('root')->table('ResellerApplications')
                ->where('ApplicationId', $applicationId)
                ->update([
                    'Status' => 'Approved',
                    'TemporaryPassword' => $temporaryPassword,
                    'DecidedByUserId' => $adminUserId,
                    'DecidedAt' => now(),
                    'UpdatedAt' => now(),
                ]);

            return $rId;
        });

        $reseller = DB::connection('root')->table('Resellers')->where('ResellerId', $resellerId)->first();
        $resellerArray = (array) $reseller;
        $resellerArray['TemporaryPassword'] = $temporaryPassword;
        $resellerArray['LoginEmail'] = $app->ContactEmail;

        return ApiEnvelope::success(
            results: [$resellerArray],
            requestId: (string) $request->attributes->get('X-Request-Id', '')
        );
    }

    /**
     * Admin endpoint: deny an application.
     */
    public function denyAdmin(Request $request, int $applicationId): JsonResponse
    {
        $app = DB::connection('root')->table('ResellerApplications')
            ->where('ApplicationId', $applicationId)
            ->first();

        if (!$app) {
            return ApiEnvelope::failure(
                errorCode: 'NotFound',
                errorMessage: 'Application not found.',
                httpStatus: 404,
                requestId: (string) $request->attributes->get('X-Request-Id', '')
            );
        }

        $adminUserId = (int) ($request->user()?->getAuthIdentifier() ?? 1);

        DB::connection('root')->table('ResellerApplications')
            ->where('ApplicationId', $applicationId)
            ->update([
                'Status' => 'Denied',
                'DecidedByUserId' => $adminUserId,
                'DecidedAt' => now(),
                'UpdatedAt' => now(),
            ]);

        $updated = DB::connection('root')->table('ResellerApplications')
            ->where('ApplicationId', $applicationId)
            ->first();

        return ApiEnvelope::success(
            results: [(array) $updated],
            requestId: (string) $request->attributes->get('X-Request-Id', '')
        );
    }
}
