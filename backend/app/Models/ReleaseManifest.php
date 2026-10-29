<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Root-DB signed release manifest row.
 */
final class ReleaseManifest extends Model
{
    protected $connection = 'root';
    protected $table = 'ReleaseManifests';
    protected $primaryKey = 'ReleaseManifestId';

    protected $fillable = [
        'productSlug',
        'releaseVersion',
        'minVersion',
        'manifestSha256',
        'signature',
        'kid',
        'filesJson',
        'isActive',
    ];

    protected $casts = [
        'isActive' => 'bool',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];
}
