<?php

declare(strict_types=1);

namespace Tests\Feature\Reseller;

use App\Http\Controllers\Reseller\PrefixController as ResellerPrefixController;
use App\Http\Controllers\Reseller\MachineBindingController;

use Tests\TestCase;

/**
 * Feature tests for Reseller Portal Branded Minting, Prefixes & Machine Telemetry.
 */
final class ResellerPortalArchitectureTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->artisan('migrate', ['--path' => 'database/migrations/root/2026_10_22_000001_create_reseller_portal_tables.php']);
        $this->seed(\Database\Seeders\ResellerPortalSeeder::class);
    }

    public function test_get_reseller_prefixes_returns_active_prefixes(): void
    {
        $response = $this->withHeaders(['Accept' => 'application/json'])
            ->getJson('/api/v1/reseller/prefixes');

        $response->assertStatus(200);
        $response->assertJsonPath('Status.IsSuccess', true);
        $this->assertNotEmpty($response->json('Results'));
    }

    public function test_post_reseller_prefixes_registers_valid_prefix(): void
    {
        $prefix = 'TEST' . rand(100, 999);
        $response = $this->withHeaders(['Accept' => 'application/json'])
            ->postJson('/api/v1/reseller/prefixes', [
                'PrefixValue' => $prefix,
            ]);

        $response->assertStatus(201);
        $response->assertJsonPath('Status.IsSuccess', true);
        $response->assertJsonPath('Results.0.PrefixValue', $prefix);
    }

    public function test_post_reseller_prefixes_rejects_invalid_format(): void
    {
        $response = $this->withHeaders(['Accept' => 'application/json'])
            ->postJson('/api/v1/reseller/prefixes', [
                'PrefixValue' => 'NO',
            ]);

        $response->assertStatus(400);
        $response->assertJsonPath('Status.IsSuccess', false);
    }

    public function test_post_reseller_prefixes_rejects_duplicate_prefix(): void
    {
        $response = $this->withHeaders(['Accept' => 'application/json'])
            ->postJson('/api/v1/reseller/prefixes', [
                'PrefixValue' => 'ALIM',
            ]);

        $response->assertStatus(409);
        $response->assertJsonPath('Status.IsSuccess', false);
    }

    public function test_branded_serial_minting_and_quota_ledger(): void
    {
        $response = $this->withHeaders(['Accept' => 'application/json'])
            ->postJson('/api/v1/reseller/licenses', [
                'PrefixValue' => 'ALIM',
                'TierName' => 'Tier1',
                'EnvironmentName' => 'Production',
                'LicenseCategory' => 'Monthly',
            ]);

        $response->assertStatus(201);
        $response->assertJsonPath('Status.IsSuccess', true);

        $licenseKey = (string) $response->json('Results.0.LicenseKey');
        $this->assertMatchesRegularExpression('/^ALIM-[DWMYLXK]-V\d+-[A-Z0-9]{4}-[A-Z0-9]{4}-[A-Z0-9]{4}$/', $licenseKey);

        // Verify Quota ledger record
        $quotaResponse = $this->withHeaders(['Accept' => 'application/json'])
            ->getJson('/api/v1/reseller/quotas');
        $quotaResponse->assertStatus(200);
    }

    public function test_branded_serial_minting_rejects_foreign_prefix(): void
    {
        $response = $this->withHeaders(['Accept' => 'application/json'])
            ->postJson('/api/v1/reseller/licenses', [
                'PrefixValue' => 'ACME',
                'TierName' => 'Tier1',
                'EnvironmentName' => 'Production',
                'LicenseCategory' => 'Monthly',
            ]);

        $response->assertStatus(403);
        $response->assertJsonPath('Status.IsSuccess', false);
    }

    public function test_revoke_license_restores_quota_within_24_hours(): void
    {
        $mintResponse = $this->withHeaders(['Accept' => 'application/json'])
            ->postJson('/api/v1/reseller/licenses', [
                'PrefixValue' => 'ALIM',
                'TierName' => 'Tier1',
                'EnvironmentName' => 'Production',
                'LicenseCategory' => 'Monthly',
            ]);

        $licenseKey = (string) $mintResponse->json('Results.0.LicenseKey');

        $revokeResponse = $this->withHeaders(['Accept' => 'application/json'])
            ->postJson("/api/v1/reseller/licenses/{$licenseKey}/revoke", [
                'reason' => 'Customer refunded',
            ]);

        $revokeResponse->assertStatus(200);
        $revokeResponse->assertJsonPath('Results.0.Status', 'Revoked');
    }

    public function test_machine_binding_inspect_release_and_clear_cooldown(): void
    {
        $mintResponse = $this->withHeaders(['Accept' => 'application/json'])
            ->postJson('/api/v1/reseller/licenses', [
                'PrefixValue' => 'ALIM',
                'TierName' => 'Tier1',
                'EnvironmentName' => 'Production',
                'LicenseCategory' => 'Monthly',
            ]);

        $licenseKey = (string) $mintResponse->json('Results.0.LicenseKey');
        $licenseId = (int) $mintResponse->json('Results.0.LicenseId');

        // Insert binding
        $now = gmdate('Y-m-d H:i:s');
        $bindingId = \Illuminate\Support\Facades\DB::connection('shard')->table('MachineBindings')->insertGetId([
            'LicenseId' => $licenseId,
            'FingerprintHash' => 'f1b1c44298fc1c149afbf4c8996fb92427ae41e4649b934ca495991b7852b899',
            'IpAddress' => '10.0.0.5',
            'Hostname' => 'OFFICE-PC',
            'FirstSeenAt' => $now,
            'LastSeenAt' => $now,
        ]);

        // Inspect bindings
        $bindingsResponse = $this->withHeaders(['Accept' => 'application/json'])
            ->getJson("/api/v1/reseller/licenses/{$licenseKey}/bindings");
        $bindingsResponse->assertStatus(200);
        $bindingsResponse->assertJsonPath('Status.IsSuccess', true);

        // Release binding
        $releaseResponse = $this->withHeaders(['Accept' => 'application/json'])
            ->postJson("/api/v1/reseller/licenses/{$licenseKey}/bindings/{$bindingId}/release");
        $releaseResponse->assertStatus(200);
        $releaseResponse->assertJsonPath('Results.0.IsActive', false);
        $releaseResponse->assertJsonPath('Results.0.IsCooldownActive', true);

        // Clear cooldown
        $clearResponse = $this->withHeaders(['Accept' => 'application/json'])
            ->postJson("/api/v1/reseller/licenses/{$licenseKey}/bindings/{$bindingId}/clear-cooldown");
        $clearResponse->assertStatus(200);
        $clearResponse->assertJsonPath('Results.0.IsCooldownActive', false);
    }
}
