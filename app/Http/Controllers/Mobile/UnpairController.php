<?php

namespace App\Http\Controllers\Mobile;

use App\Models\Device;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;

final class UnpairController
{
    public function __invoke(Request $request): JsonResponse
    {
        $payload = $request->validate([
            'device_id' => ['required', 'string'],
            'device_secret' => ['required', 'string'],
        ]);

        $deviceId = $payload['device_id'];
        $deviceSecret = $payload['device_secret'];

        $device = Device::query()
            ->where('device_id', $deviceId)
            ->where('status', 'active')
            ->first();

        if (!$device) {
            return response()->json([
                'error' => 'device_not_found',
                'message' => 'Thiết bị không tìm thấy hoặc đã bị vô hiệu hóa.',
            ], 404);
        }

        // Verify device secret
        if (!$device->secret_encrypted) {
            return response()->json([
                'error' => 'invalid_device',
                'message' => 'Thiết bị chưa được pair đúng cách.',
            ], 401);
        }

        try {
            $plainSecret = Crypt::decryptString($device->secret_encrypted);
        } catch (\Throwable $e) {
            return response()->json([
                'error' => 'invalid_device',
                'message' => 'Không thể xác minh thiết bị.',
            ], 401);
        }

        if (!hash_equals($plainSecret, $deviceSecret)) {
            return response()->json([
                'error' => 'invalid_credentials',
                'message' => 'Thông tin xác thực không hợp lệ.',
            ], 401);
        }

        // Delete device (cascade will handle related records)
        $device->delete();

        return response()->json([
            'message' => 'Đã hủy pair thiết bị thành công.',
        ]);
    }
}