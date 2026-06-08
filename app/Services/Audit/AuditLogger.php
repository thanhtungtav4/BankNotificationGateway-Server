<?php

namespace App\Services\Audit;

use App\Models\AuditLog;
use Illuminate\Http\Request;

final class AuditLogger
{
    public function log(string $action, ?object $subject = null, array $metadata = [], ?Request $request = null): void
    {
        $user = $request?->user();

        AuditLog::query()->create([
            'tenant_id' => $user?->tenant_id ?? $metadata['tenant_id'] ?? null,
            'actor_type' => $user ? $user::class : null,
            'actor_id' => $user?->id,
            'action' => $action,
            'subject_type' => $subject ? $subject::class : null,
            'subject_id' => $subject?->id ?? null,
            'metadata' => $metadata,
            'ip' => $request?->ip(),
        ]);
    }
}
