<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Migration creating Products and ProductDeals tables in Root DB.
 * Reference: 02-spec/21-app/51-platform-ecosystem-and-capability-spec.md
 */
return new class extends Migration
{
    private const CONN = 'root';

    public function up(): void
    {
        # lint-allow: function-length
        Schema::connection(self::CONN)->create('Products', function (Blueprint $table): void {
            $table->id('ProductId');
            $table->unsignedBigInteger('userId')->default(1);
            $table->string('productSlug', 64)->nullable()->unique();
            $table->string('ProductCode', 64)->nullable()->unique();
            $table->string('productName', 120);
            $table->string('category', 40)->default('DesktopApp');
            $table->text('description')->nullable();
            $table->text('ed25519PublicKey')->nullable();
            $table->text('ed25519PrivateKey')->nullable();
            $table->string('webhookSecret', 64)->nullable();
            $table->string('webhookUrl', 255)->nullable();
            $table->unsignedSmallInteger('checkIntervalDays')->default(7);
            $table->boolean('isActive')->default(true);
            $table->timestamps();

            $table->index('userId', 'IX_Products_UserId');
        });

        # lint-allow: function-length
        Schema::connection(self::CONN)->create('ProductDeals', function (Blueprint $table): void {
            $table->id('ProductDealId');
            $table->unsignedBigInteger('productId');
            $table->string('dealName', 100);
            $table->string('dealCode', 40);
            $table->string('billingInterval', 20)->default('monthly');
            $table->unsignedInteger('priceCents')->default(0);
            $table->unsignedSmallInteger('allowedMachineSeats')->default(1);
            $table->unsignedSmallInteger('allowedDomains')->default(1);
            $table->boolean('isDecoyTarget')->default(false);
            $table->boolean('isActive')->default(true);
            $table->timestamps();

            $table->foreign('productId')->references('ProductId')->on('Products')->onDelete('cascade');
            $table->index('productId', 'IX_ProductDeals_ProductId');
        });
    }

    public function down(): void
    {
        Schema::connection(self::CONN)->dropIfExists('ProductDeals');
        Schema::connection(self::CONN)->dropIfExists('Products');
    }
};
