<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Migration creating Reseller Portal tables in root database.
 */
return new class extends Migration
{
    private const CONN = 'root';

    // lint-allow: function-length max=30 reason="Schema setup for multiple reseller tables"
    public function up(): void
    {
        $this->createResellersTable();
        $this->createPrefixesTable();
        $this->createQuotasTables();
        $this->createLedgerTables();
        $this->createLicensesTable();
        $this->createSerialsTable();
        $this->createMachineBindingsTable();
        $this->createQuotaRequestsTable();
    }

    private function createResellersTable(): void
    {
        if (Schema::connection(self::CONN)->hasTable('Resellers')) {
            return;
        }

        Schema::connection(self::CONN)->create('Resellers', function (Blueprint $table): void {
            $table->id('ResellerId');
            $table->string('ResellerName', 128)->unique();
            $table->string('ResellerSlug', 64)->unique();
            $table->string('ContactEmail', 255);
            $table->boolean('IsActive')->default(true);
            $table->dateTime('CreatedAt')->nullable();
            $table->dateTime('UpdatedAt')->nullable();
        });
    }

    private function createPrefixesTable(): void
    {
        if (Schema::connection(self::CONN)->hasTable('Prefixes')) {
            return;
        }

        Schema::connection(self::CONN)->create('Prefixes', function (Blueprint $table): void {
            $table->id('PrefixId');
            $table->unsignedBigInteger('ResellerId');
            $table->string('PrefixValue', 12)->unique();
            $table->boolean('IsActive')->default(true);
            $table->dateTime('CreatedAt')->nullable();
            $table->dateTime('UpdatedAt')->nullable();
            $table->index('ResellerId');
        });
    }

    private function createQuotasTables(): void
    {
        $this->buildQuotaTable('ResellerQuotas');
        $this->buildQuotaTable('Quotas');
    }

    private function buildQuotaTable(string $tableName): void
    {
        if (Schema::connection(self::CONN)->hasTable($tableName)) {
            return;
        }

        Schema::connection(self::CONN)->create($tableName, function (Blueprint $table): void {
            $table->id('Id');
            $table->unsignedBigInteger('ResellerId');
            $table->unsignedSmallInteger('LicenseCategoryId')->default(3);
            $table->unsignedSmallInteger('LicenseTierId')->default(1);
            $table->unsignedBigInteger('LicensesGranted')->default(500);
            $table->unsignedBigInteger('LicensesConsumed')->default(0);
            $table->dateTime('PeriodStart')->nullable();
            $table->dateTime('PeriodEnd')->nullable();
            $table->dateTime('CreatedAt')->nullable();
            $table->dateTime('UpdatedAt')->nullable();
            $table->index(['ResellerId', 'LicenseCategoryId', 'LicenseTierId']);
        });
    }

    private function createLedgerTables(): void
    {
        $this->buildLedgerTable('ResellerQuotaLedger');
        $this->buildLedgerTable('LicenseLedger');
    }

    private function buildLedgerTable(string $tableName): void
    {
        if (Schema::connection(self::CONN)->hasTable($tableName)) {
            return;
        }

        Schema::connection(self::CONN)->create($tableName, function (Blueprint $table): void {
            $table->id('Id');
            $table->unsignedBigInteger('ResellerId');
            $table->unsignedSmallInteger('LicenseCategoryId')->nullable();
            $table->unsignedSmallInteger('LicenseTierId')->nullable();
            $table->string('TierName', 32)->nullable();
            $table->integer('Delta');
            $table->string('Action', 32);
            $table->string('LedgerAction', 32)->nullable();
            $table->string('ReferenceId', 64)->nullable();
            $table->unsignedBigInteger('LicenseId')->nullable();
            $table->dateTime('OccurredAt')->nullable();
            $table->dateTime('CreatedAt')->nullable();
            $table->dateTime('UpdatedAt')->nullable();
        });
    }

    private function createLicensesTable(): void
    {
        if (Schema::connection(self::CONN)->hasTable('Licenses')) {
            return;
        }

        Schema::connection(self::CONN)->create('Licenses', function (Blueprint $table): void {
            $table->id('LicenseId');
            $table->string('LicenseKey', 128)->unique();
            $table->string('PrefixValue', 12);
            $table->unsignedBigInteger('ResellerId');
            $table->unsignedBigInteger('IssuedByUserId')->default(1);
            $table->string('IssuerActorType', 32)->default('Reseller');
            $table->unsignedSmallInteger('LicenseCategoryId')->default(3);
            $table->string('TierName', 32)->default('Tier1');
            $table->string('EnvironmentName', 32)->default('Production');
            $table->string('ProductVersion', 16)->default('V1');
            $table->string('Status', 32)->default('Active');
            $table->dateTime('IssuedAt')->nullable();
            $table->dateTime('ExpiresAt')->nullable();
            $table->dateTime('RevokedAt')->nullable();
            $table->unsignedBigInteger('RevokedByUserId')->nullable();
            $table->string('RevokeReason', 255)->nullable();
            $table->unsignedBigInteger('ResellerQuotaLedgerId')->nullable();
            $table->integer('Version')->default(1);
            $table->dateTime('CreatedAt')->nullable();
            $table->dateTime('UpdatedAt')->nullable();
            $table->index(['ResellerId', 'Status']);
        });
    }

    private function createSerialsTable(): void
    {
        if (Schema::connection(self::CONN)->hasTable('Serials')) {
            return;
        }

        Schema::connection(self::CONN)->create('Serials', function (Blueprint $table): void {
            $table->id('SerialId');
            $table->unsignedBigInteger('LicenseId');
            $table->string('SerialValue', 128)->unique();
            $table->string('DeviceIdHash', 64)->nullable();
            $table->string('EnvironmentName', 32)->default('Production');
            $table->string('FeaturePayloadHash', 64)->nullable();
            $table->string('IdempotencyKey', 64)->nullable();
            $table->boolean('IsRevoked')->default(false);
            $table->dateTime('IssuedAt')->nullable();
            $table->dateTime('LastVerifiedAt')->nullable();
            $table->dateTime('CreatedAt')->nullable();
            $table->dateTime('UpdatedAt')->nullable();
            $table->index('LicenseId');
        });
    }

    private function createMachineBindingsTable(): void
    {
        if (Schema::connection(self::CONN)->hasTable('MachineBindings')) {
            return;
        }

        Schema::connection(self::CONN)->create('MachineBindings', function (Blueprint $table): void {
            $table->id('MachineBindingId');
            $table->unsignedBigInteger('LicenseId');
            $table->string('FingerprintHash', 64);
            $table->string('IpAddress', 45)->nullable();
            $table->string('Hostname', 128)->nullable();
            $table->dateTime('FirstSeenAt')->nullable();
            $table->dateTime('LastSeenAt')->nullable();
            $table->dateTime('ReleasedAt')->nullable();
            $table->dateTime('RebindCooldownUntil')->nullable();
            $table->dateTime('CreatedAt')->nullable();
            $table->dateTime('UpdatedAt')->nullable();
            $table->index(['LicenseId', 'ReleasedAt']);
            $table->index(['FingerprintHash', 'RebindCooldownUntil']);
        });
    }

    private function createQuotaRequestsTable(): void
    {
        if (Schema::connection(self::CONN)->hasTable('QuotaRequests')) {
            return;
        }

        Schema::connection(self::CONN)->create('QuotaRequests', function (Blueprint $table): void {
            $table->id('Id');
            $table->unsignedBigInteger('ResellerId');
            $table->unsignedSmallInteger('LicenseCategoryId')->default(3);
            $table->unsignedSmallInteger('LicenseTierId')->default(1);
            $table->integer('RequestedDelta');
            $table->integer('ApprovedDelta')->default(0);
            $table->string('Status', 32)->default('Pending');
            $table->text('Justification')->nullable();
            $table->text('DenialReason')->nullable();
            $table->unsignedBigInteger('DecidedByUserId')->nullable();
            $table->dateTime('CreatedAt')->nullable();
            $table->dateTime('UpdatedAt')->nullable();
        });
    }

    public function down(): void
    {
        Schema::connection(self::CONN)->dropIfExists('MachineBindings');
        Schema::connection(self::CONN)->dropIfExists('Serials');
        Schema::connection(self::CONN)->dropIfExists('Licenses');
        Schema::connection(self::CONN)->dropIfExists('QuotaRequests');
        Schema::connection(self::CONN)->dropIfExists('ResellerQuotaLedger');
        Schema::connection(self::CONN)->dropIfExists('LicenseLedger');
        Schema::connection(self::CONN)->dropIfExists('ResellerQuotas');
        Schema::connection(self::CONN)->dropIfExists('Quotas');
        Schema::connection(self::CONN)->dropIfExists('Prefixes');
        Schema::connection(self::CONN)->dropIfExists('Resellers');
    }
};
