<?php

declare(strict_types=1);

namespace Tests\Feature\Portal;

use App\Db\ShardResolver;
use App\Models\AuthSession;
use App\Models\ImpersonationIndex;
use App\Models\Reseller;
use App\Models\User;
use App\Services\AuthSessionService;
use App\Services\ImpersonationService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Tests\Support\AssertsLaraException;
use Tests\TestCase;

/**
 * Plan 06 step 56. Feature test ImpersonationTransactionTest locking 
 * session begin/end pair + audit rows (spec 47).
 *
 * Locked behaviors:
 *  - AC-IMP-001/002: Admin can begin impersonation with valid reason.
 *  - AC-IMP-004/011: One active impersonation session per operator (Root UK gate).
 *  - AC-IMP-010: Shard-scoped target has AuthSession row in shard, not Root.
 *  - AC-IMP-005: Operator can end their own session.
 *  - AC-IMP-006: Admin can force-end any active session.
 *  - AC-IMP-007: AuditLogs.PayloadJson carries ImpersonatorUserId.
 */
final class ImpersonationTransactionTest extends TestCase
{
    use AssertsLaraException;

    private const ROOT = 'root';
    private const SHARD = 'shard';
    private const SLUG = 'acme-shard';

    private User $admin;
    private User $rootTarget;
    private User $shardTarget;
    private Reseller $reseller;
    private string $shardDbPath;

    protected function setUp(): void
    {
        parent::setUp();

        // 1. Setup Root DB (SQLite Memory)
        config()->set('database.connections.root', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
            'foreign_key_constraints' => false,
        ]);

        // 2. Setup Shard Template
        $this->shardDbPath = sys_get_temp_dir() . '/lara_test_imp_shard_' . uniqid('', true) . '.sqlite';
        touch($this->shardDbPath);
        config()->set('database.connections.shard_template', [
            'driver' => 'sqlite',
            'database' => $this->shardDbPath,
            'prefix' => '',
            'foreign_key_constraints' => false,
        ]);

        // 3. Migrate Root
        $this->migrateRoot();

        // 4. Seed bootstrap identities
        $this->seedIdentities();
    }

    protected function tearDown(): void
    {
        DB::disconnect('shard');
        DB::purge('shard');
        if (isset($this->shardDbPath) && file_exists($this->shardDbPath)) {
            @unlink($this->shardDbPath);
        }
        parent::tearDown();
    }

    /**
     * @test AC-IMP-001, AC-IMP-004, AC-IMP-010, AC-IMP-007
     */
    public function it_begins_impersonation_transactionally_across_root_and_shard(): void
    {
        // 1. Create a parent session for the Admin
        $parent = $this->openNormalSession($this->admin);
        $requestId = 'req-imp-001';

        // 2. Begin impersonation of shard target
        /** @var ImpersonationService $svc */
        $svc = app(ImpersonationService::class);
        
        $payload = $svc->begin(
            $this->admin,
            $parent,
            $this->shardTarget,
            'Testing shard isolation and audit',
            $requestId
        );

        $sessionId = $payload['SessionId'];
        $this->assertNotEmpty($sessionId);

        // 3. Verify Root ImpersonationIndex
        $this->assertDatabaseHas('ImpersonationIndex', [
            'SessionId' => $sessionId,
            'ImpersonatorUserId' => $this->admin->UserId,
            'TargetUserId' => $this->shardTarget->UserId,
            'TargetResellerId' => $this->reseller->ResellerId,
            'EndedAt' => null,
        ], self::ROOT);

        // 4. Verify Shard AuthSessions (AC-IMP-010)
        // ShardResolver was bound during begin()
        $this->assertDatabaseHas('AuthSessions', [
            'SessionId' => $sessionId,
            'UserId' => $this->shardTarget->UserId,
            'Kind' => AuthSession::KIND_IMPERSONATION,
            'ImpersonatorUserId' => $this->admin->UserId,
            'ParentSessionId' => $parent->SessionId,
            'EndedAt' => null,
        ], self::SHARD);

        // Verify Root AuthSessions DOES NOT have it
        $this->assertDatabaseMissing('AuthSessions', [
            'SessionId' => $sessionId,
        ], self::ROOT);

        // 5. Verify Audit Log (AC-IMP-007)
        $audit = DB::connection(self::ROOT)->table('AuditLogs')
            ->where('RequestId', $requestId)
            ->where('Action', 'ImpersonationStarted')
            ->first();
        
        $this->assertNotNull($audit);
        $payloadJson = json_decode($audit->PayloadJson, true);
        $this->assertEquals($this->admin->UserId, $payloadJson['ImpersonatorUserId']);
        $this->assertEquals($this->shardTarget->UserId, $payloadJson['TargetUserId']);
        $this->assertEquals('Testing shard isolation and audit', $payloadJson['Reason']);

        // 6. Verify AC-IMP-004 (Unique active gate)
        $caught = false;
        try {
            $svc->begin($this->admin, $parent, $this->rootTarget, 'Second attempt', $requestId);
        } catch (\Throwable $e) {
            $caught = true;
        }
        $this->assertTrue($caught, 'Second begin must throw due to active impersonation constraint');
    }

    /**
     * @test AC-IMP-005
     */
    public function it_ends_impersonation_transactionally(): void
    {
        $parent = $this->openNormalSession($this->admin);
        /** @var ImpersonationService $svc */
        $svc = app(ImpersonationService::class);
        $payload = $svc->begin($this->admin, $parent, $this->rootTarget, 'Cleanup test', 'req-002');
        $sessionId = $payload['SessionId'];
        
        $session = AuthSession::query()->where('SessionId', $sessionId)->first();
        
        $svc->end($this->admin, $session, 'OperatorEnded', 'req-003');

        // Verify index closed
        $this->assertDatabaseHas('ImpersonationIndex', [
            'SessionId' => $sessionId,
            'EndReason' => 'OperatorEnded',
        ], self::ROOT);
        $this->assertNotNull(ImpersonationIndex::find($sessionId)->EndedAt);

        // Verify session closed
        $this->assertDatabaseHas('AuthSessions', [
            'SessionId' => $sessionId,
            'RevokeReason' => 'OperatorEnded',
        ], self::ROOT);
        
        // Verify audit
        $this->assertDatabaseHas('AuditLogs', [
            'RequestId' => 'req-003',
            'Action' => 'ImpersonationEnded',
        ], self::ROOT);
    }

    /**
     * @test AC-IMP-006
     */
    public function it_allows_admin_to_force_end_arbitrary_session(): void
    {
        $parent = $this->openNormalSession($this->admin);
        /** @var ImpersonationService $svc */
        $svc = app(ImpersonationService::class);
        $payload = $svc->begin($this->admin, $parent, $this->shardTarget, 'Force end test', 'req-004');
        $sessionId = $payload['SessionId'];

        // End by force
        $svc->forceEnd($this->admin, $sessionId, 'req-005');

        $this->assertDatabaseHas('ImpersonationIndex', [
            'SessionId' => $sessionId,
            'EndReason' => 'AdminForced',
        ], self::ROOT);
        
        // Verify shard session closed
        $this->assertDatabaseHas('AuthSessions', [
            'SessionId' => $sessionId,
            'RevokeReason' => 'AdminForced',
        ], self::SHARD);
    }

    private function migrateRoot(): void
    {
        $root = DB::connection(self::ROOT);
        $root->statement('CREATE TABLE IF NOT EXISTS "Roles" (
            "RoleId" INTEGER PRIMARY KEY AUTOINCREMENT,
            "RoleName" VARCHAR(32) NOT NULL UNIQUE
        )');
        $root->statement('CREATE TABLE IF NOT EXISTS "Users" (
            "UserId" INTEGER PRIMARY KEY AUTOINCREMENT,
            "Email" VARCHAR(255) NOT NULL UNIQUE,
            "PasswordHash" VARCHAR(255) NOT NULL,
            "TenantId" INTEGER NULL,
            "IsActive" BOOLEAN NOT NULL DEFAULT 1
        )');
        $root->statement('CREATE TABLE IF NOT EXISTS "UserRoles" (
            "UserRoleId" INTEGER PRIMARY KEY AUTOINCREMENT,
            "UserId" INTEGER NOT NULL,
            "RoleId" INTEGER NOT NULL
        )');
        $root->statement('CREATE TABLE IF NOT EXISTS "Resellers" (
            "ResellerId" INTEGER PRIMARY KEY AUTOINCREMENT,
            "ResellerName" VARCHAR(128) NOT NULL,
            "ResellerSlug" VARCHAR(64) NOT NULL UNIQUE,
            "ContactEmail" VARCHAR(255) NOT NULL,
            "IsActive" BOOLEAN NOT NULL DEFAULT 1
        )');
        $root->statement('CREATE TABLE IF NOT EXISTS "ResellerShardRoutes" (
            "ResellerShardRouteId" INTEGER PRIMARY KEY AUTOINCREMENT,
            "ResellerId" INTEGER NOT NULL,
            "AppDbPath" VARCHAR(512) NOT NULL,
            "ShardStatus" VARCHAR(32) NOT NULL DEFAULT "Active"
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
        $root->statement('CREATE TABLE IF NOT EXISTS "ImpersonationIndex" (
            "SessionId" TEXT PRIMARY KEY,
            "ImpersonatorUserId" INTEGER NOT NULL,
            "TargetUserId" INTEGER NOT NULL,
            "TargetResellerId" INTEGER NULL,
            "StartedAt" TEXT NOT NULL,
            "ExpiresAt" TEXT NULL,
            "EndedAt" TEXT NULL,
            "EndReason" TEXT NULL
        )');
        $root->statement('CREATE UNIQUE INDEX IF NOT EXISTS "UX_ImpersonationIndex_OneActive" ON "ImpersonationIndex" ("ImpersonatorUserId") WHERE "EndedAt" IS NULL');
        $root->statement('CREATE TABLE IF NOT EXISTS "personal_access_tokens" (
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
        $root->statement('CREATE TABLE IF NOT EXISTS "AuditLogs" (
            "AuditLogId" INTEGER PRIMARY KEY AUTOINCREMENT,
            "ActorType" TEXT NOT NULL,
            "ActorId" INTEGER NULL,
            "Action" TEXT NOT NULL,
            "TargetType" TEXT NOT NULL,
            "TargetId" TEXT NULL,
            "RequestId" TEXT NOT NULL,
            "PayloadJson" TEXT NULL,
            "CreatedAt" TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
        )');

        config()->set('database.connections.shard', config('database.connections.shard_template'));
        DB::purge('shard');
        DB::connection(self::SHARD)->statement('CREATE TABLE IF NOT EXISTS "AuthSessions" (
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

    private function seedIdentities(): void
    {
        $existing = DB::connection(self::ROOT)->table('Roles')->where('RoleName', 'Admin')->first();
        $roleId = $existing ? $existing->RoleId : DB::connection(self::ROOT)->table('Roles')->insertGetId([
            'RoleName' => 'Admin',
        ]);

        $this->admin = User::create([
            'Email' => 'admin@lara.test',
            'PasswordHash' => 'hash',
            'IsActive' => true,
        ]);

        DB::connection(self::ROOT)->table('UserRoles')->insert([
            'UserId' => $this->admin->UserId,
            'RoleId' => $roleId,
        ]);

        $this->rootTarget = User::create([
            'Email' => 'target.root@lara.test',
            'PasswordHash' => 'hash',
            'IsActive' => true,
        ]);

        $this->reseller = Reseller::create([
            'ResellerName' => 'ACME Shard',
            'ResellerSlug' => self::SLUG,
            'ContactEmail' => 'acme@lara.test',
            'IsActive' => true,
        ]);

        $this->shardTarget = User::create([
            'Email' => 'target.shard@lara.test',
            'PasswordHash' => 'hash',
            'TenantId' => $this->reseller->ResellerId,
            'IsActive' => true,
        ]);
        
        // Add shard route
        DB::connection(self::ROOT)->table('ResellerShardRoutes')->insert([
            'ResellerId' => $this->reseller->ResellerId,
            'AppDbPath' => $this->shardDbPath,
            'ShardStatus' => 'Active',
        ]);
    }

    private function openNormalSession(User $user): AuthSession
    {
        /** @var AuthSessionService $svc */
        $svc = app(AuthSessionService::class);

        return $svc->openNormal($user);
    }
}
