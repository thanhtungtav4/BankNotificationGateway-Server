<?php

namespace App\Services\Mobile;

use App\Jobs\ParseNotificationJob;
use App\Models\Device;
use App\Models\DeviceAllowedPackage;
use App\Models\NotificationEvent;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;

final class NotificationIngestService
{
    public function ingest(Device $device, array $payload): NotificationEvent
    {
        $packageName = (string) Arr::get($payload, 'package_name', '');
        abort_if($packageName === '', 422, 'package_name is required');

        $allowed = DeviceAllowedPackage::query()
            ->where('tenant_id', $device->tenant_id)
            ->where('device_id', $device->id)
            ->where('package_name', $packageName)
            ->where('is_active', true)
            ->exists();

        abort_unless($allowed, 403, 'Package is not allowed for this device');

        $eventHash = hash('sha256', implode('|', [
            $packageName,
            (string) Arr::get($payload, 'title', ''),
            (string) Arr::get($payload, 'text', ''),
            (string) Arr::get($payload, 'big_text', ''),
            (string) Arr::get($payload, 'posted_at', ''),
        ]));

        return DB::transaction(function () use ($device, $payload, $packageName, $eventHash): NotificationEvent {
            $notificationKey = $payload['notification_key'] ?? null;

            $existing = NotificationEvent::query()
                ->where('tenant_id', $device->tenant_id)
                ->where('device_id', $device->id)
                ->when($notificationKey, fn ($query) => $query->where('notification_key', $notificationKey))
                ->when(! $notificationKey, fn ($query) => $query->where('event_hash', $eventHash))
                ->first();

            if ($existing) {
                $existing->update(['status' => 'duplicated']);
                return $existing;
            }

            $event = NotificationEvent::query()->create([
                'tenant_id' => $device->tenant_id,
                'device_id' => $device->id,
                'package_name' => $packageName,
                'app_name' => $payload['app_name'] ?? null,
                'title' => $payload['title'] ?? null,
                'text' => $payload['text'] ?? null,
                'big_text' => $payload['big_text'] ?? null,
                'posted_at' => $payload['posted_at'] ?? null,
                'received_at' => now(),
                'notification_key' => $notificationKey,
                'event_hash' => $eventHash,
                'raw_payload' => $payload,
                'status' => 'received',
            ]);

            ParseNotificationJob::dispatch($event->id);

            return $event;
        });
    }
}
