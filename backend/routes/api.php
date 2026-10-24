<?php

declare(strict_types=1);

/*
 | Plan 06 steps 26 + 27 + 28 + 29.
 |
 | Route groups gated by RBAC, shard binding, or HMAC signature.
 | Controllers for the remaining Admin/Reseller/Portal surface land in
 | Plan 06 steps 30-42; this file wires the middleware skeleton plus
 | the first live Admin controller (Resellers) so end-to-end plumbing
 | (envelope + Idempotency-Key + ETag/If-Match) is provable now.
 |
 | References:
 |  - 02-spec/21-app/04-roles.md                     (RBAC surface)
 |  - 02-spec/23-app-db/10-reseller-shard-split-db.md (shard binding rule)
 |  - 02-spec/21-app/11-api-contracts/09-concurrency-control.md (If-Match)
 */

use App\Http\Controllers\Admin\BindingController as AdminBindingController;
use App\Http\Controllers\Admin\LicenseController;
use App\Http\Controllers\Admin\FeatureController;
use App\Http\Controllers\Admin\PrefixController;
use App\Http\Controllers\Admin\QuotaRequestController as AdminQuotaRequestController;
use App\Http\Controllers\Admin\ResellerController;
use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\Auth\CaptchaController;
use App\Http\Controllers\Auth\ForgotPasswordController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\LogoutController;
use App\Http\Controllers\Auth\RegisterController;
use App\Http\Controllers\Auth\ResetPasswordController;
use App\Http\Controllers\Auth\MeController;
use App\Http\Controllers\Public\HealthController;

use App\Http\Controllers\Reseller\LicenseController as ResellerLicenseController;
use App\Http\Controllers\Reseller\MachineBindingController as ResellerMachineBindingController;
use App\Http\Controllers\Reseller\PrefixController as ResellerPrefixController;
use App\Http\Controllers\Reseller\QuotaRequestController as ResellerQuotaRequestController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

// Plan 06 step 43 (login substrate) + Plan 09 login modernization.
// Auth surface: unauthenticated register/login/captcha, authenticated logout.
// AuthSessions writes live in Root DB (Kind = Normal). Register is the
// bootstrap-only path that creates the first SuperAdmin; once any Root user
// exists it returns AuthRegistrationClosed (403).
Route::middleware('rate.auth:register')->post('/Auth/Register', RegisterController::class)->name('api.auth.register');
Route::middleware('rate.auth:login')->post('/Auth/Login', LoginController::class)->name('api.auth.login');
Route::middleware('rate.auth:captcha')->get('/Auth/Captcha', CaptchaController::class)->name('api.auth.captcha');
Route::middleware('rate.auth:forgot')->post('/Auth/ForgotPassword', ForgotPasswordController::class)->name('api.auth.forgot_password');
Route::middleware('rate.auth:reset')->post('/Auth/ResetPassword', ResetPasswordController::class)->name('api.auth.reset_password');

Route::middleware(['auth:sanctum', 'session.active'])->post('/Auth/Logout', LogoutController::class)->name('api.auth.logout');
Route::middleware(['auth:sanctum', 'session.active'])->get('/Me/Capabilities', [MeController::class, 'capabilities'])->name('api.me.capabilities');

// v0.300.0. Public readiness probe for cPanel deploys and load balancers.
// Unauthenticated by design (see /api/public/* handbook). Root DB ping only;
// authenticated shard status lives on Admin\MetricsController::shardStatus.
Route::get('/Public/Health', HealthController::class)->name('api.public.health');



// Admin surface: SuperAdmin + Admin. No shard binding here; Admins act
// across resellers and explicitly select tenants per request.
Route::middleware(['auth:sanctum', 'session.active', 'require.role:Admin|SuperAdmin', 'casbin.pep'])
    ->prefix('Admin')
    ->name('api.admin.')
    ->group(function (): void {
        Route::get('/Ping', function (Request $request) {
            return response()->json([
                'Ok' => true,
                'Scope' => 'Admin',
                'UserId' => (string) $request->user()->getAuthIdentifier(),
            ]);
        })->name('ping');

        // Plan 06 step 29. Reseller directory CRUD on the Root DB.
        Route::get('/Resellers', [ResellerController::class, 'index'])->name('resellers.index');
        Route::post('/Resellers', [ResellerController::class, 'store'])->name('resellers.store');
        Route::get('/Resellers/{ResellerSlug}', [ResellerController::class, 'show'])->name('resellers.show');
        Route::patch('/Resellers/{ResellerSlug}', [ResellerController::class, 'update'])->name('resellers.update');

        // Plan 06 step 70. Feature catalog + TierFeatures matrix (Root, read-only).
        Route::get('/Features', [FeatureController::class, 'index'])->name('features.index');

        // Plan 06 step 30. Prefix registry on the Root DB.
        Route::get('/Prefixes', [PrefixController::class, 'index'])->name('prefixes.index');
        Route::post('/Prefixes', [PrefixController::class, 'store'])->name('prefixes.store');
        Route::delete('/Prefixes/{PrefixValue}', [PrefixController::class, 'destroy'])->name('prefixes.destroy');

        // Plan 06 steps 31-33. License CRUD on the reseller shard. Show/update/revoke
        // require ?ResellerSlug=... so the caller selects the shard explicitly.
        Route::post('/Licenses', [LicenseController::class, 'issue'])->name('licenses.issue');
        Route::get('/Licenses/{LicenseKey}', [LicenseController::class, 'show'])
            ->where('LicenseKey', '[A-Z0-9-]{4,80}')->name('licenses.show');
        Route::patch('/Licenses/{LicenseKey}', [LicenseController::class, 'update'])
            ->where('LicenseKey', '[A-Z0-9-]{4,80}')->name('licenses.update');
        // Plan 09 step 58. `destroy` is the REST-symmetric alias; it delegates
        // to `revoke()` so the audit action name stays `LicenseRevoked`.
        Route::delete('/Licenses/{LicenseKey}', [LicenseController::class, 'destroy'])
            ->where('LicenseKey', '[A-Z0-9-]{4,80}')->name('licenses.revoke');
        // Plan 06 step 48. LicenseLedger read (shard-scoped via ?ResellerSlug).
        Route::get('/Licenses/{LicenseKey}/Ledger', [LicenseController::class, 'ledger'])
            ->where('LicenseKey', '[A-Z0-9-]{4,80}')->name('licenses.ledger');

        // Plan 06 step 39 (admin bindings). Spec 30 Â§"Admin operations".
        // Shard bound via ?ResellerSlug=..., same pattern as other Admin routes.
        Route::get('/Licenses/{LicenseKey}/Bindings', [AdminBindingController::class, 'index'])
            ->where('LicenseKey', '[A-Z0-9-]{4,80}')->name('licenses.bindings.index');
        Route::post('/Licenses/{LicenseKey}/Bindings/{MachineBindingId}/Release', [AdminBindingController::class, 'release'])
            ->where(['LicenseKey' => '[A-Z0-9-]{4,80}', 'MachineBindingId' => '[0-9]+'])
            ->name('licenses.bindings.release');
        Route::post('/Licenses/{LicenseKey}/Bindings/{MachineBindingId}/ClearCooldown', [AdminBindingController::class, 'clearCooldown'])
            ->where(['LicenseKey' => '[A-Z0-9-]{4,80}', 'MachineBindingId' => '[0-9]+'])
            ->name('licenses.bindings.clear_cooldown');

        // Plan 06 step 34. User directory + role assignment on Root DB.
        Route::get('/Users', [UserController::class, 'index'])->name('users.index');
        Route::post('/Users', [UserController::class, 'store'])->name('users.store');
        Route::get('/Users/{UserId}', [UserController::class, 'show'])
            ->where('UserId', '[0-9]+')->name('users.show');
        Route::patch('/Users/{UserId}', [UserController::class, 'update'])
            ->where('UserId', '[0-9]+')->name('users.update');
        Route::get('/Users/{UserId}/Roles', [UserController::class, 'listRoles'])
            ->where('UserId', '[0-9]+')->name('users.roles.index');
        Route::post('/Users/{UserId}/Roles', [UserController::class, 'assignRole'])
            ->where('UserId', '[0-9]+')->name('users.roles.assign');
        Route::delete('/Users/{UserId}/Roles/{RoleName}', [UserController::class, 'revokeRole'])
            ->where(['UserId' => '[0-9]+', 'RoleName' => '[A-Za-z]{2,32}'])
            ->name('users.roles.revoke');
        Route::post('/Users/{UserId}/Impersonate', [UserController::class, 'impersonate'])
            ->where('UserId', '[0-9]+')->name('users.impersonate');
        Route::post('/Impersonation/End', [UserController::class, 'endImpersonation'])
            ->name('impersonation.end');
        // Plan 06 step 43 (forceEnd). Admin-only termination of any active
        // impersonation session by SessionId (spec 47 Â§2 AdminForced).
        Route::post('/Impersonation/{SessionId}/ForceEnd', [UserController::class, 'forceEndImpersonation'])
            ->where('SessionId', '[0-9a-fA-F-]{36}')
            ->name('impersonation.force_end');


        // Plan 06 step 37. Admin-scoped quota-request decision surface.
        // Shard is selected explicitly via ?ResellerSlug=... on each call.
        Route::get('/QuotaRequests', [AdminQuotaRequestController::class, 'index'])
            ->name('quota_requests.index');
        // Cross-tenant fanout inbox (spec 42 v1.1.0 Â§"Admin inbox").
        Route::get('/QuotaRequests/All', [AdminQuotaRequestController::class, 'indexAll'])
            ->name('quota_requests.index_all');
        Route::post('/QuotaRequests/{RequestId}/Approve', [AdminQuotaRequestController::class, 'approve'])
            ->where('RequestId', '[0-9]+')->name('quota_requests.approve');
        Route::post('/QuotaRequests/{RequestId}/Deny', [AdminQuotaRequestController::class, 'deny'])
            ->where('RequestId', '[0-9]+')->name('quota_requests.deny');

        // Plan 06 step 45. Self-update publish saga.
        // Phase 1 (v0.237.0): UploadTicket. Phase 3a/3c (v0.238.0):
        // finalize + yank. Idempotency-Key required (see
        // IdempotencyKeyMiddleware REQUIRED_PREFIXES `api/admin/appupdates`).
        Route::get('/AppUpdates', [\App\Http\Controllers\Admin\AppUpdateController::class, 'index'])
            ->name('app_updates.index');
        Route::post('/AppUpdates/UploadTicket', [\App\Http\Controllers\Admin\AppUpdateController::class, 'uploadTicket'])
            ->name('app_updates.upload_ticket');
        Route::post('/AppUpdates', [\App\Http\Controllers\Admin\AppUpdateController::class, 'publish'])
            ->name('app_updates.publish');
        Route::post('/AppUpdates/{Version}/Yank', [\App\Http\Controllers\Admin\AppUpdateController::class, 'yank'])
            ->where('Version', '[0-9]+\.[0-9]+\.[0-9]+([-+][0-9A-Za-z.\-]+)?')
            ->name('app_updates.yank');

        // Plan 09 step 29. Dashboard KPI aggregator (root counts + shard fanout).
        Route::get('/Metrics', [\App\Http\Controllers\Admin\MetricsController::class, 'index'])
            ->name('metrics.index');

        // Plan 09 step 31. Per-shard reachability probe for the dashboard "Recheck now" control.
        Route::get('/Metrics/ShardStatus', [\App\Http\Controllers\Admin\MetricsController::class, 'shardStatus'])
            ->name('metrics.shardStatus');

        // Plan 09 step 23. Read-only viewer over Root AuditLogs. Filters by
        // Action/TargetType/ActorId; ordered CreatedAt DESC; Limit<=500.
        Route::get('/AuditLogs', [\App\Http\Controllers\Admin\AuditController::class, 'index'])
            ->name('audit.index');

        // v0.298.0. Auth session listing per user + admin-forced revoke by SessionId.
        Route::get('/Users/{UserId}/Sessions', [\App\Http\Controllers\Admin\SessionController::class, 'index'])
            ->where('UserId', '[0-9]+')->name('users.sessions.index');
        Route::delete('/Sessions/{SessionId}', [\App\Http\Controllers\Admin\SessionController::class, 'destroy'])
            ->where('SessionId', '[0-9a-fA-F-]{36}')->name('sessions.destroy');

        // Plan 14 step 9 (v0.614.0). Backup/Restore Export endpoint (S1 shadow).
        // 02-spec/26-backup-restore/11-endpoint-export.md. Idempotency-Key required
        // (see IdempotencyKeyMiddleware REQUIRED_PREFIXES `api/admin/backup/exports`).
        Route::post('/Backup/Exports', [\App\Http\Controllers\Admin\BrExportController::class, 'store'])
            ->name('backup.exports.store');

        // Plan 14 step 27 (v0.676.0). Backup/Restore Import endpoint (S1 shadow,
        // verifyOnly slice). 02-spec/26-backup-restore/12-endpoint-import.md.
        // Idempotency-Key required (REQUIRED_PREFIXES `api/admin/backup/imports`).
        // verifyAndApply is rejected 400 ValidationFailed until a follow-up step
        // lands the apply-phase job dispatch.
        Route::post('/Backup/Imports', [\App\Http\Controllers\Admin\BrImportController::class, 'store'])
            ->name('backup.imports.store');

        // Plan 16 step 58 (v0.563.0). Runtime-config admin surface for
        // repo-root `version.json`. `show` returns ETag over UpdatedAt;
        // `update` enforces If-Match, atomic write, and SuperAdmin RBAC.
        // Contract: 02-spec/28-runtime-modes/05-admin-runtime-toggle.md.
        Route::get('/RuntimeConfig', [\App\Http\Controllers\Admin\RuntimeConfigController::class, 'show'])
            ->name('runtime_config.show');
        Route::put('/RuntimeConfig', [\App\Http\Controllers\Admin\RuntimeConfigController::class, 'update'])
            ->name('runtime_config.update');

        // Plan 18 Step 102. Admin Errors tail endpoint.
        Route::get('/Errors', [\App\Http\Controllers\Admin\ErrorController::class, 'index'])
            ->name('errors.index');
    });


// Reseller surface: Reseller role + automatic shard binding by TenantId.
Route::middleware(['auth:sanctum', 'session.active', 'require.role:Reseller', \App\Http\Middleware\ShardBindingMiddleware::class])
    ->prefix('Reseller')
    ->name('api.reseller.')
    ->group(function (): void {
        Route::get('/Ping', function (Request $request) {
            return response()->json([
                'Ok' => true,
                'Scope' => 'Reseller',
                'ResellerSlug' => (string) $request->attributes->get('ResellerSlug', ''),
            ]);
        })->name('ping');

        // Plan 24 Group 3. Reseller Vanity Prefixes.
        Route::get('/Prefixes', [\App\Http\Controllers\Reseller\PrefixController::class, 'index'])->name('prefixes.index');
        Route::post('/Prefixes', [\App\Http\Controllers\Reseller\PrefixController::class, 'store'])->name('prefixes.store');

        // Plan 06 step 35. Reseller-scoped License surface (shard-bound).
        Route::get('/Licenses', [ResellerLicenseController::class, 'index'])->name('licenses.index');
        // Plan 06 step 35b. Reseller-scoped License minting.
        Route::post('/Licenses', [ResellerLicenseController::class, 'issue'])->name('licenses.issue');
        Route::get('/Licenses/{LicenseKey}', [ResellerLicenseController::class, 'show'])
            ->where('LicenseKey', '[A-Z0-9-]{4,80}')->name('licenses.show');
        Route::patch('/Licenses/{LicenseKey}/Renew', [ResellerLicenseController::class, 'renew'])
            ->where('LicenseKey', '[A-Z0-9-]{4,80}')->name('licenses.renew');
        // Plan 06 step 48. LicenseLedger read (shard already bound).
        Route::get('/Licenses/{LicenseKey}/Ledger', [ResellerLicenseController::class, 'ledger'])
            ->where('LicenseKey', '[A-Z0-9-]{4,80}')->name('licenses.ledger');
        // Plan 24 Group 4. Reseller machine binding inspection and release.
        Route::get('/Licenses/{LicenseKey}/Bindings', [ResellerMachineBindingController::class, 'index'])
            ->where('LicenseKey', '[A-Z0-9-]{4,80}')->name('licenses.bindings.index');
        Route::post('/Licenses/{LicenseKey}/Bindings/{MachineBindingId}/Release', [ResellerMachineBindingController::class, 'release'])
            ->where(['LicenseKey' => '[A-Z0-9-]{4,80}', 'MachineBindingId' => '[0-9]+'])
            ->name('licenses.bindings.release');
        Route::post('/Licenses/{LicenseKey}/Bindings/{MachineBindingId}/ClearCooldown', [ResellerMachineBindingController::class, 'clearCooldown'])
            ->where(['LicenseKey' => '[A-Z0-9-]{4,80}', 'MachineBindingId' => '[0-9]+'])
            ->name('licenses.bindings.clear_cooldown');

        // Plan 06 step 36. Reseller-scoped quota-request workflow (shard-bound).
        // Approve/Deny live on the Admin surface per spec 42 Â§Endpoints.
        Route::get('/QuotaRequests', [ResellerQuotaRequestController::class, 'index'])
            ->name('quota_requests.index');
        Route::post('/QuotaRequests', [ResellerQuotaRequestController::class, 'store'])
            ->name('quota_requests.store');
        Route::post('/QuotaRequests/{RequestId}/Cancel', [ResellerQuotaRequestController::class, 'cancel'])
            ->where('RequestId', '[0-9]+')->name('quota_requests.cancel');
    });

// Plan 06 step 28. Portal surface: unauthenticated but HMAC-signed.
// No auth guard, no shard binding; individual controllers derive the
// target reseller from the signed KeyId or from request payload.
Route::middleware(['require.signature'])
    ->prefix('Portal')
    ->name('api.portal.')
    ->group(function (): void {
        Route::get('/Ping', function (Request $request) {
            return response()->json([
                'Ok' => true,
                'Scope' => 'Portal',
                'KeyId' => (string) $request->attributes->get('lara.signature.key_id', ''),
            ]);
        })->name('ping');

        // Plan 06 step 38. End-user device requests a Serial for a known
        // LicenseKey. HMAC-signed by SignedRequestMiddleware. Idempotent
        // by UNIQUE(LicenseId, DeviceIdHash) at the DB layer.
        Route::post('/Serials', [\App\Http\Controllers\Portal\SerialController::class, 'issue'])
            ->name('serials.issue');

        // Plan 06 step 39a. First of three verify handshake endpoints.
        // 39c (/Verify/Final) lands after TierFeatures (Plan 06 step 41)
        // and MachineBindings/UserBindings substrates.
        Route::post('/Verify/Serial', [\App\Http\Controllers\Portal\VerifyController::class, 'serial'])
            ->name('verify.serial');

        // Plan 06 step 39b. Second verify handshake endpoint. Mints a
        // single-use VerifyKey bound to SHA-256(HashKey) with 5-minute
        // TTL. MachineBindings persistence deferred to a later Plan 06
        // step; see VerifyController::hash() docblock for scope boundary.
        Route::post('/Verify/Hash', [\App\Http\Controllers\Portal\VerifyController::class, 'hash'])
            ->name('verify.hash');

        // Plan 06 step 39c. Third and final verify handshake endpoint.
        // Consumes the VerifyKey inside a shard transaction, runs the
        // environment gate INSIDE the same transaction per spec 03
        // Â§Transaction boundary, resolves the Features map via
        // FeatureService (LicenseFeatures > TierFeatures precedence),
        // and returns {IsAuthorized, LicenseId, EnvironmentId,
        // LicenseTierId, Features, AuthorizedAt, ExpiresAt}. Machine /
        // User binding writes remain deferred until the shard binding
        // tables land; response fields stay null (spec-legal, optional).
        Route::post('/Verify/Final', [\App\Http\Controllers\Portal\VerifyController::class, 'final'])
            ->name('verify.final');
    });

// Plan 09 / Reseller / Admin serial lookup with leaky-bucket rate limiting
Route::middleware(\App\Http\Middleware\ThrottleLicenseLookups::class)
    ->get('/Serials/{SerialValue}', function (string $serialValue) {
    $clean = trim(strtoupper($serialValue));
    $file = database_path('registered_serials.json');
    if (file_exists($file)) {
        $registry = json_decode((string) file_get_contents($file), true) ?: [];
        if (isset($registry[$clean])) {
            return response()->json([
                'IsSuccess' => true,
                'Data' => $registry[$clean],
            ]);
        }
    }

    try {
        $serial = \App\Models\Serial::where('SerialValue', $clean)->first();
        if ($serial) {
            return response()->json([
                'IsSuccess' => true,
                'Data' => [
                    'SerialId' => $serial->SerialId,
                    'LicenseId' => $serial->LicenseId,
                    'SerialValue' => $serial->SerialValue,
                    'IsRevoked' => (bool) $serial->IsRevoked,
                    'Category' => 'Subscription',
                    'CreatedAt' => $serial->CreatedAt ? $serial->CreatedAt->toISOString() : \Illuminate\Support\Carbon::now()->toISOString(),
                ],
            ]);
        }
    } catch (\Throwable $e) {
        // Shard not bound or DB unconfigured; gracefully fallback
    }

    return response()->json([
        'IsSuccess' => false,
        'ErrorCode' => 'SerialNotFound',
        'Message' => "Serial \"{$clean}\" not found.",
    ], 404);
})->where('SerialValue', '[A-Za-z0-9-]+');

Route::post('/Serials/Register', function (Request $request) {
    $serialValue = strtoupper(trim((string) $request->input('SerialValue', '')));
    if (empty($serialValue)) {
        return response()->json(['IsSuccess' => false, 'ErrorCode' => 'InvalidSerial', 'Message' => 'SerialValue is required.'], 422);
    }
    $file = database_path('registered_serials.json');
    $registry = [];
    if (file_exists($file)) {
        $registry = json_decode((string) file_get_contents($file), true) ?: [];
    }

    $category = (string) $request->input('Category', 'Monthly');
    $expiresAt = $request->input('ExpiresAt');
    if (!$expiresAt && $category !== 'Lifetime') {
        $days = match (strtolower($category)) {
            'daily' => 1,
            'weekly' => 7,
            'yearly' => 365,
            default => 30,
        };
        $expiresAt = \Illuminate\Support\Carbon::now()->addDays($days)->toISOString();
    }

    $record = [
        'SerialId' => crc32($serialValue),
        'SerialValue' => $serialValue,
        'Category' => $category,
        'TierName' => $request->input('TierName', 'Enterprise'),
        'CustomerName' => $request->input('CustomerName', 'Customer'),
        'CustomerEmail' => $request->input('CustomerEmail', ''),
        'Status' => $request->input('Status', 'active'),
        'ExpiresAt' => $expiresAt,
        'Features' => $request->input('Features', ['core.reports', 'core.dashboards', 'addon.exports']),
        'MaxActivations' => (int) $request->input('MaxActivations', 5),
        'IsRevoked' => (bool) $request->input('IsRevoked', false),
        'RegisteredAt' => \Illuminate\Support\Carbon::now()->toISOString(),
    ];

    $registry[$serialValue] = $record;
    file_put_contents($file, json_encode($registry, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

    return response()->json(['IsSuccess' => true, 'Data' => $record]);
});

Route::post('/Serials/Revoke', function (Request $request) {
    $serialValue = strtoupper(trim((string) $request->input('SerialValue', '')));
    if (empty($serialValue)) {
        return response()->json(['IsSuccess' => false, 'ErrorCode' => 'InvalidSerial', 'Message' => 'SerialValue is required.'], 422);
    }
    $file = database_path('registered_serials.json');
    $registry = [];
    if (file_exists($file)) {
        $registry = json_decode((string) file_get_contents($file), true) ?: [];
    }

    if (isset($registry[$serialValue])) {
        $registry[$serialValue]['Status'] = 'suspended';
        $registry[$serialValue]['IsRevoked'] = true;
        $registry[$serialValue]['RevokedAt'] = \Illuminate\Support\Carbon::now()->toISOString();
        file_put_contents($file, json_encode($registry, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        return response()->json(['IsSuccess' => true, 'Data' => $registry[$serialValue]]);
    }

    $record = [
        'SerialId' => crc32($serialValue),
        'SerialValue' => $serialValue,
        'Category' => 'Revoked',
        'Status' => 'suspended',
        'IsRevoked' => true,
        'RevokedAt' => \Illuminate\Support\Carbon::now()->toISOString(),
    ];
    $registry[$serialValue] = $record;
    file_put_contents($file, json_encode($registry, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

    return response()->json(['IsSuccess' => true, 'Data' => $record]);
});

Route::post('/Serials/BindDevice', function (Request $request) {
    $serialValue = strtoupper(trim((string) $request->input('SerialValue', '')));
    $digest = strtoupper(trim((string) ($request->input('HardwareDigest') ?? $request->input('DeviceId') ?? '')));
    $hostname = trim((string) $request->input('Hostname', 'Client PC'));
    $os = trim((string) $request->input('Os', 'Windows'));

    if (empty($serialValue) || empty($digest)) {
        return response()->json(['IsSuccess' => false, 'ErrorCode' => 'InvalidInput', 'Message' => 'SerialValue and HardwareDigest required.'], 422);
    }

    $file = database_path('registered_serials.json');
    $registry = file_exists($file) ? (json_decode((string) file_get_contents($file), true) ?: []) : [];

    if (!isset($registry[$serialValue])) {
        // Auto-register active key with 30-day default if unknown
        $registry[$serialValue] = [
            'SerialId' => crc32($serialValue),
            'SerialValue' => $serialValue,
            'Category' => 'Subscription',
            'TierName' => 'Standard',
            'Status' => 'active',
            'ExpiresAt' => \Illuminate\Support\Carbon::now()->addDays(30)->toISOString(),
            'MaxActivations' => 5,
            'IsRevoked' => false,
            'BoundDevices' => [],
        ];
    }

    $record = &$registry[$serialValue];
    if (!empty($record['IsRevoked']) || ($record['Status'] ?? '') === 'suspended') {
        return response()->json(['IsSuccess' => false, 'ErrorCode' => 'LicenseSuspended', 'Message' => 'License has been deactivated/revoked.'], 403);
    }

    $devices = $record['BoundDevices'] ?? [];
    $max = (int) ($record['MaxActivations'] ?? 5);

    // Check if device already bound
    $found = false;
    foreach ($devices as &$dev) {
        if (($dev['HardwareDigest'] ?? '') === $digest || ($dev['DeviceId'] ?? '') === $digest) {
            $dev['LastActiveAt'] = \Illuminate\Support\Carbon::now()->toISOString();
            $dev['Hostname'] = $hostname ?: ($dev['Hostname'] ?? 'PC');
            $dev['Os'] = $os ?: ($dev['Os'] ?? 'OS');
            $found = true;
            break;
        }
    }

    if (!$found) {
        if (count($devices) >= $max) {
            return response()->json([
                'IsSuccess' => false,
                'ErrorCode' => 'MaxActivationsReached',
                'Message' => "Maximum machine limit of {$max} reached. Please unbind an older device from your portal.",
                'BoundDevices' => $devices,
                'MaxActivations' => $max,
            ], 409);
        }

        $devices[] = [
            'DeviceId' => $digest,
            'HardwareDigest' => $digest,
            'Hostname' => $hostname,
            'Os' => $os,
            'ActivatedAt' => \Illuminate\Support\Carbon::now()->toISOString(),
            'LastActiveAt' => \Illuminate\Support\Carbon::now()->toISOString(),
        ];
    }

    $record['BoundDevices'] = $devices;
    file_put_contents($file, json_encode($registry, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

    return response()->json([
        'IsSuccess' => true,
        'Data' => [
            'SerialValue' => $serialValue,
            'BoundDevices' => $devices,
            'MaxActivations' => $max,
            'Status' => $record['Status'] ?? 'active',
        ],
    ]);
});

Route::post('/Serials/UnbindDevice', function (Request $request) {
    $serialValue = strtoupper(trim((string) $request->input('SerialValue', '')));
    $digest = strtoupper(trim((string) ($request->input('HardwareDigest') ?? $request->input('DeviceId') ?? '')));

    if (empty($serialValue) || empty($digest)) {
        return response()->json(['IsSuccess' => false, 'ErrorCode' => 'InvalidInput', 'Message' => 'SerialValue and DeviceId required.'], 422);
    }

    $file = database_path('registered_serials.json');
    $registry = file_exists($file) ? (json_decode((string) file_get_contents($file), true) ?: []) : [];

    if (!isset($registry[$serialValue])) {
        return response()->json(['IsSuccess' => false, 'ErrorCode' => 'SerialNotFound', 'Message' => 'Serial not found.'], 404);
    }

    $record = &$registry[$serialValue];
    $devices = $record['BoundDevices'] ?? [];

    $filtered = array_values(array_filter($devices, function ($dev) use ($digest) {
        return ($dev['HardwareDigest'] ?? '') !== $digest && ($dev['DeviceId'] ?? '') !== $digest;
    }));

    $record['BoundDevices'] = $filtered;
    file_put_contents($file, json_encode($registry, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

    return response()->json([
        'IsSuccess' => true,
        'Message' => 'Device released successfully.',
        'Data' => [
            'SerialValue' => $serialValue,
            'BoundDevices' => $filtered,
            'MaxActivations' => (int) ($record['MaxActivations'] ?? 5),
            'Status' => $record['Status'] ?? 'active',
        ],
    ]);
});

Route::post('/Serials/CancelRenewal', function (Request $request) {
    $serialValue = strtoupper(trim((string) $request->input('SerialValue', '')));
    if (empty($serialValue)) {
        return response()->json(['IsSuccess' => false, 'ErrorCode' => 'InvalidSerial', 'Message' => 'SerialValue is required.'], 422);
    }
    $file = database_path('registered_serials.json');
    $registry = file_exists($file) ? (json_decode((string) file_get_contents($file), true) ?: []) : [];

    if (isset($registry[$serialValue])) {
        $registry[$serialValue]['AutoRenew'] = false;
        $registry[$serialValue]['CancelAtPeriodEnd'] = true;
        file_put_contents($file, json_encode($registry, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        return response()->json(['IsSuccess' => true, 'Data' => $registry[$serialValue]]);
    }

    return response()->json(['IsSuccess' => false, 'ErrorCode' => 'SerialNotFound'], 404);
});

// Plan 06 steps 44 + 45. Self-update manifest read path per
// 02-spec/21-app/17-self-update-endpoint.md v1.3.0. Stable is anonymous
// (no auth guard). Beta is reserved and rejected in-controller with
// AuthzRoleDenied until the AppBuilder|Admin gate lands post-v1.0.
Route::get('/App/UpdateManifest', \App\Http\Controllers\App\UpdateManifestController::class)
    ->name('app.update_manifest');

// Plan 06 step 45. Self-update publish saga, phase 2 of 3. PUT receiver
// authenticated by the opaque `UploadToken` in the path (single-use,
// TTL-scoped). No Sanctum guard: the token IS the bearer credential.
Route::put('/App/UpdateAssetReceiver/{UploadToken}', \App\Http\Controllers\App\UpdateAssetReceiverController::class)
    ->where('UploadToken', '[a-f0-9]{64}')
    ->name('app.update_asset_receiver');

// Plan 06 step 45 (v0.238.0). Self-update byte-serving endpoint per
// spec 17 v1.3.0 Â§"HEAD /App/UpdateAsset/{Version}/{Platform}" and
// Â§"GET". Anonymous on Stable channel (v1.0). Only finalised and
// non-yanked rows visible; missing rows return UpdateAssetNotFound.
Route::match(['GET', 'HEAD'], '/App/UpdateAsset/{Version}/{Platform}', \App\Http\Controllers\App\UpdateAssetController::class)
    ->where(['Version' => '[0-9]+\.[0-9]+\.[0-9]+([-+][0-9A-Za-z.\-]+)?', 'Platform' => '[A-Za-z0-9]+'])
    ->name('app.update_asset');

// Plan 06 step 46 (v0.239.0). Ed25519 detached-signature endpoint per
// spec 17 v1.3.0 Â§"Signature verification" + Â§"Response 200" SignatureUrl.
// Anonymous on Stable channel (v1.0). Only finalised, non-yanked rows
// with a persisted signature blob are visible; otherwise 404
// UpdateSignatureUnavailable.
// Commercial Pricing Plans endpoint (Root DB)
Route::get('/Pricing/Plans', function () {
    $plans = \App\Models\PricingPlan::where('isActive', true)->get();
    return response()->json(['IsSuccess' => true, 'Data' => $plans]);
})->name('api.pricing.plans');

// Cryptographic Offline Token verification / generation endpoint
Route::post('/Serials/OfflineToken', function (\Illuminate\Http\Request $request, \App\Services\JwtTokenService $jwt) {
    $serial = strtoupper(trim((string) $request->input('SerialValue', '')));
    $hwid = strtoupper(trim((string) $request->input('HardwareDigest', '')));
    $tier = (string) $request->input('Tier', 'PLAN_2Y');
    $slots = (int) $request->input('AllowedSlots', 10);

    if (empty($serial) || empty($hwid)) {
        return response()->json(['IsSuccess' => false, 'ErrorCode' => 'InvalidInput', 'Message' => 'SerialValue and HardwareDigest required'], 422);
    }

    $token = $jwt->issueOfflineToken($serial, $hwid, $tier, $slots);
    return response()->json(['IsSuccess' => true, 'Data' => ['OfflineToken' => $token, 'SerialValue' => $serial, 'HardwareDigest' => $hwid]]);
})->name('api.serials.offline_token');

// Developer Portal Product Management & AI Integration Endpoints
// Public Deals API for Method 1 Headless Synchronization
Route::get('/v1/products/{slug}/deals', [\App\Http\Controllers\Developer\ProductController::class, 'publicDeals'])->name('api.products.deals.public');
Route::prefix('v1/developer')->group(function (): void {
    Route::get('/overview', [\App\Http\Controllers\Developer\DeveloperOverviewController::class, 'overview'])->name('api.developer.overview');
    Route::post('/ai/chat', [\App\Http\Controllers\Developer\DeveloperOverviewController::class, 'chat'])->name('api.developer.ai.chat');
    Route::get('/products', [\App\Http\Controllers\Developer\ProductController::class, 'index'])->name('api.developer.products.index');
    Route::post('/products', [\App\Http\Controllers\Developer\ProductController::class, 'store'])->name('api.developer.products.store');
    Route::get('/products/{slug}', [\App\Http\Controllers\Developer\ProductController::class, 'show'])->name('api.developer.products.show');
    Route::post('/products/{slug}/deals', [\App\Http\Controllers\Developer\ProductController::class, 'addDeal'])->name('api.developer.products.deals.store');
    Route::put('/products/{slug}/deals/{dealId}', [\App\Http\Controllers\Developer\ProductController::class, 'updateDeal'])->name('api.developer.products.deals.update');
    Route::delete('/products/{slug}/deals/{dealId}', [\App\Http\Controllers\Developer\ProductController::class, 'deleteDeal'])->name('api.developer.products.deals.destroy');
    Route::get('/products/{slug}/blueprint', [\App\Http\Controllers\Developer\ProductController::class, 'aiBlueprint'])->name('api.developer.products.blueprint');
    Route::post('/ai/design-pricing', [\App\Http\Controllers\Developer\AiArchitectController::class, 'designPricing'])->name('api.developer.ai.design_pricing');
    Route::post('/products/{slug}/apply-ai-deals', [\App\Http\Controllers\Developer\AiArchitectController::class, 'applyAiDeals'])->name('api.developer.products.apply_ai_deals');
    Route::post('/products/{slug}/blueprint-code', [\App\Http\Controllers\Developer\AiArchitectController::class, 'generateBlueprintCode'])->name('api.developer.products.blueprint_code');
});

// Decoupled Payment Gateway Webhook Callback (Zero card data stored)
Route::post('/v1/products/{slug}/licenses/issue', [\App\Http\Controllers\Developer\ProductController::class, 'webhookIssue'])
    ->name('api.products.licenses.issue');

// Multi-Gateway Decoupled Webhook Ingestion (Stripe, LemonSqueezy, WooCommerce)
Route::post('/v1/webhooks/stripe', [\App\Http\Controllers\Webhooks\PaymentWebhookController::class, 'handleStripe'])
    ->name('api.webhooks.stripe');
Route::post('/v1/webhooks/lemonsqueezy', [\App\Http\Controllers\Webhooks\PaymentWebhookController::class, 'handleLemonSqueezy'])
    ->name('api.webhooks.lemonsqueezy');
Route::post('/v1/webhooks/woocommerce', [\App\Http\Controllers\Webhooks\PaymentWebhookController::class, 'handleWooCommerce'])
    ->name('api.webhooks.woocommerce');

// Client License Handshake & Verification Endpoints (Periodic Cadence + Multi-Seat HWID)
Route::post('/v1/verify', [\App\Http\Controllers\Developer\AdvancedLicensingController::class, 'verifyClient'])
    ->name('api.v1.verify');
Route::post('/v1/activate', [\App\Http\Controllers\Developer\AdvancedLicensingController::class, 'activate'])
    ->name('api.v1.activate');
Route::post('/v1/deactivate', [\App\Http\Controllers\Developer\AdvancedLicensingController::class, 'deactivate'])
    ->name('api.v1.deactivate');
Route::post('/v1/heartbeat', [\App\Http\Controllers\Developer\AdvancedLicensingController::class, 'heartbeat'])
    ->name('api.v1.heartbeat');

Route::post('/v1/products/{slug}/verify', [\App\Http\Controllers\Developer\AdvancedLicensingController::class, 'verifyClient'])
    ->name('api.products.verify');
Route::post('/v1/products/{slug}/activate', [\App\Http\Controllers\Developer\AdvancedLicensingController::class, 'activate'])
    ->name('api.products.activate');
Route::post('/v1/products/{slug}/deactivate', [\App\Http\Controllers\Developer\AdvancedLicensingController::class, 'deactivate'])
    ->name('api.products.deactivate');
Route::post('/v1/products/{slug}/heartbeat', [\App\Http\Controllers\Developer\AdvancedLicensingController::class, 'heartbeat'])
    ->name('api.products.heartbeat');

// Reseller Wholesale Quotas & Custom Offers Endpoints
Route::prefix('v1/reseller-wholesale/{resellerSlug}')->group(function (): void {
    Route::get('/overview', [\App\Http\Controllers\Developer\AdvancedLicensingController::class, 'resellerOverview'])->name('api.reseller_wholesale.overview');
    Route::post('/quota', [\App\Http\Controllers\Developer\AdvancedLicensingController::class, 'allocateQuota'])->name('api.reseller_wholesale.quota');
    Route::post('/offers', [\App\Http\Controllers\Developer\AdvancedLicensingController::class, 'createResellerOffer'])->name('api.reseller_wholesale.offers');
});

// Fraud Alerts, Impossible Travel & 1-Click Quarantine Endpoints
Route::prefix('v1/security/{productSlug}')->group(function (): void {
    Route::get('/events', [\App\Http\Controllers\Developer\AdvancedLicensingController::class, 'securityEvents'])->name('api.security.events');
    Route::post('/quarantine', [\App\Http\Controllers\Developer\AdvancedLicensingController::class, 'quarantine'])->name('api.security.quarantine');
    Route::post('/unbind-hardware', [\App\Http\Controllers\Developer\AdvancedLicensingController::class, 'unbindHardware'])->name('api.security.unbind_hardware');
});

// Reseller Portal Domain Services & API Endpoints
Route::middleware([\App\Http\Middleware\ResellerAuthOrPreview::class])
    ->prefix('v1/reseller')
    ->name('api.v1.reseller.')
    ->group(function (): void {
        // Reseller Vanity Prefixes
        Route::get('/prefixes', [ResellerPrefixController::class, 'index'])->name('prefixes.index');
        Route::post('/prefixes', [ResellerPrefixController::class, 'store'])->name('prefixes.store');

        // Branded Serial Minting & Licenses
        Route::get('/licenses', [ResellerLicenseController::class, 'index'])->name('licenses.index');
        Route::post('/licenses', [ResellerLicenseController::class, 'store'])->name('licenses.store');
        Route::get('/licenses/{licenseKey}', [ResellerLicenseController::class, 'show'])->name('licenses.show');
        Route::post('/licenses/{licenseKey}/revoke', [ResellerLicenseController::class, 'revoke'])->name('licenses.revoke');
        Route::delete('/licenses/{licenseKey}', [ResellerLicenseController::class, 'destroy'])->name('licenses.destroy');
        Route::post('/licenses/{licenseKey}/renew', [ResellerLicenseController::class, 'renew'])->name('licenses.renew');
        Route::patch('/licenses/{licenseKey}/renew', [ResellerLicenseController::class, 'renew'])->name('licenses.patch_renew');
        Route::get('/licenses/{licenseKey}/ledger', [ResellerLicenseController::class, 'ledger'])->name('licenses.ledger');

        // Downstream Machine Bindings & Troubleshooting
        Route::get('/licenses/{licenseKey}/bindings', [ResellerMachineBindingController::class, 'index'])->name('licenses.bindings.index');
        Route::post('/licenses/{licenseKey}/bindings/{bindingId}/release', [ResellerMachineBindingController::class, 'release'])->name('licenses.bindings.release');
        Route::post('/licenses/{licenseKey}/bindings/{bindingId}/clear-cooldown', [ResellerMachineBindingController::class, 'clearCooldown'])->name('licenses.bindings.clear_cooldown');

        // Quotas & Ledger Overview
        Route::get('/quotas', [ResellerLicenseController::class, 'quotas'])->name('quotas.index');
    });

