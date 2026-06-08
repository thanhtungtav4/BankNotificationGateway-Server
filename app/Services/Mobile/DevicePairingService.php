<?php

namespace App\Services\Mobile;

use App\Models\Device;
use App\Models\DevicePairingToken;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

final class DevicePairingService
{
    /** @return array{device_id:string,device_secret:string,server_url:string} */
    public function pair(array $payload): array
    {
        $plainToken = (string) ($payload['pairing_token'] ?? '');
        $pairingToken = $this->findUsableToken($plainToken);

        $deviceId = 'dev_' . strtolower(Str::ulid()->toBase32());
        $deviceSecret = Str::random(64);

        Device::query()->create([
            'tenant_id' => $pairingToken->tenant_id,
            'device_id' => $deviceId,
            'device_name' => $payload['device_name'] ?? $pairingToken->device_name ?? 'Android Device',
            'secret_hash' => Hash::make($deviceSecret),
            'secret_encrypted' => Crypt::encryptString($deviceSecret),
            'status' => 'active',
            'app_version' => $payload['app_version'] ?? null,
            'android_version' => $payload['android_version'] ?? null,
        ]);

        $pairingToken->update(['consumed_at' => now()]);

        return [
            'device_id' => $deviceId,
            'device_secret' => $deviceSecret,
            'server_url' => config('app.url'),
        ];
    }

    public function createPairingToken(int $tenantId, ?string $deviceName = null): string
    {
        $plainToken = 'pair_' . Str::random(48);

        DevicePairingToken::query()->create([
            'tenant_id' => $tenantId,
            'token_hash' => hash('sha256', $plainToken),
            'device_name' => $deviceName,
            'expires_at' => now()->addMinutes(15),
        ]);

        return $plainToken;
    }

    private function findUsableToken(string $plainToken): DevicePairingToken
    {
        // Handle legacy "tenant:N" format tokens
        if (str_starts_with($plainToken, 'tenant:')) {
            $tenantId = (int) substr($plainToken, strlen('tenant:'));
            $token = DevicePairingToken::query()
                ->where('tenant_id', $tenantId)
                ->whereNull('consumed_at')
                ->where('expires_at', '>', now())
                ->orderByDesc('id')
                ->first();

            abort_if(! $token, 422, 'Pairing token is invalid, expired, or already used');

            return $token;
        }

        // Handle new "pair_xxx" format tokens
        $token = DevicePairingToken::query()
            ->where('token_hash', hash('sha256', $plainToken))
            ->first();

        abort_if(! $token || ! $token->isUsable(), 422, 'Pairing token is invalid, expired, or already used');

        return $token;
    }
}
