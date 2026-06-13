<?php

namespace App\Http\Controllers\Dashboard;

use App\Models\AdminUser;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

final class AuthController
{
    public function login(Request $request): JsonResponse
    {
        $payload = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string', 'min:6'],
        ]);

        $admin = AdminUser::query()->where('email', $payload['email'])->first();

        if (!$admin || !Hash::check($payload['password'], $admin->password)) {
            return response()->json([
                'error' => 'invalid_credentials',
                'message' => 'Email hoặc mật khẩu không đúng.',
            ], 401);
        }

        if (!$admin->isActive()) {
            return response()->json([
                'error' => 'account_inactive',
                'message' => 'Tài khoản đã bị vô hiệu hóa.',
            ], 403);
        }

        // Update last login
        $admin->update(['last_login_at' => now()]);

        // Create API token
        $token = $admin->createToken('admin-session', ['admin']);

        return response()->json([
            'message' => 'Đăng nhập thành công',
            'admin' => [
                'id' => $admin->id,
                'name' => $admin->name,
                'email' => $admin->email,
                'role' => $admin->role,
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
        $admin = $request->user();

        return response()->json([
            'admin' => [
                'id' => $admin->id,
                'name' => $admin->name,
                'email' => $admin->email,
                'role' => $admin->role,
                'status' => $admin->status,
                'last_login_at' => $admin->last_login_at?->toIso8601String(),
            ],
        ]);
    }

    public function register(Request $request): JsonResponse
    {
        $payload = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email', 'unique:admin_users,email'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
            'role' => ['nullable', 'string', 'in:admin,super_admin'],
        ]);

        $admin = AdminUser::query()->create([
            'name' => $payload['name'],
            'email' => $payload['email'],
            'password' => Hash::make($payload['password']),
            'role' => $payload['role'] ?? 'admin',
            'status' => 'active',
        ]);

        return response()->json([
            'message' => 'Tạo admin thành công',
            'admin' => [
                'id' => $admin->id,
                'name' => $admin->name,
                'email' => $admin->email,
                'role' => $admin->role,
            ],
        ], 201);
    }
}