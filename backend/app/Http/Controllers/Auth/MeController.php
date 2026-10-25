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

        $me = [
            'UserId' => (int) $user->getKey(),
            'Id' => (int) $user->getKey(),
            'Email' => (string) $user->Email,
            'Role' => $user->getPrimaryRole(),
            'Capabilities' => $user->getCapabilities(),
        ];
        $me = $this->attachImpersonation($user, $me);

        return ApiEnvelope::success(['Me' => $me], $requestId);
    }

    private function attachImpersonation(User $user, array $me): array
    {
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
        $userId = (string) $request->user()->getAuthIdentifier();
        
        // In a real environment with lauthz, getImplicitPermissionsForUser 
        // retrieves all permissions the user has, including inherited ones.
        // For the FE capability mirror, Casbin seed rows use "Capability:*" objects.
        $permissions = Enforcer::getImplicitPermissionsForUser($userId);
        
        $capabilities = [];
        foreach ($permissions as $p) {
            // p is typically [sub, obj, act, eft];
            if (isset($p[1]) && str_starts_with($p[1], 'Capability:')) {
                // If the effect is deny, we might need to handle it, but for now 
                // the FE just needs a list of allowed capabilities.
                // In a robust implementation, we would evaluate each capability string.
                if (!isset($p[3]) || $p[3] === 'allow') {
                    $capabilities[] = str_replace('Capability:', '', $p[1]);
                }
            }
        }

        return response()->json([
            'Capabilities' => array_values(array_unique($capabilities)),
        ]);
    }
}
