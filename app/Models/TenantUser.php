<?php

namespace App\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Laravel\Sanctum\HasApiTokens;
use Illuminate\Notifications\Notifiable;

final class TenantUser extends Authenticatable
{
    use HasApiTokens, Notifiable;

    protected $fillable = [
        'tenant_id',
        'email',
        'name',
        'role',
        'status',
        'password',
    ];

    protected $hidden = [
        'password',
    ];

    protected $casts = [
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public const ROLE_ADMIN = 'admin';
    public const ROLE_VIEWER = 'viewer';

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function isAdmin(): bool
    {
        return $this->role === self::ROLE_ADMIN;
    }

    public function isViewer(): bool
    {
        return $this->role === self::ROLE_VIEWER;
    }

    public function isActive(): bool
    {
        return $this->status === 'active';
    }
}