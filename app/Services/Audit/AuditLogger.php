<?php

namespace App\Services\Audit;

use App\Models\AuditLog;
use Illuminate\Support\Facades\Auth;

final class AuditLogger
{
    public function log(
        string $action,
        ?string $subjectType = null,
        ?int $subjectId = null,
        ?array $metadata = null,
        ?string $ip = null,
        ?int $tenantId = null,
    ): void {
        $actor = $this->resolveActor();

        AuditLog::query()->create([
            'tenant_id' => $tenantId ?? $actor?->tenant_id ?? Auth::id(),
            'actor_type' => $actor ? get_class($actor) : null,
            'actor_id' => $actor?->id,
            'action' => $action,
            'subject_type' => $subjectType,
            'subject_id' => $subjectId,
            'metadata' => $metadata,
            'ip' => $ip ?? request()->ip(),
        ]);
    }

    public function logTenant(string $action, int $tenantId, array $changes = [], ?string $ip = null): void
    {
        $this->log(
            action: $action,
            subjectType: 'tenant',
            subjectId: $tenantId,
            metadata: $changes,
            ip: $ip,
            tenantId: $tenantId,
        );
    }

    public function logDevice(string $action, int $deviceId, int $tenantId, array $changes = [], ?string $ip = null): void
    {
        $this->log(
            action: $action,
            subjectType: 'device',
            subjectId: $deviceId,
            metadata: $changes,
            ip: $ip,
            tenantId: $tenantId,
        );
    }

    public function logWebhook(string $action, int $webhookId, int $tenantId, array $changes = [], ?string $ip = null): void
    {
        $this->log(
            action: $action,
            subjectType: 'tenant_webhook',
            subjectId: $webhookId,
            metadata: $changes,
            ip: $ip,
            tenantId: $tenantId,
        );
    }

    public function logAuth(string $action, ?int $adminId = null, ?string $ip = null): void
    {
        $this->log(
            action: $action,
            subjectType: 'admin_user',
            subjectId: $adminId,
            metadata: null,
            ip: $ip,
        );
    }

    private function resolveActor(): ?object
    {
        if (Auth::check()) {
            return Auth::user();
        }

        return null;
    }
}