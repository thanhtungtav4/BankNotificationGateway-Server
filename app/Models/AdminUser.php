<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Laravel\Sanctum\HasApiTokens;
use Illuminate\Notifications\Notifiable;

final class AdminUser extends Authenticatable
{
    use HasApiTokens, Notifiable, HasFactory;
    protected $fillable = [
        'name',
        'email',
        'password',
        'role',
        'status',
        'last_login_at',
    ];

    protected $hidden = [
        'password',
    ];

    protected $casts = [
        'last_login_at' => 'datetime',
    ];

    public function isActive(): bool
    {
        return $this->status === 'active';
    }

    public function isSuperAdmin(): bool
    {
        return $this->role === 'super_admin';
    }

    public function isAdmin(): bool
    {
        return in_array($this->role, ['super_admin', 'admin'], true);
    }

    public function auditLogs(): HasMany
    {
        return $this->hasMany(AuditLog::class, 'created_by_id')
            ->where('created_by_type', self::class);
    }
}