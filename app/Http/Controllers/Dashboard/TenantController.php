<?php

namespace App\Http\Controllers\Dashboard;

use App\Models\Tenant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

final class TenantController
{
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        if ($user instanceof \App\Models\TenantUser) {
            return response()->json(['data' => Tenant::query()->where('id', $user->tenant_id)->get()]);
        }
        return response()->json(['data' => Tenant::query()->latest()->get()]);
    }

    public function store(Request $request): JsonResponse
    {
        if ($request->user() instanceof \App\Models\TenantUser) {
            abort(403, 'Unauthorized action');
        }

        $payload = $request->validate([
            'name' => ['required', 'string', 'max:160'],
            'plan' => ['nullable', 'string', 'max:80'],
            'status' => ['nullable', 'string', 'max:40'],
        ]);

        $tenant = Tenant::query()->create([
            'name' => $payload['name'],
            'slug' => Str::slug($payload['name']) . '-' . Str::lower(Str::random(5)),
            'status' => $payload['status'] ?? 'trial',
            'monthly_fee' => 0,
        ]);

        return response()->json(['data' => $tenant], 201);
    }

    public function show(Request $request): JsonResponse
    {
        $id = (int) $request->route('id');
        $user = $request->user();
        
        if ($user instanceof \App\Models\TenantUser && $user->tenant_id !== $id) {
            abort(403, 'Unauthorized action');
        }

        $tenant = Tenant::query()->with(['devices', 'webhooks', 'parserConfigs'])->findOrFail($id);

        return response()->json(['data' => $tenant]);
    }

    public function update(Request $request): JsonResponse
    {
        $id = (int) $request->route('id');
        $user = $request->user();

        if ($user instanceof \App\Models\TenantUser) {
            if ($user->tenant_id !== $id || !$user->isAdmin()) {
                abort(403, 'Unauthorized action');
            }
            $payload = $request->validate([
                'name' => ['required', 'string', 'max:160'],
            ]);
        } else {
            $payload = $request->validate([
                'name' => ['sometimes', 'string', 'max:160'],
                'status' => ['sometimes', 'string', 'in:trial,active,suspended'],
                'plan_id' => ['nullable', 'integer'],
            ]);
        }

        $tenant = Tenant::query()->findOrFail($id);
        $tenant->update($payload);

        return response()->json(['data' => $tenant->fresh()]);
    }

    public function destroy(Request $request): JsonResponse
    {
        if ($request->user() instanceof \App\Models\TenantUser) {
            abort(403, 'Unauthorized action');
        }

        $id = (int) $request->route('id');
        $tenant = Tenant::query()->findOrFail($id);

        // Delete tenant (cascades to devices, webhooks, etc. via DB constraints)
        $tenant->delete();

        return response()->json(['message' => 'Tenant deleted successfully']);
    }

    public function saveParserConfig(Request $request): JsonResponse
    {
        $id = (int) $request->route('id');
        $user = $request->user();

        if ($user instanceof \App\Models\TenantUser) {
            if ($user->tenant_id !== $id || !$user->isAdmin()) {
                abort(403, 'Unauthorized action');
            }
        }

        $validated = $request->validate([
            'regex' => ['required', 'string', 'max:500'],
            'amount_group' => ['nullable', 'integer', 'min:0'],
            'direction_group' => ['nullable', 'integer', 'min:0'],
            'order_code_group' => ['nullable', 'integer', 'min:0'],
            'transfer_content_group' => ['nullable', 'integer', 'min:0'],
        ]);

        $tenant = Tenant::query()->findOrFail($id);

        $config = \App\Models\TenantParserConfig::query()->updateOrCreate(
            ['tenant_id' => $tenant->id, 'name' => 'Default'],
            [
                'bank_rules' => [
                    'regex' => $validated['regex'],
                    'amount_group' => $validated['amount_group'],
                    'direction_group' => $validated['direction_group'],
                    'order_code_group' => $validated['order_code_group'],
                    'transfer_content_group' => $validated['transfer_content_group'],
                ],
                'is_active' => true,
            ]
        );

        return response()->json([
            'message' => 'Saved parser configuration successfully',
            'data' => $config,
        ]);
    }
}
