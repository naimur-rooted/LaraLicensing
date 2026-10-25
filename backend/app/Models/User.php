<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Laravel\Sanctum\HasApiTokens;

/**
 * Plan 06 step 34 + step 43 (login substrate). Root-DB user row.
 *
 * Backed by the Root `Users` table created in
 * `2026_07_18_000001_create_root_identity_tables.php`. Timestamps are
 * managed manually because column names are PascalCase.
 *
 * TenantId is the reseller anchor: NULL means Root-scope (SuperAdmin
 * or global Admin); non-NULL points at `Resellers.ResellerId`.
 */
final class User extends Authenticatable
{
    use HasApiTokens;

    protected $connection = 'root';
    protected $table = 'Users';
    protected $primaryKey = 'UserId';
    public $timestamps = false;

    protected $fillable = [
        'Email',
        'PasswordHash',
        'TenantId',
        'IsActive',
    ];

    protected $hidden = [
        'PasswordHash',
    ];

    protected $casts = [
        'UserId' => 'int',
        'TenantId' => 'int',
        'IsActive' => 'bool',
        'CreatedAt' => 'datetime',
        'UpdatedAt' => 'datetime',
        'DeletedAt' => 'datetime',
    ];

    public function getAuthPassword(): string
    {
        return (string) ($this->attributes['PasswordHash'] ?? $this->attributes['password_hash'] ?? $this->attributes['password'] ?? '');
    }

    public function getPasswordAttribute(): ?string
    {
        return (string) ($this->attributes['PasswordHash'] ?? $this->attributes['password_hash'] ?? $this->attributes['password'] ?? '');
    }

    public function getEmailAttribute(): ?string
    {
        return (string) ($this->attributes['Email'] ?? $this->attributes['email'] ?? '');
    }

    public function getIdAttribute(): ?int
    {
        $id = $this->attributes['UserId'] ?? $this->attributes['user_id'] ?? $this->attributes['id'] ?? null;
        return $id !== null ? (int) $id : null;
    }

    public function setEmailAttribute($value): void
    {
        $this->attributes['Email'] = (string) $value;
    }

    public function setPasswordAttribute($value): void
    {
        $this->attributes['PasswordHash'] = (string) $value;
    }

    public function getPrimaryRole(): string
    {
        try {
            $roleName = \Illuminate\Support\Facades\DB::connection('root')
                ->table('UserRoles as ur')
                ->join('Roles as r', 'r.RoleId', '=', 'ur.RoleId')
                ->where('ur.UserId', (int) $this->getKey())
                ->value('r.RoleName');

            return $roleName ?: 'EndUser';
        } catch (\Throwable) {
            return 'EndUser';
        }
    }

    public function getCapabilities(): array
    {
        $role = $this->getPrimaryRole();
        if ($role === 'Admin' || $role === 'SuperAdmin') {
            return ['*'];
        }
        if ($role === 'Reseller') {
            return ['reseller.*'];
        }
        return ['portal.*'];
    }
}
