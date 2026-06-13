<?php

namespace App\Http\Controllers\Dashboard;

use App\Models\Tenant;
use App\Services\Quota\QuotaService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class QuotaController
{
    public function __construct(
        private readonly QuotaService $quotaService,
    ) {}

    public function show(Request $request, int $id): JsonResponse
    {
        $user = $request->user();
        if ($user instanceof \App\Models\TenantUser && $id !== $user->tenant_id) {
            abort(403, 'Unauthorized action');
        }

        $tenant = Tenant::query()->findOrFail($id);

        $quotaInfo = $this->quotaService->getTenantQuotaInfo($tenant);

        return response()->json(['data' => $quotaInfo]);
    }
}