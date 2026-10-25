<?php

declare(strict_types=1);

namespace Tests\Feature\Auth;

use App\Models\AuthSession;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\RateLimiter;
use Tests\Support\AssertsLaraException;
use Tests\TestCase;

final class DeveloperRegisterTest extends TestCase
{
    use AssertsLaraException;

    private const TEST_EMAIL = 'developer@acme-software.test';
    private const TEST_PASSWORD = 'SecurePassword2026!';

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
        RateLimiter::clear('auth:register|ip=127.0.0.1');
        $this->createRootFixtures();
    }

    public function test_successful_developer_registration_returns_201_token_and_plan(): void
    {
        $res = $this->postJson('/api/v1/auth/developer-register', [
            'Email' => self::TEST_EMAIL,
            'Password' => self::TEST_PASSWORD,
            'DisplayName' => 'Acme Developer',
            'SelectedPlan' => 'PRO',
            'BillingInterval' => 'annual',
        ]);

        $res->assertStatus(201);
        $json = $res->json();
        $this->assertTrue($json['Status']['IsSuccess'] ?? false);
        $this->assertSame(201, $json['Status']['Code']);

        $row = $json['Results'][0] ?? [];
        $this->assertSame(self::TEST_EMAIL, $row['Email'] ?? null);
        $this->assertSame('Developer', $row['Role'] ?? null);
        $this->assertNotEmpty($row['Token'] ?? null);
        $this->assertSame('PRO', $row['Subscription']['PlanCode'] ?? null);
        $this->assertSame(15, $row['Subscription']['MaxProducts'] ?? null);
        $this->assertSame(1000, $row['Subscription']['DailyQuota'] ?? null);
    }

    public function test_duplicate_email_registration_returns_409_conflict(): void
    {
        $this->postJson('/api/v1/auth/developer-register', [
            'Email' => self::TEST_EMAIL,
            'Password' => self::TEST_PASSWORD,
            'SelectedPlan' => 'STARTER',
        ])->assertStatus(201);

        $res = $this->postJson('/api/v1/auth/developer-register', [
            'Email' => self::TEST_EMAIL,
            'Password' => self::TEST_PASSWORD,
            'SelectedPlan' => 'PRO',
        ]);

        $res->assertStatus(409);
        $json = $res->json();
        $this->assertFalse($json['Status']['IsSuccess'] ?? true);
        $this->assertSame('DomainConflict', $json['Attributes']['Error']['ErrorCode'] ?? null);
    }

    public function test_password_under_12_characters_returns_400(): void
    {
        $res = $this->postJson('/api/v1/auth/developer-register', [
            'Email' => 'shortpass@example.test',
            'Password' => 'short123',
            'SelectedPlan' => 'STARTER',
        ]);

        $res->assertStatus(400);
    }

    public function test_route_alias_developer_register_works_consistently(): void
    {
        $res = $this->postJson('/Api/Auth/DeveloperRegister', [
            'Email' => 'alias@example.test',
            'Password' => self::TEST_PASSWORD,
            'SelectedPlan' => 'ESSENTIAL',
        ]);

        $res->assertStatus(201);
        $this->assertSame('ESSENTIAL', $res->json('Results.0.Subscription.PlanCode'));
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
        $root->statement('CREATE TABLE IF NOT EXISTS "Roles" (
            "RoleId" INTEGER PRIMARY KEY AUTOINCREMENT,
            "RoleName" TEXT NOT NULL UNIQUE,
            "CreatedAt" TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
            "UpdatedAt" TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
        )');
        $root->statement('CREATE TABLE IF NOT EXISTS "UserRoles" (
            "UserRoleId" INTEGER PRIMARY KEY AUTOINCREMENT,
            "UserId" INTEGER NOT NULL,
            "RoleId" INTEGER NOT NULL,
            "CreatedAt" TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
            UNIQUE("UserId", "RoleId")
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

        $root->table('Roles')->insertOrIgnore([
            ['RoleId' => 1, 'RoleName' => 'SuperAdmin'],
            ['RoleId' => 2, 'RoleName' => 'Admin'],
            ['RoleId' => 3, 'RoleName' => 'Reseller'],
            ['RoleId' => 4, 'RoleName' => 'AppBuilder'],
            ['RoleId' => 5, 'RoleName' => 'EndUser'],
        ]);
    }
}
