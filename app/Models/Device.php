<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

final class Device extends Model
{
    use HasFactory;

    protected $guarded = [];

    protected $casts = [
        'last_seen_at' => 'datetime',
        'is_charging' => 'boolean',
        'listener_enabled' => 'boolean',
    ];

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function allowedPackages(): HasMany
    {
        return $this->hasMany(DeviceAllowedPackage::class);
    }

    public function notificationEvents(): HasMany
    {
        return $this->hasMany(NotificationEvent::class);
    }
}
