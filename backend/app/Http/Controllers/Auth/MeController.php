<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\ImpersonationIndex;
use App\Models\User;
use App\Support\ApiEnvelope;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Lauthz\Facades\Enforcer;

class MeController extends Controller
{
    public function show(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $requestId = (string) $request->attributes->get('X-Request-Id', '');
        $roleName = $this->resolveRoleName($user);
        $me = $this->composeMeData($user, $roleName);

        if ($request->is('*Users/Me*', '*users/me*')) {
            return ApiEnvelope::success([$me], $requestId);
        }

        return ApiEnvelope::success(['Me' => $me], $requestId);
    }

    private function resolveRoleName(User $user): string
    {
        $roleName = $user->getPrimaryRole() ?? 'EndUser';
        $isDeveloper = $roleName === 'Developer' || $roleName === 'AppBuilder';

        return $isDeveloper ? 'EndUser' : $roleName;
    }

    private function composeMeData(User $user, string $roleName): array
    {
        $displayName = (string) ($user->DisplayName ?? $user->Name ?? explode('@', (string) $user->Email)[0]);
        $me = [
            'UserId' => (int) $user->getKey(),
            'Id' => (int) $user->getKey(),
            'Email' => (string) $user->Email,
            'Role' => $user->getPrimaryRole() ?? 'EndUser',
            'RoleName' => $roleName,
            'ResellerId' => ($user->ResellerId ?? $user->TenantId) ? (int) ($user->ResellerId ?? $user->TenantId) : null,
            'DisplayName' => $displayName,
            'Capabilities' => $user->getCapabilities(),
        ];

        return $this->attachImpersonation($user, $me);
    }

    private function attachImpersonation(User $user, array $me): array
    {
        try {
            $active = ImpersonationIndex::query()
                ->where('ImpersonatorUserId', (int) $user->getKey())
                ->whereNull('EndedAt')
                ->latest('StartedAt')
                ->first();

            if ($active !== null) {
                $me['Impersonation'] = [
                    'TargetUserId' => (int) $active->TargetUserId,
                    'SessionId' => (string) $active->SessionId,
                ];
            }
        } catch (\Throwable) {
            // Defensive: if ImpersonationIndex table does not exist or fails, do not crash Me
        }

        return $me;
    }

    /**
     * Return the Casbin capabilities for the authenticated user.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function capabilities(Request $request): JsonResponse
    {
        $user = $request->user();
        $userId = (string) $user->getAuthIdentifier();

        $capabilities = [];

        if (class_exists(Enforcer::class)) {
            // In an environment with lauthz, getImplicitPermissionsForUser 
            // retrieves all permissions the user has, including inherited ones.
            // For the FE capability mirror, Casbin seed rows use "Capability:*" objects.
            $permissions = Enforcer::getImplicitPermissionsForUser($userId);
            
            foreach ($permissions as $p) {
                // p is typically [sub, obj, act, eft];
                if (isset($p[1]) && str_starts_with($p[1], 'Capability:')) {
                    if (!isset($p[3]) || $p[3] === 'allow') {
                        $capabilities[] = str_replace('Capability:', '', $p[1]);
                    }
                }
            }
        }

        if (empty($capabilities)) {
            $capabilities = method_exists($user, 'getCapabilities') ? $user->getCapabilities() : ['*'];
        }

        $requestId = (string) ($request->attributes->get('RequestId') ?? 'req-' . bin2hex(random_bytes(8)));

        return ApiEnvelope::success([
            ['Capabilities' => array_values(array_unique($capabilities))],
        ], $requestId);
    }
}
