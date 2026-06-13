<?php

namespace App\Http\Controllers\Dashboard;

use App\Models\Tenant;
use App\Models\TenantUser;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

final class TenantUserController
{
    public function index(Request $request): JsonResponse
    {
        $tenantId = $request->input('tenant_id');
        $user = $request->user();

        $query = TenantUser::query()->with('tenant');

        if ($user instanceof \App\Models\TenantUser) {
            $query->where('tenant_id', $user->tenant_id);
        } else if ($tenantId) {
            $query->where('tenant_id', $tenantId);
        }

        $users = $query->latest()->get();

        return response()->json(['data' => $users]);
    }

    public function store(Request $request): JsonResponse
    {
        $payload = $request->validate([
            'tenant_id' => ['required', 'integer'],
            'email' => ['required', 'email'],
            'name' => ['required', 'string', 'max:120'],
            'role' => ['nullable', 'string', 'in:admin,viewer'],
            'password' => ['required', 'string', 'min:6'],
        ]);

        $user = $request->user();
        if ($user instanceof \App\Models\TenantUser) {
            if ($payload['tenant_id'] !== $user->tenant_id || !$user->isAdmin()) {
                abort(403, 'Unauthorized action');
            }
        }

        $tenant = Tenant::query()->findOrFail($payload['tenant_id']);

        // Check if email already exists
        $existing = TenantUser::query()->where('email', $payload['email'])->first();
        if ($existing) {
            return response()->json([
                'error' => 'email_exists',
                'message' => 'Email đã được sử dụng.',
            ], 400);
        }

        $newUser = TenantUser::query()->create([
            'tenant_id' => $tenant->id,
            'email' => $payload['email'],
            'name' => $payload['name'],
            'role' => $payload['role'] ?? TenantUser::ROLE_VIEWER,
            'status' => 'active',
            'password' => Hash::make($payload['password']),
        ]);

        return response()->json(['data' => $newUser], 201);
    }

    public function show(Request $request, int $id): JsonResponse
    {
        $targetUser = TenantUser::query()->with('tenant')->findOrFail($id);
        $user = $request->user();

        if ($user instanceof \App\Models\TenantUser && $targetUser->tenant_id !== $user->tenant_id) {
            abort(403, 'Unauthorized action');
        }

        return response()->json(['data' => $targetUser]);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $targetUser = TenantUser::query()->findOrFail($id);
        $user = $request->user();

        if ($user instanceof \App\Models\TenantUser) {
            if ($targetUser->tenant_id !== $user->tenant_id || !$user->isAdmin()) {
                abort(403, 'Unauthorized action');
            }
        }

        $payload = $request->validate([
            'name' => ['sometimes', 'string', 'max:120'],
            'role' => ['sometimes', 'string', 'in:admin,viewer'],
            'status' => ['sometimes', 'string', 'in:active,inactive'],
            'password' => ['sometimes', 'string', 'min:6'],
        ]);

        if (isset($payload['password'])) {
            $payload['password'] = Hash::make($payload['password']);
        }

        $targetUser->update($payload);

        return response()->json(['data' => $targetUser->fresh()]);
    }

    public function destroy(Request $request, int $id): JsonResponse
    {
        $targetUser = TenantUser::query()->findOrFail($id);
        $user = $request->user();

        if ($user instanceof \App\Models\TenantUser) {
            if ($targetUser->tenant_id !== $user->tenant_id || !$user->isAdmin()) {
                abort(403, 'Unauthorized action');
            }
        }

        $targetUser->delete();

        return response()->json(['message' => 'User deleted successfully']);
    }
}