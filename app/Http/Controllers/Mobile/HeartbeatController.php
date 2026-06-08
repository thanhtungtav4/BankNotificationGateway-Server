<?php

namespace App\Http\Controllers\Mobile;

use App\Services\Mobile\DeviceAuthenticator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class HeartbeatController
{
    public function __invoke(Request $request, DeviceAuthenticator $authenticator): JsonResponse
    {
        $device = $authenticator->authenticate($request);
        $payload = $request->validate([
            'battery_level' => ['nullable', 'integer', 'between:0,100'],
            'is_charging' => ['nullable', 'boolean'],
            'listener_enabled' => ['required', 'boolean'],
            'queue_pending' => ['required', 'integer', 'min:0'],
            'app_version' => ['nullable', 'string', 'max:50'],
            'android_version' => ['nullable', 'string', 'max:50'],
        ]);

        $device->update($payload + [
            'last_seen_at' => now(),
            'last_ip' => $request->ip(),
        ]);

        return response()->json(['status' => 'ok']);
    }
}
