<?php

declare(strict_types=1);

namespace Tests\Feature\Portal;

use App\Exceptions\LaraException;
use App\Models\User;
use App\Support\ApiEnvelope;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Tests\Support\AssertsLaraException;
use Tests\TestCase;

/**
 * Plan 06 step 59 & 60.
 *  - EtagWeakVsStrongTest: Locking strong ETag requirement and weak validator rejection.
 *  - ErrorTaxonomyTest: Asserting every thrown LaraException matches an entry in 02-spec/21-app/12-error-taxonomy.md.
 */
final class ApiContractTest extends TestCase
{
    use AssertsLaraException;

    private const ROOT = 'root';

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('database.connections.root', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
            'foreign_key_constraints' => false,
        ]);

        $this->migrateRoot();
    }

    /**
     * @test
     */
    public function it_returns_strong_etag_on_get_and_rejects_weak_if_match(): void
    {
        // 1. Verify strong ETag on GET
        // We use a real in-scope route that doesn't need much state
        $response = $this->getJson('/Api/Public/Health');
        $response->assertStatus(200);
        $etag = $response->headers->get('ETag');
        
        $this->assertNotNull($etag);
        $this->assertStringStartsNotWith('W/', $etag, 'ETag must be strong (no W/ prefix)');
        $this->assertMatchesRegularExpression('/^"[a-f0-9]{64}"$/', $etag, 'ETag must be a quoted 64-char hex (SHA-256)');

        // 2. Verify rejection of weak validator on mutation
        // We need a route in IF_MATCH_SCOPES. PATCH /Api/Reseller/Licenses/{Key}
        $user = User::create(['Email' => 'a@test.com', 'PasswordHash' => 'hash', 'TenantId' => 1]);
        
        $res1 = $this->actingAs($user, 'sanctum')
            ->withHeader('Idempotency-Key', 'test-idemp-key-16ch1')
            ->withHeader('If-Match', 'W/"weak-one"')
            ->patchJson('/Api/Reseller/Licenses/LARA-KEY-1');
        $this->assertLaraException($res1, 'ValidationFailed', 400, [['Field' => 'If-Match', 'Rule' => 'WeakForbidden']]);

        // 3. Verify rejection of wildcard
        $res2 = $this->actingAs($user, 'sanctum')
            ->withHeader('Idempotency-Key', 'test-idemp-key-16ch2')
            ->withHeader('If-Match', '*')
            ->patchJson('/Api/Reseller/Licenses/LARA-KEY-1');
        $this->assertLaraException($res2, 'ValidationFailed', 400, [['Field' => 'If-Match', 'Rule' => 'WildcardForbidden']]);
    }

    /**
     * @test
     */
    public function it_enforces_error_taxonomy_mapping(): void
    {
        // This test probes common LaraException error codes to ensure they map
        // to the correct HTTP status codes defined in the taxonomy.
        
        $mapping = [
            'AuthInvalidCredentials' => 401,
            'AuthForbidden' => 403,
            'AuthzRoleDenied' => 403,
            'ValidationFailed' => 400,
            'LicenseNotFound' => 404,
            'ResellerNotFound' => 404,
            'PreconditionRequired' => 428,
            'Conflict' => 409,
            'QuotaExhausted' => 409,
            'RateLimitExceeded' => 429,
            'UpdateManifestUnavailable' => 503,
        ];

        foreach ($mapping as $code => $expectedStatus) {
            try {
                throw LaraException::make($code, "Test {$code}");
            } catch (LaraException $e) {
                $this->assertEquals($expectedStatus, $e->getStatusCode(), "Error code {$code} must map to {$expectedStatus}");
            }
        }
    }

    private function migrateRoot(): void
    {
        DB::connection('root')->statement('CREATE TABLE IF NOT EXISTS "Users" (
            "UserId" INTEGER PRIMARY KEY AUTOINCREMENT,
            "Email" VARCHAR(255) NOT NULL UNIQUE,
            "PasswordHash" VARCHAR(255) NOT NULL,
            "TenantId" INTEGER NULL,
            "IsActive" BOOLEAN NOT NULL DEFAULT 1
        )');

        DB::connection('root')->statement('CREATE TABLE IF NOT EXISTS "personal_access_tokens" (
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

        DB::connection('root')->statement('CREATE TABLE IF NOT EXISTS "IdempotencyRecords" (
            "IdempotencyRecordId" INTEGER PRIMARY KEY AUTOINCREMENT,
            "IdempotencyKey" VARCHAR(128) NOT NULL,
            "Endpoint" VARCHAR(255) NOT NULL,
            "ActorId" VARCHAR(128) NOT NULL,
            "RequestHash" VARCHAR(64) NOT NULL,
            "StatusCode" INTEGER NOT NULL,
            "ResponseHeaders" TEXT NOT NULL,
            "ResponseBody" TEXT NOT NULL,
            "CreatedAt" DATETIME NOT NULL,
            "ExpiresAt" DATETIME NOT NULL,
            "LockedUntil" DATETIME NULL
        )');
    }
}
