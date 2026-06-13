<?php

namespace App\Http\Controllers\Dashboard;

use App\Models\Device;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class DeviceController
{
    public function index(Request $request): JsonResponse
    {
        $query = Device::query()->with('tenant');
        $user = $request->user();

        if ($user instanceof \App\Models\TenantUser) {
            $query->where('tenant_id', $user->tenant_id);
        } else if ($request->filled('tenant_id')) {
            $query->where('tenant_id', $request->input('tenant_id'));
        }

        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }

        return response()->json([
            'data' => $query->latest()->get(),
        ]);
    }

    public function show(Request $request): JsonResponse
    {
        $id = (int) $request->route('id');
        $device = Device::query()->with(['tenant', 'allowedPackages'])->findOrFail($id);
        $user = $request->user();

        if ($user instanceof \App\Models\TenantUser && $device->tenant_id !== $user->tenant_id) {
            abort(403, 'Unauthorized action');
        }

        return response()->json(['data' => $device]);
    }

    public function destroy(Request $request): JsonResponse
    {
        $id = (int) $request->route('id');
        $device = Device::query()->findOrFail($id);
        $user = $request->user();

        if ($user instanceof \App\Models\TenantUser) {
            if ($device->tenant_id !== $user->tenant_id || !$user->isAdmin()) {
                abort(403, 'Unauthorized action');
            }
        }

        $device->delete();

        return response()->json(['message' => 'Device deleted successfully']);
    }
}