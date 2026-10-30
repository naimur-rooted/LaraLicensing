<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Http\Controllers\Admin\ResellerQuotaController;
use App\Models\AuthSession;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Ramsey\Uuid\Uuid;
use Tests\Support\AssertsLaraException;
use Tests\TestCase;

/**
 * Covers Admin\ResellerQuotaController (shared /Resellers/{id}/Quotas,
 * /QuotaLedger, /QuotaRequests endpoints) for the EndpointInventory gate.
 *
 * Root cause guarded: these three endpoints shipped in 7404ea8 with no
 * test file referencing the controller, so the parity gate failed.
 * Index and ledger read Root ResellerQuotas / ResellerQuotaLedger;
 * quotaRequests binds the reseller shard, so its happy path uses the
 * provisioned alim shard (ResellerId 1) like the other Postgres-backed
 * reseller suites, and asserts shape only, not row counts.
 */
final class ResellerQuotaControllerTest extends TestCase
{
    use AssertsLaraException;

    private const ADMIN_EMAIL = 'admin-quota@example.test';
    private const PASSWORD = 'CorrectHorse!Battery42';

    private User $admin;
    private int $resellerId;
    private string $adminBearer;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = $this->makeUser(self::ADMIN_EMAIL);
        DB::connection('root')->table('UserRoles')->insert([
            'UserId' => (int) $this->admin->getKey(),
            'RoleId' => 2,
        ]);
        $this->adminBearer = $this->openSessionAndMintToken($this->admin);
        $this->resellerId = (int) DB::connection('root')->table('Resellers')->insertGetId([
            'ResellerName' => 'Acme',
            'ResellerSlug' => 'acme',
            'ContactEmail' => 'ops@acme.test',
            'IsActive' => 1,
        ]);
        DB::connection('root')->table('ResellerQuotas')->insert([
            'ResellerId' => $this->resellerId,
            'LicenseCategoryId' => 3,
            'LicenseTierId' => 1,
            'LicensesGranted' => 500,
            'LicensesConsumed' => 65,
        ]);
        DB::connection('root')->table('ResellerQuotaLedger')->insert([
            'ResellerId' => $this->resellerId,
            'LicenseCategoryId' => 3,
            'LicenseTierId' => 1,
            'Delta' => 500,
            'Action' => 'QuotaGranted',
            'LedgerAction' => 'QuotaGranted',
            'ReferenceId' => 'seed-ref-quota',
        ]);
    }

    public function test_index_bare_returns_401(): void
    {
        $res = $this->getJson('/Api/Resellers/' . $this->resellerId . '/Quotas');
        $this->assertLaraException($res, 'AuthUnauthorized', 401);
    }

    public function test_index_admin_returns_seeded_quota_with_remaining(): void
    {
        $res = $this->withHeader('Authorization', 'Bearer ' . $this->adminBearer)
            ->getJson('/Api/Resellers/' . $this->resellerId . '/Quotas');
        $res->assertStatus(200);
        $this->assertSame(500, $res->json('Results.0.LicensesGranted'));
        $this->assertSame(65, $res->json('Results.0.LicensesConsumed'));
        $this->assertSame(435, $res->json('Results.0.LicensesRemaining'));
    }

    public function test_index_filters_by_license_category(): void
    {
        $res = $this->withHeader('Authorization', 'Bearer ' . $this->adminBearer)
            ->getJson('/Api/Resellers/' . $this->resellerId . '/Quotas?LicenseCategoryId=99');
        $res->assertStatus(200);
        $this->assertSame([], $res->json('Results'));
    }

    public function test_index_unknown_reseller_returns_404(): void
    {
        $res = $this->withHeader('Authorization', 'Bearer ' . $this->adminBearer)
            ->getJson('/Api/Resellers/999999/Quotas');
        $this->assertLaraException($res, 'ResellerNotFound', 404);
    }

    public function test_ledger_admin_returns_seeded_row(): void
    {
        $res = $this->withHeader('Authorization', 'Bearer ' . $this->adminBearer)
            ->getJson('/Api/Resellers/' . $this->resellerId . '/QuotaLedger');
        $res->assertStatus(200);
        $this->assertSame(500, $res->json('Results.0.Delta'));
        $this->assertSame('QuotaGranted', $res->json('Results.0.LedgerAction'));
    }

    public function test_quota_requests_bare_returns_401(): void
    {
        $res = $this->getJson('/Api/Resellers/' . $this->resellerId . '/QuotaRequests');
        $this->assertLaraException($res, 'AuthUnauthorized', 401);
    }

    public function test_quota_requests_admin_binds_alim_shard_and_returns_list(): void
    {
        $alimId = (int) DB::connection('root')->table('Resellers')->insertGetId([
            'ResellerName' => 'Alim',
            'ResellerSlug' => 'alim',
            'ContactEmail' => 'ops@alim.test',
            'IsActive' => 1,
        ]);
        $res = $this->withHeader('Authorization', 'Bearer ' . $this->adminBearer)
            ->getJson('/Api/Resellers/' . $alimId . '/QuotaRequests');
        $res->assertStatus(200);
        $this->assertIsArray($res->json('Results'));
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
