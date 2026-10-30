<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Db\ShardResolver;
use App\Models\AuthSession;
use App\Models\User;
use App\Policies\HasRolePolicy;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Ramsey\Uuid\Uuid;
use Tests\Support\AssertsLaraException;
use Tests\TestCase;

/**
 * Quota dual-write (RF-02 fix): an admin approve must move Root
 * ResellerQuotas and Root ResellerQuotaLedger, not just the shard, because
 * the gauge and issuance read Root. Backfill must repair shard-only quotas
 * idempotently, never lowering a Root total that is already ahead.
 *
 * The shard here is a temp sqlite file via the shard_template override,
 * the same pattern as QuotaRequestsTest, so no Postgres shard is needed.
 */
final class QuotaDualWriteTest extends TestCase
{
    use AssertsLaraException;

    private const ADMIN_EMAIL = 'admin-quota-dual@example.test';
    private const PASSWORD = 'CorrectHorse!Battery42';
    private const RESELLER_SLUG = 'acme';
    private const IDEM_KEY = 'aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa';

    private User $admin;
    private int $resellerId;
    private string $adminBearer;
    private string $shardDbPath;

    protected function setUp(): void
    {
        parent::setUp();
        $this->shardDbPath = sys_get_temp_dir() . '/lara_test_quota_dual_' . uniqid('', true) . '.sqlite';
        touch($this->shardDbPath);
        $this->configureShardTemplate();
        $this->createRootFixtures();
        $this->createShardTables();
        $this->swapRolePolicy();
        $this->admin = $this->makeUser(self::ADMIN_EMAIL);
        $this->adminBearer = $this->openSessionAndMintToken($this->admin);
        $this->resellerId = $this->seedReseller(self::RESELLER_SLUG, 'Acme');
        DB::connection('root')->table('UserRoles')->insert([
            'UserId' => (int) $this->admin->getKey(),
            'RoleId' => 2,
        ]);
    }

    protected function tearDown(): void
    {
        if (file_exists($this->shardDbPath)) {
            @unlink($this->shardDbPath);
        }
        parent::tearDown();
    }

    public function test_approve_dual_writes_root_quota_and_gauge(): void
    {
        \Tests\Feature\FakeHasRolePolicy::$grants = [(string) $this->admin->getKey() => ['Admin']];
        $qrId = $this->seedPendingQuotaRequest($this->resellerId, 7, 1, 10);

        $res = $this->withHeader('Authorization', 'Bearer ' . $this->adminBearer)
            ->withHeader('Idempotency-Key', self::IDEM_KEY)
            ->postJson('/Api/Admin/QuotaRequests/' . $qrId . '/Approve?ResellerSlug=' . self::RESELLER_SLUG, [
                'ApprovedDelta' => 10,
            ]);
        $res->assertStatus(200);

        $this->bindShard(self::RESELLER_SLUG);
        $shardQuota = DB::connection('shard')->table('Quotas')
            ->where('ResellerId', $this->resellerId)->where('LicenseCategoryId', 7)->where('LicenseTierId', 1)->first();
        $this->assertSame(10, (int) $shardQuota->LicensesGranted);

        $rootQuota = DB::connection('root')->table('ResellerQuotas')
            ->where('ResellerId', $this->resellerId)->where('LicenseCategoryId', 7)->where('LicenseTierId', 1)->first();
        $this->assertNotNull($rootQuota, 'Approve must dual-write Root ResellerQuotas.');
        $this->assertSame(10, (int) $rootQuota->LicensesGranted);
        $this->assertSame(0, (int) $rootQuota->LicensesConsumed);

        $gauge = $this->withHeader('Authorization', 'Bearer ' . $this->adminBearer)
            ->getJson('/Api/Resellers/' . $this->resellerId . '/Quotas');
        $gauge->assertStatus(200);
        $this->assertSame(10, (int) $gauge->json('Results.0.LicensesGranted'));
        $this->assertSame(10, (int) $gauge->json('Results.0.LicensesRemaining'));
    }

    public function test_approve_dual_writes_root_ledger(): void
    {
        \Tests\Feature\FakeHasRolePolicy::$grants = [(string) $this->admin->getKey() => ['Admin']];
        $qrId = $this->seedPendingQuotaRequest($this->resellerId, 7, 1, 10);
        $res = $this->withHeader('Authorization', 'Bearer ' . $this->adminBearer)
            ->withHeader('Idempotency-Key', self::IDEM_KEY)
            ->postJson('/Api/Admin/QuotaRequests/' . $qrId . '/Approve?ResellerSlug=' . self::RESELLER_SLUG, [
                'ApprovedDelta' => 10,
            ]);
        $res->assertStatus(200);

        $ledger = DB::connection('root')->table('ResellerQuotaLedger')
            ->where('ResellerId', $this->resellerId)->where('Delta', 10)->first();
        $this->assertNotNull($ledger, 'Approve must dual-write Root ResellerQuotaLedger.');
        $this->assertSame('QuotaAdjusted', (string) $ledger->LedgerAction);
    }

    public function test_backfill_creates_root_quota_and_is_idempotent(): void
    {
        $this->seedShardQuota($this->resellerId, 7, 1, 25);
        $this->assertNull(DB::connection('root')->table('ResellerQuotas')->where('ResellerId', $this->resellerId)->first());

        $this->assertSame(0, Artisan::call('lara:quotas:backfill'));
        $rootQuota = DB::connection('root')->table('ResellerQuotas')->where('ResellerId', $this->resellerId)->first();
        $this->assertSame(25, (int) $rootQuota->LicensesGranted);
        $this->assertSame(0, (int) $rootQuota->LicensesConsumed);

        $this->assertSame(0, Artisan::call('lara:quotas:backfill'));
        $this->assertSame(25, (int) DB::connection('root')->table('ResellerQuotas')->where('ResellerId', $this->resellerId)->sum('LicensesGranted'));
    }

    public function test_backfill_dry_run_writes_nothing(): void
    {
        $this->seedShardQuota($this->resellerId, 7, 1, 25);
        $this->assertSame(0, Artisan::call('lara:quotas:backfill', ['--dry-run' => true]));
        $this->assertNull(DB::connection('root')->table('ResellerQuotas')->where('ResellerId', $this->resellerId)->first());
    }

    public function test_backfill_raises_behind_root_but_never_lowers_ahead_root(): void
    {
        $this->seedShardQuota($this->resellerId, 7, 1, 25);
        DB::connection('root')->table('ResellerQuotas')->insert([
            'ResellerId' => $this->resellerId, 'LicenseCategoryId' => 7, 'LicenseTierId' => 1,
            'LicensesGranted' => 10, 'LicensesConsumed' => 2,
        ]);
        $this->assertSame(0, Artisan::call('lara:quotas:backfill'));
        $this->assertSame(25, (int) DB::connection('root')->table('ResellerQuotas')->where('ResellerId', $this->resellerId)->sum('LicensesGranted'));

        DB::connection('root')->table('ResellerQuotas')->where('ResellerId', $this->resellerId)->update(['LicensesGranted' => 500]);
        $this->assertSame(0, Artisan::call('lara:quotas:backfill'));
        $this->assertSame(500, (int) DB::connection('root')->table('ResellerQuotas')->where('ResellerId', $this->resellerId)->sum('LicensesGranted'));
    }

    private function configureShardTemplate(): void
    {
        config()->set('database.connections.shard_template', [
            'driver' => 'sqlite',
            'database' => $this->shardDbPath,
            'prefix' => '',
            'foreign_key_constraints' => false,
        ]);
        $this->bindShard(self::RESELLER_SLUG);
    }

    private function bindShard(string $slug): void
    {
        /** @var ShardResolver $resolver */
        $resolver = $this->app->make(ShardResolver::class);
        $resolver->bind($slug);
    }

    private function createShardTables(): void
    {
        $this->bindShard(self::RESELLER_SLUG);
        DB::connection('shard')->statement('CREATE TABLE IF NOT EXISTS "QuotaRequests" (
            "QuotaRequestId" INTEGER PRIMARY KEY AUTOINCREMENT, "ResellerId" INTEGER NOT NULL,
            "LicenseCategoryId" INTEGER NOT NULL, "LicenseTierId" INTEGER NOT NULL,
            "RequestedDelta" INTEGER NOT NULL, "ApprovedDelta" INTEGER NULL, "Status" INTEGER NOT NULL,
            "Justification" TEXT NULL, "DenialReason" TEXT NULL, "SubmittedByUserId" INTEGER NOT NULL,
            "DecidedByUserId" INTEGER NULL, "SubmittedAt" TEXT NOT NULL, "DecidedAt" TEXT NULL,
            "RequestId" TEXT NOT NULL, "IdempotencyKey" TEXT NULL
        )');
        DB::connection('shard')->statement('CREATE TABLE IF NOT EXISTS "Quotas" (
            "QuotaId" INTEGER PRIMARY KEY AUTOINCREMENT, "ResellerId" INTEGER NOT NULL,
            "LicenseCategoryId" INTEGER NOT NULL, "LicenseTierId" INTEGER NOT NULL,
            "LicensesGranted" INTEGER NOT NULL, "LicensesConsumed" INTEGER NOT NULL DEFAULT 0,
            "PeriodStart" TEXT NOT NULL, "PeriodEnd" TEXT NULL, "CreatedAt" TEXT NULL, "UpdatedAt" TEXT NULL
        )');
        DB::connection('shard')->statement('CREATE TABLE IF NOT EXISTS "LicenseLedger" (
            "LedgerId" INTEGER PRIMARY KEY AUTOINCREMENT, "ResellerId" INTEGER NOT NULL,
            "TierName" TEXT NOT NULL, "LedgerAction" TEXT NOT NULL, "Delta" INTEGER NOT NULL,
            "LicenseId" INTEGER NULL, "QuotaRequestId" INTEGER NULL, "RequestId" TEXT NOT NULL,
            "ActorUserId" INTEGER NOT NULL, "CreatedAt" TEXT NULL
        )');
    }

    private function seedPendingQuotaRequest(int $resellerId, int $categoryId, int $tierId, int $delta): int
    {
        $this->bindShard(self::RESELLER_SLUG);

        return (int) DB::connection('shard')->table('QuotaRequests')->insertGetId([
            'ResellerId' => $resellerId, 'LicenseCategoryId' => $categoryId, 'LicenseTierId' => $tierId,
            'RequestedDelta' => $delta, 'ApprovedDelta' => null, 'Status' => 1,
            'Justification' => 'Test seed row for dual-write.', 'DenialReason' => null,
            'SubmittedByUserId' => (int) $this->admin->getKey(), 'DecidedByUserId' => null,
            'SubmittedAt' => Carbon::now()->toDateTimeString(), 'DecidedAt' => null,
            'RequestId' => Uuid::uuid4()->toString(), 'IdempotencyKey' => null,
        ]);
    }

    private function seedShardQuota(int $resellerId, int $categoryId, int $tierId, int $granted): void
    {
        $this->bindShard(self::RESELLER_SLUG);
        DB::connection('shard')->table('Quotas')->insert([
            'ResellerId' => $resellerId, 'LicenseCategoryId' => $categoryId, 'LicenseTierId' => $tierId,
            'LicensesGranted' => $granted, 'LicensesConsumed' => 0,
            'PeriodStart' => Carbon::now()->toDateTimeString(), 'PeriodEnd' => null,
        ]);
    }

    private function swapRolePolicy(): void
    {
        require_once __DIR__ . '/../RoleGateTest.php';
        \Tests\Feature\FakeHasRolePolicy::$grants = [];
        $this->app->instance(HasRolePolicy::class, new \Tests\Feature\FakeHasRolePolicy());
    }

    private function createRootFixtures(): void
    {
        $root = DB::connection('root');
        $root->statement('CREATE TABLE IF NOT EXISTS "Users" (
            "UserId" INTEGER PRIMARY KEY AUTOINCREMENT, "Email" TEXT NOT NULL UNIQUE,
            "PasswordHash" TEXT NOT NULL, "TenantId" INTEGER NULL, "IsActive" INTEGER NOT NULL DEFAULT 1,
            "CreatedAt" TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP, "UpdatedAt" TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP, "DeletedAt" TEXT NULL
        )');
        $root->statement('CREATE TABLE IF NOT EXISTS "AuthSessions" (
            "SessionId" TEXT PRIMARY KEY, "UserId" INTEGER NOT NULL, "Kind" TEXT NOT NULL,
            "ImpersonatorUserId" INTEGER NULL, "ParentSessionId" TEXT NULL, "CreatedAt" TEXT NOT NULL,
            "ExpiresAt" TEXT NOT NULL, "EndedAt" TEXT NULL, "RevokeReason" TEXT NULL
        )');
        $root->statement('CREATE TABLE IF NOT EXISTS "personal_access_tokens" (
            "id" INTEGER PRIMARY KEY AUTOINCREMENT, "tokenable_type" TEXT NOT NULL, "tokenable_id" INTEGER NOT NULL,
            "name" TEXT NOT NULL, "token" TEXT NOT NULL UNIQUE, "abilities" TEXT NULL,
            "last_used_at" TEXT NULL, "expires_at" TEXT NULL, "created_at" TEXT NULL, "updated_at" TEXT NULL
        )');
        $root->statement('CREATE TABLE IF NOT EXISTS "AuditLogs" (
            "AuditLogId" INTEGER PRIMARY KEY AUTOINCREMENT, "ActorType" TEXT, "ActorId" INTEGER,
            "Action" TEXT NOT NULL, "TargetType" TEXT NOT NULL, "TargetId" TEXT NULL,
            "RequestId" TEXT NOT NULL, "PayloadJson" TEXT NULL, "CreatedAt" TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
        )');
        $root->statement('CREATE TABLE IF NOT EXISTS "IdempotencyRecords" (
            "IdempotencyRecordId" INTEGER PRIMARY KEY AUTOINCREMENT, "Endpoint" TEXT NOT NULL,
            "ActorId" TEXT NOT NULL, "IdempotencyKey" TEXT NOT NULL, "BodyHash" TEXT NOT NULL,
            "ResponseStatus" INTEGER NOT NULL, "ResponseHeadersJson" TEXT NOT NULL, "ResponseBody" TEXT NOT NULL,
            "CreatedAt" TEXT NOT NULL, "ExpiresAt" TEXT NOT NULL,
            CONSTRAINT "UX_IdempotencyRecords_Scope" UNIQUE ("Endpoint", "ActorId", "IdempotencyKey")
        )');
        $root->statement('CREATE TABLE IF NOT EXISTS "Resellers" (
            "ResellerId" INTEGER PRIMARY KEY AUTOINCREMENT, "ResellerName" TEXT NOT NULL UNIQUE,
            "ResellerSlug" TEXT NOT NULL UNIQUE, "ContactEmail" TEXT NOT NULL,
            "IsActive" INTEGER NOT NULL DEFAULT 1, "CreatedAt" TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
            "UpdatedAt" TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP, "DeletedAt" TEXT NULL
        )');
    }

    private function seedReseller(string $slug, string $name): int
    {
        return (int) DB::connection('root')->table('Resellers')->insertGetId([
            'ResellerName' => $name, 'ResellerSlug' => $slug, 'ContactEmail' => 'ops@' . $slug . '.test', 'IsActive' => 1,
        ]);
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

    private function openSessionAndMintToken(User $user): string
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

        return $user->createToken($sessionId)->plainTextToken;
    }
}
