<?php

namespace App\Services\Mobile;

use App\Models\Device;
use App\Services\Security\ReplayProtectionService;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;

final class DeviceAuthenticator
{
    public function __construct(
        private readonly DeviceSignatureService $signatureService,
        private readonly ReplayProtectionService $replayProtection,
    ) {}

    public function authenticate(Request $request): Device
    {
        $deviceId = (string) $request->header('X-Device-Id', '');
        $timestamp = (string) $request->header('X-Timestamp', '');
        $signature = (string) $request->header('X-Signature', '');

        if ($deviceId === '' || $timestamp === '' || $signature === '') {
            throw new HttpResponseException(response()->json(['message' => 'Missing device authentication headers'], 401));
        }

        $device = Device::query()
            ->with('tenant')
            ->where('device_id', $deviceId)
            ->where('status', 'active')
            ->first();

        if (! $device || ! $device->secret_encrypted) {
            throw new HttpResponseException(response()->json(['message' => 'Device not active'], 401));
        }

        $this->replayProtection->assertFresh($timestamp, (int) config('bank_gateway.mobile_signature_tolerance_seconds', 300));

        $plainSecret = Crypt::decryptString($device->secret_encrypted);
        if (! $this->signatureService->verify($timestamp, $request->getContent(), $plainSecret, $signature)) {
            throw new HttpResponseException(response()->json(['message' => 'Invalid signature'], 401));
        }

        return $device;
    }
}
