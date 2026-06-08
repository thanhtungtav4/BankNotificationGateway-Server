<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

final class NotificationEvent extends Model
{
    protected $guarded = [];

    protected $casts = [
        'posted_at' => 'datetime',
        'received_at' => 'datetime',
        'raw_payload' => 'array',
    ];

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function device(): BelongsTo
    {
        return $this->belongsTo(Device::class);
    }

    public function parsedTransaction(): HasOne
    {
        return $this->hasOne(ParsedTransaction::class);
    }
}
