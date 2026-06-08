<?php

namespace App\Http\Controllers\Dashboard;

use App\Services\Mobile\DevicePairingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class PairingTokenController
{
    public function __invoke(Request $request, DevicePairingService $pairingService): JsonResponse
    {
        $payload = $request->validate([
            'tenant_id' => ['required', 'integer', 'min:1'],
            'server_url' => ['nullable', 'string', 'max:255'],
            'device_name' => ['nullable', 'string', 'max:120'],
        ]);

        $serverUrl = rtrim($payload['server_url'] ?? config('app.url'), '/');
        $pairingToken = $pairingService->createPairingToken((int) $payload['tenant_id'], $payload['device_name'] ?? null);

        return response()->json([
            'server_url' => $serverUrl,
            'pairing_token' => $pairingToken,
            'expires_in_seconds' => 900,
            'qr_payload' => json_encode([
                'server_url' => $serverUrl,
                'pairing_token' => $pairingToken,
            ], JSON_UNESCAPED_SLASHES),
        ]);
    }
}
