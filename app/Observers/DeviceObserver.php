<?php

namespace App\Observers;

use App\Models\Device;
use App\Services\Audit\AuditLogger;

final class DeviceObserver
{
    public function __construct(
        private readonly AuditLogger $auditLogger,
    ) {}

    public function created(Device $device): void
    {
        $this->auditLogger->logDevice('paired', $device->id, $device->tenant_id, [
            'device_id' => $device->device_id,
            'device_name' => $device->device_name,
            'app_version' => $device->app_version,
        ]);
    }

    public function updated(Device $device): void
    {
        $changes = $device->getChanges();
        unset($changes['updated_at']);

        if (empty($changes)) {
            return;
        }

        // Log specific events
        if (isset($changes['status'])) {
            $this->auditLogger->logDevice(
                $changes['status'] === 'unpaired' ? 'unpaired' : 'status_changed',
                $device->id,
                $device->tenant_id,
                ['new_status' => $changes['status'], 'old_status' => $device->getOriginal('status')]
            );
        }

        if (isset($changes['last_seen_at'])) {
            // Heartbeat - don't log every time, just log periodically
            // This is handled in HeartbeatController instead
        }
    }

    public function deleted(Device $device): void
    {
        $this->auditLogger->logDevice('deleted', $device->id, $device->tenant_id, [
            'device_id' => $device->device_id,
            'device_name' => $device->device_name,
        ]);
    }
}