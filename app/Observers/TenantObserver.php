<?php

namespace App\Observers;

use App\Models\Tenant;
use App\Services\Audit\AuditLogger;

final class TenantObserver
{
    public function __construct(
        private readonly AuditLogger $auditLogger,
    ) {}

    public function created(Tenant $tenant): void
    {
        $this->auditLogger->logTenant('created', $tenant->id, [
            'name' => $tenant->name,
            'slug' => $tenant->slug,
            'status' => $tenant->status,
        ]);
    }

    public function updated(Tenant $tenant): void
    {
        $changes = $tenant->getChanges();
        unset($changes['updated_at']);

        if (empty($changes)) {
            return;
        }

        $this->auditLogger->logTenant('updated', $tenant->id, $changes);
    }

    public function deleted(Tenant $tenant): void
    {
        $this->auditLogger->logTenant('deleted', $tenant->id, [
            'name' => $tenant->name,
            'slug' => $tenant->slug,
        ]);
    }
}