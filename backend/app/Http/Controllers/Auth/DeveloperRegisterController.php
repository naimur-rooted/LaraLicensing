<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth;

use App\Exceptions\AuthException;
use App\Exceptions\DomainConflictException;
use App\Http\Requests\Auth\DeveloperRegisterRequest;
use App\Models\User;
use App\Services\AuthSessionService;
use App\Support\ApiEnvelope;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;

/**
 * Public developer registration endpoint: POST /api/v1/auth/developer-register.
 */
final class DeveloperRegisterController
{
    private const ROOT_CONNECTION = 'root';
    private const USERS_TABLE = 'Users';
    private const USER_ROLES_TABLE = 'UserRoles';

    public function __construct(private readonly AuthSessionService $sessions)
    {
    }

    public function __invoke(DeveloperRegisterRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $email = strtolower(trim((string) $validated['Email']));
        $password = (string) $validated['Password'];
        $plan = strtoupper((string) ($validated['SelectedPlan'] ?? 'STARTER'));
        $interval = (string) ($validated['BillingInterval'] ?? 'annual');
        $requestId = (string) $request->attributes->get('X-Request-Id', '');

        $this->assertEmailIsAvailable($email, $requestId);
        $user = $this->provisionDeveloperUser($email, $password, $requestId);
        $session = $this->sessions->openNormal($user);
        $token = $user->createToken($session->SessionId, ['*'], $session->ExpiresAt)->plainTextToken;

        return $this->buildSuccessResponse($user, $session, $token, $plan, $interval, $requestId);
    }

    private function assertEmailIsAvailable(string $email, string $requestId): void
    {
        $exists = DB::connection(self::ROOT_CONNECTION)
            ->table(self::USERS_TABLE)
            ->whereRaw('LOWER("Email") = ?', [$email])
            ->exists();

        if ($exists) {
            Log::warning('auth.developer_register.conflict', ['RequestId' => $requestId, 'Email' => $email]);
            throw DomainConflictException::conflict(
                'AuthUserConflict',
                'An account with this email address already exists.',
                [['Field' => 'Email', 'Rule' => 'Unique', 'Value' => $email]]
            );
        }
    }

    private function provisionDeveloperUser(string $email, string $password, string $requestId): User
    {
        return DB::connection(self::ROOT_CONNECTION)->transaction(function () use ($email, $password, $requestId): User {
            $now = Carbon::now();
            $user = new User();
            $user->setConnection(self::ROOT_CONNECTION);
            $user->Email = $email;
            $user->PasswordHash = Hash::make($password);
            $user->TenantId = null;
            $user->IsActive = true;
            $user->CreatedAt = $now;
            $user->UpdatedAt = $now;
            $user->save();

            $this->assignDeveloperRole((int) $user->getKey(), $now);
            Log::info('auth.developer_register.created', ['RequestId' => $requestId, 'UserId' => (int) $user->getKey()]);

            return $user;
        });
    }

    private function assignDeveloperRole(int $userId, Carbon $now): void
    {
        $roleId = DB::connection(self::ROOT_CONNECTION)
            ->table('Roles')
            ->whereIn('RoleName', ['AppBuilder', 'Developer'])
            ->value('RoleId');

        if ($roleId !== null && (int) $roleId > 0) {
            DB::connection(self::ROOT_CONNECTION)->table(self::USER_ROLES_TABLE)->insert([
                'UserId' => $userId,
                'RoleId' => (int) $roleId,
                'CreatedAt' => $now,
            ]);
        }
    }

    private function resolvePlanCapabilities(string $plan): array
    {
        return match ($plan) {
            'MAX' => ['planName' => 'Max Enterprise', 'maxProducts' => 50, 'dailyQuota' => 5000, 'hourlyQuota' => 500, 'seats' => 999],
            'PRO' => ['planName' => 'Pro Developer', 'maxProducts' => 15, 'dailyQuota' => 1000, 'hourlyQuota' => 120, 'seats' => 15],
            'ESSENTIAL' => ['planName' => 'Essential', 'maxProducts' => 3, 'dailyQuota' => 200, 'hourlyQuota' => 25, 'seats' => 5],
            default => ['planName' => 'Starter / Free', 'maxProducts' => 1, 'dailyQuota' => 100, 'hourlyQuota' => 15, 'seats' => 2],
        };
    }

    private function buildSuccessResponse(
        User $user,
        object $session,
        string $token,
        string $plan,
        string $interval,
        string $requestId
    ): JsonResponse {
        $meta = $this->resolvePlanCapabilities($plan);

        return ApiEnvelope::success(
            results: [[
                'UserId' => (int) $user->getKey(),
                'Email' => (string) $user->Email,
                'Role' => 'Developer',
                'SessionId' => $session->SessionId,
                'ExpiresAt' => $session->ExpiresAt->toIso8601String(),
                'Token' => $token,
                'Subscription' => [
                    'PlanCode' => $plan,
                    'PlanName' => $meta['planName'],
                    'BillingInterval' => $interval,
                    'MaxProducts' => $meta['maxProducts'],
                    'DailyQuota' => $meta['dailyQuota'],
                    'HourlyQuota' => $meta['hourlyQuota'],
                    'MaxMachineSeats' => $meta['seats'],
                    'Status' => 'active',
                ],
            ]],
            requestId: $requestId,
            httpCode: 201,
            message: 'Created',
        );
    }
}
