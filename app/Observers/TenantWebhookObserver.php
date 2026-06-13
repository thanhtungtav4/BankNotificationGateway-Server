<?php

namespace App\Observers;

use App\Models\TenantWebhook;
use App\Services\Audit\AuditLogger;

final class TenantWebhookObserver
{
    public function __construct(
        private readonly AuditLogger $auditLogger,
    ) {}

    public function created(TenantWebhook $webhook): void
    {
        $this->auditLogger->logWebhook('created', $webhook->id, $webhook->tenant_id, [
            'name' => $webhook->name,
            'url' => $webhook->url,
            'is_active' => $webhook->is_active,
        ]);
    }

    public function updated(TenantWebhook $webhook): void
    {
        $changes = $webhook->getChanges();
        unset($changes['updated_at']);

        if (empty($changes)) {
            return;
        }

        $this->auditLogger->logWebhook('updated', $webhook->id, $webhook->tenant_id, $changes);
    }

    public function deleted(TenantWebhook $webhook): void
    {
        $this->auditLogger->logWebhook('deleted', $webhook->id, $webhook->tenant_id, [
            'name' => $webhook->name,
            'url' => $webhook->url,
        ]);
    }
}