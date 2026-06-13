<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

final class Tenant extends Model
{
    use HasFactory;

    protected $guarded = [];

    protected $casts = [
        'trial_ends_at' => 'datetime',
        'subscription_ends_at' => 'datetime',
    ];

    public function plan(): BelongsTo
    {
        return $this->belongsTo(Plan::class);
    }

    public function devices(): HasMany
    {
        return $this->hasMany(Device::class);
    }

    public function webhooks(): HasMany
    {
        return $this->hasMany(TenantWebhook::class);
    }

    public function parserConfigs(): HasMany
    {
        return $this->hasMany(TenantParserConfig::class);
    }
}
