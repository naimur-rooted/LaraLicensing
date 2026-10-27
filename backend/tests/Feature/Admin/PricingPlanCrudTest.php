<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Models\AuthSession;
use App\Models\PricingPlan;
use App\Models\User;
use App\Policies\HasRolePolicy;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Ramsey\Uuid\Uuid;
use Tests\Support\AssertsLaraException;
use Tests\TestCase;

/**
 * Admin PricingPlans CRUD (B7).
 *
 * Previously only a public read path existed (GET /Pricing/Plans, active
 * plans only). This locks the admin surface inside the existing Admin
 * group: bare -> 401, non-Admin -> 403, create -> 201, duplicate planCode
 * -> 400, negative price -> 400, update -> 200, delete -> 200 and row gone.
 */
final class PricingPlanCrudTest extends TestCase
{
    use AssertsLaraException;

    private const ADMIN_EMAIL = 'admin-pricing@example.test';
    private const PASSWORD = 'CorrectHorse!Battery42';

    private User $admin;
    private string $adminBearer;

    protected function setUp(): void
    {
        parent::setUp();
        $this->createRootFixtures();
        $this->swapRolePolicy();
        $this->admin = $this->makeUser(self::ADMIN_EMAIL);
        [, $this->adminBearer] = $this->openSessionAndMintToken($this->admin);
    }

    public function test_bare_returns_401(): void
    {
        $res = $this->postJson('/Api/Admin/PricingPlans', $this->validBody());
        $this->assertLaraException($res, 'AuthUnauthorized', 401);
    }

    public function test_non_admin_returns_403(): void
    {
        \Tests\Feature\FakeHasRolePolicy::$grants = [(string) $this->admin->getKey() => ['Reseller']];
        $res = $this->authed()->postJson('/Api/Admin/PricingPlans', $this->validBody());
        $this->assertLaraException($res, 'AuthForbidden', 403);
    }

    public function test_admin_crud_round_trip(): void
    {
        $this->grantAdmin();
        $code = 'MUSE_TEST_' . time();

        $create = $this->authed()->postJson('/Api/Admin/PricingPlans', $this->validBody($code));
        $create->assertStatus(201);
        $id = (int) $create->json('Results.Item.PricingPlanId');
        $this->assertGreaterThan(0, $id);

        $show = $this->authed()->getJson("/Api/Admin/PricingPlans/{$id}");
        $show->assertStatus(200);
        $this->assertSame($code, $show->json('Results.Item.planCode'));

        $update = $this->authed()->patchJson("/Api/Admin/PricingPlans/{$id}", ['retailPriceCents' => 9900]);
        $update->assertStatus(200);
        $this->assertSame(9900, $update->json('Results.Item.retailPriceCents'));

        $delete = $this->authed()->deleteJson("/Api/Admin/PricingPlans/{$id}");
        $delete->assertStatus(200);
        $this->assertNull(PricingPlan::query()->where('PricingPlanId', $id)->first());
    }

    public function test_duplicate_plan_code_returns_400(): void
    {
        $this->grantAdmin();
        $code = 'MUSE_DUP_' . time();
        $this->authed()->postJson('/Api/Admin/PricingPlans', $this->validBody($code))->assertStatus(201);
        $res = $this->authed()->postJson('/Api/Admin/PricingPlans', $this->validBody($code));
        $this->assertLaraException($res, 'ValidationInputInvalid', 400);
    }

    public function test_negative_price_returns_400(): void
    {
        $this->grantAdmin();
        $body = $this->validBody('MUSE_NEG_' . time());
        $body['retailPriceCents'] = -100;
        $res = $this->authed()->postJson('/Api/Admin/PricingPlans', $body);
        $this->assertLaraException($res, 'ValidationInputInvalid', 400);
    }

    private function authed(): self
    {
        return $this->withHeader('Authorization', 'Bearer ' . $this->adminBearer);
    }

    private function grantAdmin(): void
    {
        \Tests\Feature\FakeHasRolePolicy::$grants = [(string) $this->admin->getKey() => ['Admin']];
    }

    /** @return array<string,mixed> */
    private function validBody(string $code = 'MUSE_PLAN'): array
    {
        return [
            'planCode' => $code,
            'planName' => 'Muse Test Plan',
            'billingInterval' => 'monthly',
            'retailPriceCents' => 4900,
            'discountedPriceCents' => 3900,
            'allowedMachineSlots' => 2,
            'isBestValue' => false,
            'isActive' => true,
        ];
    }

    private function swapRolePolicy(): void
    {
        require_once __DIR__ . '/../RoleGateTest.php';
        \Tests\Feature\FakeHasRolePolicy::$grants = [];
        $this->app->instance(HasRolePolicy::class, new \Tests\Feature\FakeHasRolePolicy());
    }

    private function makeUser(string $email): User
    {
        $user = new User();
        $user->Email = $email;
        $user->PasswordHash = Hash::make(self::PASSWORD);
        $user->TenantId = null;
        $user->IsActive = true;
        $user->save();

        return $user->refresh();
    }

    /** @return array{0:string,1:string} */
    private function openSessionAndMintToken(User $user): array
    {
        $sessionId = Uuid::uuid4()->toString();
        $now = Carbon::now();
        $row = new AuthSession();
        $row->SessionId = $sessionId;
        $row->UserId = (int) $user->getKey();
        $row->Kind = AuthSession::KIND_NORMAL;
        $row->ImpersonatorUserId = null;
        $row->ParentSessionId = null;
        $row->CreatedAt = $now;
        $row->ExpiresAt = $now->copy()->addMinutes(60);
        $row->EndedAt = null;
        $row->RevokeReason = null;
        $row->save();
        $token = $user->createToken($sessionId);

        return [$sessionId, $token->plainTextToken];
    }

    private function createRootFixtures(): void
    {
        $root = DB::connection('root');
        $root->statement('CREATE TABLE IF NOT EXISTS "Users" (
            "UserId" INTEGER PRIMARY KEY AUTOINCREMENT,
            "Email" TEXT NOT NULL UNIQUE,
            "PasswordHash" TEXT NOT NULL,
            "TenantId" INTEGER NULL,
            "IsActive" INTEGER NOT NULL DEFAULT 1,
            "CreatedAt" TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
            "UpdatedAt" TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
            "DeletedAt" TEXT NULL
        )');
        $root->statement('CREATE TABLE IF NOT EXISTS "AuthSessions" (
            "SessionId" TEXT PRIMARY KEY,
            "UserId" INTEGER NOT NULL,
            "Kind" TEXT NOT NULL,
            "ImpersonatorUserId" INTEGER NULL,
            "ParentSessionId" TEXT NULL,
            "CreatedAt" TEXT NOT NULL,
            "ExpiresAt" TEXT NOT NULL,
            "EndedAt" TEXT NULL,
            "RevokeReason" TEXT NULL
        )');
        $root->statement('CREATE TABLE IF NOT EXISTS "personal_access_tokens" (
            "id" INTEGER PRIMARY KEY AUTOINCREMENT,
            "tokenable_type" TEXT NOT NULL,
            "tokenable_id" INTEGER NOT NULL,
            "name" TEXT NOT NULL,
            "token" TEXT NOT NULL UNIQUE,
            "abilities" TEXT NULL,
            "last_used_at" TEXT NULL,
            "expires_at" TEXT NULL,
            "created_at" TEXT NULL,
            "updated_at" TEXT NULL
        )');
        $root->statement('CREATE TABLE IF NOT EXISTS "AuditLogs" (
            "AuditLogId" INTEGER PRIMARY KEY AUTOINCREMENT,
            "ActorType" TEXT NOT NULL,
            "ActorId" INTEGER NULL,
            "Action" TEXT NOT NULL,
            "TargetType" TEXT NOT NULL,
            "TargetId" INTEGER NULL,
            "RequestId" TEXT NOT NULL,
            "PayloadJson" TEXT NULL,
            "CreatedAt" TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
        )');
        $root->statement('CREATE TABLE IF NOT EXISTS "PricingPlans" (
            "PricingPlanId" INTEGER PRIMARY KEY AUTOINCREMENT,
            "planCode" TEXT NOT NULL UNIQUE,
            "planName" TEXT NOT NULL,
            "billingInterval" TEXT NOT NULL,
            "retailPriceCents" INTEGER NOT NULL,
            "discountedPriceCents" INTEGER NOT NULL,
            "allowedMachineSlots" INTEGER NOT NULL,
            "isBestValue" INTEGER NOT NULL DEFAULT 0,
            "isActive" INTEGER NOT NULL DEFAULT 1,
            "created_at" TEXT NULL,
            "updated_at" TEXT NULL
        )');
    }
}
