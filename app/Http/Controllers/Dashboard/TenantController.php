<?php

namespace App\Http\Controllers\Dashboard;

use App\Models\Tenant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

final class TenantController
{
    public function index(): JsonResponse
    {
        return response()->json(['data' => Tenant::query()->latest()->get()]);
    }

    public function store(Request $request): JsonResponse
    {
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
}
