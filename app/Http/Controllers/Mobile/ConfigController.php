<?php

namespace App\Http\Controllers\Mobile;

use App\Services\Mobile\DeviceAuthenticator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class ConfigController
{
    public function __invoke(Request $request, DeviceAuthenticator $authenticator): JsonResponse
    {
        $device = $authenticator->authenticate($request);
        $packages = $device->allowedPackages()
            ->where('is_active', true)
            ->get(['package_name', 'app_name', 'bank_name']);

        return response()->json([
            'allowed_packages' => $packages,
            'heartbeat_interval_seconds' => 300,
        ]);
    }
}
