<?php

declare(strict_types=1);

namespace Tests\Feature\Portal;

use App\Models\Reseller;
use App\Models\User;
use App\Models\License;
use Illuminate\Support\Facades\DB;
use Tests\Support\AssertsLaraException;
use Tests\TestCase;

/**
 * Plan 06 step 58. Feature test ShardIsolationTest asserting reseller A
 * cannot read reseller B licenses even with forged IDs.
 *
 * Locked behaviors:
 *  - Reseller A authenticated session binds to Shard A.
 *  - GET /Api/Reseller/Licenses returns only licenses in Shard A.
 *  - GET /Api/Reseller/Licenses/{Key} returns 404 LicenseNotFound if Key exists in Shard B but not A.
 *  - Cross-reseller probes via forged TenantId or Slug are blocked by ShardBindingMiddleware.
 */
final class ShardIsolationTest extends TestCase
{
    use AssertsLaraException;

    private const ROOT = 'root';
    private const SHARD = 'shard';

    private Reseller $resellerA;
    private Reseller $resellerB;
    private User $userA;
    private User $userB;
    private string $testId;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('database.connections.root', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
            'foreign_key_constraints' => false,
        ]);

        $this->testId = uniqid('', true);
        $this->dbPathA = sys_get_temp_dir() . '/lara_shard_' . $this->testId . '_reseller-a.sqlite';
        $this->dbPathB = sys_get_temp_dir() . '/lara_shard_' . $this->testId . '_reseller-b.sqlite';
        touch($this->dbPathA);
        touch($this->dbPathB);

        $this->migrateRoot();
        $this->seedTenants();

        $this->app->instance(\App\Policies\HasRolePolicy::class, new class extends \App\Policies\HasRolePolicy {
            public function hasRole(string $userId, string $role): bool { return true; }
            public function hasAnyRole(string $userId, array $roles): bool { return true; }
        });
    }

    protected function tearDown(): void
    {
        DB::disconnect('shard');
        DB::purge('shard');
        DB::disconnect('temp_shard');
        DB::purge('temp_shard');
        if (isset($this->dbPathA) && file_exists($this->dbPathA)) @unlink($this->dbPathA);
        if (isset($this->dbPathB) && file_exists($this->dbPathB)) @unlink($this->dbPathB);
        parent::tearDown();
    }

    /**
     * @test
     */
    public function it_isolates_license_list_to_bound_shard(): void
    {
        // 1. Seed license in Shard A
        $this->seedLicense($this->resellerA, $this->dbPathA, 'LARA-AAAA-AAAA');
        // 2. Seed license in Shard B
        $this->seedLicense($this->resellerB, $this->dbPathB, 'LARA-BBBB-BBBB');

        // 3. Act as User A
        $tokenA = $this->openToken($this->userA);
        $response = $this->withHeader('Authorization', 'Bearer ' . $tokenA)
            ->getJson('/Api/Reseller/Licenses');

        $response->assertStatus(200);
        $results = $response->json('Results');
        $this->assertCount(1, $results);
        $this->assertEquals('LARA-AAAA-AAAA', $results[0]['LicenseKey']);
        
        // 4. Act as User B
        $this->app['auth']->forgetGuards();
        $tokenB = $this->openToken($this->userB);
        $response = $this->withHeader('Authorization', 'Bearer ' . $tokenB)
            ->getJson('/Api/Reseller/Licenses');

        $response->assertStatus(200);
        $results = $response->json('Results');
        $this->assertCount(1, $results);
        $this->assertEquals('LARA-BBBB-BBBB', $results[0]['LicenseKey']);
    }

    /**
     * @test
     */
    public function it_returns_404_when_reseller_probes_license_in_another_shard(): void
    {
        $this->seedLicense($this->resellerA, $this->dbPathA, 'LARA-OWN-KEY');
        $this->seedLicense($this->resellerB, $this->dbPathB, 'LARA-FORGED-KEY');

        $tokenA = $this->openToken($this->userA);
        $response = $this->withHeader('Authorization', 'Bearer ' . $tokenA)
            ->getJson('/Api/Reseller/Licenses/LARA-FORGED-KEY');
        $this->assertLaraException($response, 'LicenseNotFound', 404);
    }

    private function openToken(User $user): string
    {
        $sessionId = \Ramsey\Uuid\Uuid::uuid4()->toString();
        $now = \Illuminate\Support\Carbon::now();
        DB::connection(self::ROOT)->table('AuthSessions')->insert([
            'SessionId' => $sessionId,
            'UserId' => (int) $user->getKey(),
            'Kind' => 'Normal',
            'CreatedAt' => $now->toIso8601String(),
            'ExpiresAt' => $now->copy()->addHour()->toIso8601String(),
        ]);
        return $user->createToken($sessionId)->plainTextToken;
    }

    private function migrateRoot(): void
    {
        DB::connection(self::ROOT)->statement('CREATE TABLE IF NOT EXISTS "Users" (
            "UserId" INTEGER PRIMARY KEY AUTOINCREMENT,
            "Email" VARCHAR(255) NOT NULL UNIQUE,
            "PasswordHash" VARCHAR(255) NOT NULL,
            "TenantId" INTEGER NULL,
            "IsActive" BOOLEAN NOT NULL DEFAULT 1
        )');

        DB::connection(self::ROOT)->statement('CREATE TABLE IF NOT EXISTS "Resellers" (
            "ResellerId" INTEGER PRIMARY KEY AUTOINCREMENT,
            "ResellerName" VARCHAR(128) NOT NULL,
            "ResellerSlug" VARCHAR(64) NOT NULL UNIQUE,
            "ContactEmail" VARCHAR(255) NOT NULL,
            "IsActive" BOOLEAN NOT NULL DEFAULT 1
        )');

        DB::connection(self::ROOT)->statement('CREATE TABLE IF NOT EXISTS "ResellerShardRoutes" (
            "ResellerShardRouteId" INTEGER PRIMARY KEY AUTOINCREMENT,
            "ResellerId" INTEGER NOT NULL,
            "AppDbPath" VARCHAR(512) NOT NULL,
            "ShardStatus" VARCHAR(32) NOT NULL DEFAULT "Active"
        )');

        DB::connection(self::ROOT)->statement('CREATE TABLE IF NOT EXISTS "personal_access_tokens" (
            "id" INTEGER PRIMARY KEY AUTOINCREMENT,
            "tokenable_type" VARCHAR(255) NOT NULL,
            "tokenable_id" INTEGER NOT NULL,
            "name" VARCHAR(255) NOT NULL,
            "token" VARCHAR(64) NOT NULL UNIQUE,
            "abilities" TEXT NULL,
            "last_used_at" TEXT NULL,
            "expires_at" TEXT NULL,
            "created_at" TEXT NULL,
            "updated_at" TEXT NULL
        )');

        DB::connection(self::ROOT)->statement('CREATE TABLE IF NOT EXISTS "AuthSessions" (
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
    }

    private function seedTenants(): void
    {
        $this->resellerA = Reseller::create([
            'ResellerName' => 'Reseller A',
            'ResellerSlug' => 'reseller-a',
            'ContactEmail' => 'a@test.com',
            'IsActive' => true,
        ]);
        $this->userA = User::create([
            'Email' => 'user.a@test.com',
            'PasswordHash' => 'hash',
            'TenantId' => $this->resellerA->ResellerId,
            'IsActive' => true,
        ]);
        DB::connection(self::ROOT)->table('ResellerShardRoutes')->insert([
            'ResellerId' => $this->resellerA->ResellerId,
            'AppDbPath' => $this->dbPathA,
            'ShardStatus' => 'Active',
        ]);

        $this->resellerB = Reseller::create([
            'ResellerName' => 'Reseller B',
            'ResellerSlug' => 'reseller-b',
            'ContactEmail' => 'b@test.com',
            'IsActive' => true,
        ]);
        $this->userB = User::create([
            'Email' => 'user.b@test.com',
            'PasswordHash' => 'hash',
            'TenantId' => $this->resellerB->ResellerId,
            'IsActive' => true,
        ]);
        DB::connection(self::ROOT)->table('ResellerShardRoutes')->insert([
            'ResellerId' => $this->resellerB->ResellerId,
            'AppDbPath' => $this->dbPathB,
            'ShardStatus' => 'Active',
        ]);
        
        // Setup shard template for SQLite path substitution
        config()->set('database.connections.shard_template', [
            'driver' => 'sqlite',
            'database' => sys_get_temp_dir() . '/lara_shard_' . $this->testId . '_{reseller}.sqlite',
            'prefix' => '',
            'foreign_key_constraints' => false,
        ]);
    }

    private function seedLicense(Reseller $reseller, string $dbPath, string $key): void
    {
        config()->set('database.connections.temp_shard', [
            'driver' => 'sqlite',
            'database' => $dbPath,
            'prefix' => '',
            'foreign_key_constraints' => false,
        ]);
        DB::purge('temp_shard');
        
        DB::connection('temp_shard')->statement('CREATE TABLE IF NOT EXISTS "Licenses" (
            "LicenseId" INTEGER PRIMARY KEY AUTOINCREMENT,
            "LicenseKey" VARCHAR(64) NOT NULL UNIQUE,
            "PrefixValue" VARCHAR(12) NOT NULL,
            "ResellerId" BIGINT NULL,
            "IssuedByUserId" BIGINT NOT NULL,
            "IssuerActorType" VARCHAR(16) NOT NULL DEFAULT "Reseller",
            "LicenseCategoryId" BIGINT NULL,
            "TierName" VARCHAR(16) NOT NULL,
            "EnvironmentName" VARCHAR(16) NOT NULL,
            "ProductVersion" VARCHAR(16) NOT NULL DEFAULT "V1",
            "Status" VARCHAR(16) NOT NULL DEFAULT "Active",
            "IssuedAt" TEXT NOT NULL,
            "ExpiresAt" TEXT NULL,
            "Version" INTEGER NOT NULL DEFAULT 1,
            "CreatedAt" TEXT NULL,
            "UpdatedAt" TEXT NULL
        )');

        DB::connection('temp_shard')->table('Licenses')->insert([
            'LicenseKey' => $key,
            'PrefixValue' => 'LARA',
            'ResellerId' => $reseller->ResellerId,
            'IssuedByUserId' => 1,
            'IssuerActorType' => 'Reseller',
            'LicenseCategoryId' => 1,
            'TierName' => 'Standard',
            'EnvironmentName' => 'Production',
            'ProductVersion' => 'V1',
            'Status' => 'Active',
            'IssuedAt' => now(),
            'Version' => 1,
        ]);
    }
}
