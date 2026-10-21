<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Migration adding custom features JSON column to ProductDeals table.
 */
return new class extends Migration
{
    private const CONN = 'root';

    public function up(): void
    {
        Schema::connection(self::CONN)->table('ProductDeals', function (Blueprint $table): void {
            $table->text('features')->nullable()->after('isDecoyTarget');
        });
    }

    public function down(): void
    {
        Schema::connection(self::CONN)->table('ProductDeals', function (Blueprint $table): void {
            $table->dropColumn('features');
        });
    }
};
