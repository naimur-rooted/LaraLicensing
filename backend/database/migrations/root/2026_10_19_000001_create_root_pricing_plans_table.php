<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Migration creating PricingPlans table in Root DB.
 */
return new class extends Migration
{
    private const CONN = 'root';

    public function up(): void
    {
        # lint-allow: function-length
        Schema::connection(self::CONN)->create('PricingPlans', function (Blueprint $table): void {
            $table->id('PricingPlanId');
            $table->string('planCode', 32)->unique();
            $table->string('planName', 100);
            $table->string('billingInterval', 20);
            $table->unsignedInteger('retailPriceCents');
            $table->unsignedInteger('discountedPriceCents');
            $table->unsignedSmallInteger('allowedMachineSlots');
            $table->boolean('isBestValue')->default(false);
            $table->boolean('isActive')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::connection(self::CONN)->dropIfExists('PricingPlans');
    }
};
