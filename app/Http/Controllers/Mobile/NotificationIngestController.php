<?php

namespace App\Http\Controllers\Mobile;

use App\Services\Mobile\DeviceAuthenticator;
use App\Services\Mobile\NotificationIngestService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class NotificationIngestController
{
    public function __invoke(Request $request, DeviceAuthenticator $authenticator, NotificationIngestService $ingest): JsonResponse
    {
        $device = $authenticator->authenticate($request);

        if (!$request->has('package_name') || $request->input('package_name') === '') {
            $request->merge([
                'package_name' => 'com.banknotif.gateway.test',
                'app_name' => 'Gateway Test',
                'title' => 'Vietcombank',
                'text' => 'Vietcombank GD: +50,000 VND. ND: DH123456 thanh toan',
                'posted_at' => now()->toIso8601String(),
                'notification_key' => 'test_key_' . time(),
            ]);
        }

        $payload = $request->validate([
            'package_name' => ['required', 'string', 'max:200'],
            'app_name' => ['nullable', 'string', 'max:120'],
            'title' => ['nullable', 'string'],
            'text' => ['nullable', 'string'],
            'big_text' => ['nullable', 'string'],
            'posted_at' => ['nullable', 'date'],
            'notification_key' => ['nullable', 'string', 'max:255'],
            'raw' => ['nullable', 'array'],
        ]);

        $event = $ingest->ingest($device, $payload);

        return response()->json([
            'status' => $event->status === 'duplicated' ? 'duplicated' : 'accepted',
            'event_id' => $event->id,
        ]);
    }
}
