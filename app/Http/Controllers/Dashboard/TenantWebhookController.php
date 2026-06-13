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
        $user = $request->user();

        if ($user instanceof \App\Models\TenantUser) {
            $query->where('tenant_id', $user->tenant_id);
        } else if ($tenantId = $request->integer('tenant_id')) {
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

        $user = $request->user();
        if ($user instanceof \App\Models\TenantUser) {
            if ($payload['tenant_id'] !== $user->tenant_id || !$user->isAdmin()) {
                abort(403, 'Unauthorized action');
            }
        }

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
        $user = $request->user();
        if ($user instanceof \App\Models\TenantUser) {
            if ($webhook->tenant_id !== $user->tenant_id || !$user->isAdmin()) {
                abort(403, 'Unauthorized action');
            }
        }

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

    public function destroy(Request $request, TenantWebhook $webhook): JsonResponse
    {
        $user = $request->user();
        if ($user instanceof \App\Models\TenantUser) {
            if ($webhook->tenant_id !== $user->tenant_id || !$user->isAdmin()) {
                abort(403, 'Unauthorized action');
            }
        }

        $webhook->delete();

        return response()->json(['data' => ['id' => $webhook->id]]);
    }

    public function saveParserConfig(Request $request, TenantWebhook $webhook): JsonResponse
    {
        $user = $request->user();
        if ($user instanceof \App\Models\TenantUser) {
            if ($webhook->tenant_id !== $user->tenant_id || !$user->isAdmin()) {
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

        $webhook->update([
            'bank_rules' => [
                'regex' => $validated['regex'],
                'amount_group' => $validated['amount_group'],
                'direction_group' => $validated['direction_group'],
                'order_code_group' => $validated['order_code_group'],
                'transfer_content_group' => $validated['transfer_content_group'],
            ]
        ]);

        return response()->json([
            'message' => 'Saved parser configuration successfully',
            'data' => $webhook->fresh(),
        ]);
    }

    public function clearParserConfig(Request $request, TenantWebhook $webhook): JsonResponse
    {
        $user = $request->user();
        if ($user instanceof \App\Models\TenantUser) {
            if ($webhook->tenant_id !== $user->tenant_id || !$user->isAdmin()) {
                abort(403, 'Unauthorized action');
            }
        }

        $webhook->update([
            'bank_rules' => null,
        ]);

        return response()->json([
            'message' => 'Cleared parser configuration successfully',
            'data' => $webhook->fresh(),
        ]);
    }
}
