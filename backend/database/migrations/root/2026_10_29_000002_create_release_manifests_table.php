<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Migration creating ReleaseManifests in the Root DB.
 *
 * One row per signed release manifest for a product. The manifest
 * itself is Ed25519 signed, so a client can verify its own code
 * files against it without trusting an unsigned hash list.
 */
return new class extends Migration
{
    private const CONN = 'root';

    public function up(): void
    {
        Schema::connection(self::CONN)->create('ReleaseManifests', function (Blueprint $table): void {
            $table->id('ReleaseManifestId');
            $table->string('productSlug', 100);
            $table->string('releaseVersion', 50);
            $table->string('minVersion', 50)->nullable();
            $table->string('manifestSha256', 64);
            $table->string('signature', 200);
            $table->string('kid', 50);
            $table->text('filesJson');
            $table->boolean('isActive')->default(true);
            $table->timestamps();
            $table->unique(['productSlug', 'releaseVersion']);
        });
    }

    public function down(): void
    {
        Schema::connection(self::CONN)->dropIfExists('ReleaseManifests');
    }
};
