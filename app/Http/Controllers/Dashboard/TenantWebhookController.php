<?php

namespace App\Http\Controllers\Dashboard;

use App\Models\Tenant;
use App\Models\TenantWebhook;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

final class TenantWebhookController
{
    public function index(Request $request): JsonResponse
    {
        $query = TenantWebhook::query()->with('tenant')->latest();
        if ($tenantId = $request->integer('tenant_id')) {
            $query->where('tenant_id', $tenantId);
        }

        return response()->json(['data' => $query->get()]);
    }

    public function store(Request $request): JsonResponse
    {
        $payload = $request->validate([
            'tenant_id' => ['required', 'integer', 'exists:tenants,id'],
            'name' => ['required', 'string', 'max:160'],
            'url' => ['required', 'url', 'max:500'],
            'event_types' => ['nullable', 'array'],
            'event_types.*' => ['string', 'max:80'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $webhook = TenantWebhook::query()->create([
            'tenant_id' => $payload['tenant_id'],
            'name' => $payload['name'],
            'url' => $payload['url'],
            'secret' => 'whk_' . Str::lower(Str::random(32)),
            'event_types' => $payload['event_types'] ?? ['money_in'],
            'is_active' => $payload['is_active'] ?? true,
        ]);

        return response()->json(['data' => $webhook], 201);
    }

    public function update(Request $request, TenantWebhook $webhook): JsonResponse
    {
        $payload = $request->validate([
            'name' => ['sometimes', 'string', 'max:160'],
            'url' => ['sometimes', 'url', 'max:500'],
            'event_types' => ['sometimes', 'array'],
            'event_types.*' => ['string', 'max:80'],
            'is_active' => ['sometimes', 'boolean'],
        ]);

        $webhook->update($payload);

        return response()->json(['data' => $webhook->fresh()]);
    }

    public function destroy(TenantWebhook $webhook): JsonResponse
    {
        $webhook->delete();

        return response()->json(['data' => ['id' => $webhook->id]]);
    }
}
