<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Migration creating PricingPlanPriceVersions in the Root DB.
 *
 * One row per price version of a PricingPlan. The live PricingPlans
 * row is the current version; this table keeps every previous price
 * so a price change never overwrites history.
 */
return new class extends Migration
{
    private const CONN = 'root';

    public function up(): void
    {
        Schema::connection(self::CONN)->create('PricingPlanPriceVersions', function (Blueprint $table): void {
            $table->id('PricingPlanPriceVersionId');
            $table->unsignedBigInteger('PricingPlanId');
            $table->unsignedInteger('versionNumber');
            $table->string('planCode', 32);
            $table->string('planName', 100);
            $table->string('billingInterval', 20);
            $table->unsignedInteger('retailPriceCents');
            $table->unsignedInteger('discountedPriceCents');
            $table->unsignedSmallInteger('allowedMachineSlots');
            $table->boolean('isBestValue')->default(false);
            $table->boolean('isActive')->default(true);
            $table->timestamp('effectiveFrom')->nullable();
            $table->timestamps();
            $table->unique(['PricingPlanId', 'versionNumber']);
        });
    }

    public function down(): void
    {
        Schema::connection(self::CONN)->dropIfExists('PricingPlanPriceVersions');
    }
};
