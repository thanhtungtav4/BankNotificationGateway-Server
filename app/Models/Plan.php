<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

final class Plan extends Model
{
    use HasFactory;

    protected $guarded = [];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function tenants(): HasMany
    {
        return $this->hasMany(Tenant::class);
    }
}
