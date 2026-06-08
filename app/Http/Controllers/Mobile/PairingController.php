<?php

namespace App\Http\Controllers\Mobile;

use App\Services\Mobile\DevicePairingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class PairingController
{
    public function __invoke(Request $request, DevicePairingService $pairing): JsonResponse
    {
        $payload = $request->validate([
            'pairing_token' => ['required', 'string'],
            'device_name' => ['nullable', 'string', 'max:120'],
            'app_version' => ['nullable', 'string', 'max:50'],
            'android_version' => ['nullable', 'string', 'max:50'],
        ]);

        return response()->json($pairing->pair($payload));
    }
}
