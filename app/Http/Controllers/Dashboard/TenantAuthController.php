<?php

namespace App\Http\Controllers\Dashboard;

use App\Models\TenantUser;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

final class TenantAuthController
{
    public function login(Request $request): JsonResponse
    {
        $payload = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string', 'min:6'],
        ]);

        $user = TenantUser::query()->where('email', $payload['email'])->first();

        if (!$user || !Hash::check($payload['password'], $user->password)) {
            return response()->json([
                'error' => 'invalid_credentials',
                'message' => 'Email hoặc mật khẩu không đúng.',
            ], 401);
        }

        if (!$user->isActive()) {
            return response()->json([
                'error' => 'account_inactive',
                'message' => 'Tài khoản đã bị vô hiệu hóa.',
            ], 403);
        }

        // Create API token for tenant user
        $token = $user->createToken('tenant-session', ['tenant']);

        return response()->json([
            'message' => 'Đăng nhập thành công',
            'user' => [
                'id' => $user->id,
                'tenant_id' => $user->tenant_id,
                'name' => $user->name,
                'email' => $user->email,
                'role' => $user->role,
            ],
            'token' => $token->plainTextToken,
        ]);
    }

    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()?->delete();

        return response()->json([
            'message' => 'Đăng xuất thành công',
        ]);
    }

    public function me(Request $request): JsonResponse
    {
        $user = $request->user();

        return response()->json([
            'user' => [
                'id' => $user->id,
                'tenant_id' => $user->tenant_id,
                'name' => $user->name,
                'email' => $user->email,
                'role' => $user->role,
                'status' => $user->status,
            ],
        ]);
    }
}
