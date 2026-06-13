<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

final class TenantWebhook extends Model
{
    protected $guarded = [];

    protected $casts = [
        'is_active' => 'boolean',
        'event_types' => 'array',
        'bank_rules' => 'array',
    ];

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function deliveries(): HasMany
    {
        return $this->hasMany(WebhookDelivery::class, 'webhook_id');
    }
}
