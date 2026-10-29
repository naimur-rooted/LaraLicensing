<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Models\AuthSession;
use App\Models\PricingPlanPriceVersion;
use App\Models\User;
use App\Policies\HasRolePolicy;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Ramsey\Uuid\Uuid;
use Tests\Support\AssertsLaraException;
use Tests\TestCase;

/**
 * Versioned PricingPlan price changes.
 *
 * A price change appends a new version snapshot and leaves the old
 * price readable in the versions list. A non-price update creates no
 * new version, and a future effectiveFrom is rejected.
 */
final class PricingPlanPriceVersionTest extends TestCase
{
    use AssertsLaraException;

    private const ADMIN_EMAIL = 'admin-pricing-version@example.test';
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
        $this->grantAdmin();
    }

    public function test_price_change_creates_version_and_keeps_old_price(): void
    {
        $id = $this->createPlan('MUSE_VER_' . time());
        $this->assertVersionCount($id, 1);
        $this->assertVersionPrice($id, 1, 4900);

        $update = $this->authed()->patchJson("/Api/Admin/PricingPlans/{$id}", ['retailPriceCents' => 9900]);
        $update->assertStatus(200);
        $this->assertSame(9900, $update->json('Results.Item.retailPriceCents'));

        $this->assertVersionCount($id, 2);
        $this->assertVersionPrice($id, 1, 4900);
        $this->assertVersionPrice($id, 2, 9900);
    }

    public function test_same_price_update_creates_no_new_version(): void
    {
        $id = $this->createPlan('MUSE_SAME_' . time());
        $this->authed()->patchJson("/Api/Admin/PricingPlans/{$id}", ['retailPriceCents' => 4900])->assertStatus(200);
        $this->assertVersionCount($id, 1);
    }

    public function test_non_price_update_creates_no_new_version(): void
    {
        $id = $this->createPlan('MUSE_NAME_' . time());
        $update = $this->authed()->patchJson("/Api/Admin/PricingPlans/{$id}", ['planName' => 'Renamed Plan']);
        $update->assertStatus(200);
        $this->assertSame('Renamed Plan', $update->json('Results.Item.planName'));
        $this->assertVersionCount($id, 1);
    }

    public function test_future_effective_from_returns_400_and_keeps_price(): void
    {
        $id = $this->createPlan('MUSE_FUT_' . time());
        $res = $this->authed()->patchJson("/Api/Admin/PricingPlans/{$id}", ['retailPriceCents' => 10000, 'effectiveFrom' => '2099-01-01T00:00:00Z']);
        $this->assertLaraException($res, 'ValidationInputInvalid', 400);
        $this->assertVersionCount($id, 1);
        $this->assertVersionPrice($id, 1, 4900);
    }

    public function test_versions_endpoint_requires_auth(): void
    {
        $plan = \App\Models\PricingPlan::create($this->validBody('MUSE_AUTH_' . time()));
        $res = $this->getJson("/Api/Admin/PricingPlans/{$plan->getKey()}/Versions");
        $this->assertLaraException($res, 'AuthUnauthorized', 401);
    }

    private function createPlan(string $code): int
    {
        $create = $this->authed()->postJson('/Api/Admin/PricingPlans', $this->validBody($code));
        $create->assertStatus(201);

        return (int) $create->json('Results.Item.PricingPlanId');
    }

    private function assertVersionCount(int $planId, int $expected): void
    {
        $versions = $this->authed()->getJson("/Api/Admin/PricingPlans/{$planId}/Versions");
        $versions->assertStatus(200);
        $this->assertCount($expected, $versions->json('Results.Items'));
    }

    private function assertVersionPrice(int $planId, int $version, int $price): void
    {
        $row = PricingPlanPriceVersion::query()->where('PricingPlanId', $planId)->where('versionNumber', $version)->first();
        $this->assertNotNull($row);
        $this->assertSame($price, (int) $row->retailPriceCents);
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
    private function validBody(string $code): array
    {
        return [
            'planCode' => $code,
            'planName' => 'Muse Version Test Plan',
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
        $root->statement('CREATE TABLE IF NOT EXISTS "PricingPlanPriceVersions" (
            "PricingPlanPriceVersionId" INTEGER PRIMARY KEY AUTOINCREMENT,
            "PricingPlanId" INTEGER NOT NULL,
            "versionNumber" INTEGER NOT NULL,
            "planCode" TEXT NOT NULL,
            "planName" TEXT NOT NULL,
            "billingInterval" TEXT NOT NULL,
            "retailPriceCents" INTEGER NOT NULL,
            "discountedPriceCents" INTEGER NOT NULL,
            "allowedMachineSlots" INTEGER NOT NULL,
            "isBestValue" INTEGER NOT NULL DEFAULT 0,
            "isActive" INTEGER NOT NULL DEFAULT 1,
            "effectiveFrom" TEXT NULL,
            "created_at" TEXT NULL,
            "updated_at" TEXT NULL,
            UNIQUE ("PricingPlanId", "versionNumber")
        )');
    }
}
