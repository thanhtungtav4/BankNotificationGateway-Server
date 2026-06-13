<?php

namespace App\Http\Middleware;

use App\Models\Tenant;
use App\Services\Quota\QuotaService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class EnforcePlanQuota
{
    public function __construct(
        private readonly QuotaService $quotaService,
    ) {}

    public function handle(Request $request, Closure $next, string $type = 'device'): Response
    {
        $tenantId = $request->input('tenant_id') ?? $request->route('tenant_id') ?? $request->route('id');

        if (!$tenantId) {
            return $next($request);
        }

        $tenant = Tenant::query()->find($tenantId);
        if (!$tenant) {
            return response()->json([
                'error' => 'tenant_not_found',
                'message' => 'Tenant không tìm thấy.',
            ], 404);
        }

        $result = match ($type) {
            'device' => $this->quotaService->checkDeviceLimit($tenant),
            'webhook' => $this->quotaService->checkWebhookLimit($tenant),
            'notification' => $this->quotaService->checkNotificationLimit($tenant),
            default => null,
        };

        if ($result && !$result->allowed) {
            return response()->json([
                'error' => 'quota_exceeded',
                'message' => $result->message,
                'limit' => $result->limit,
                'current' => $result->current,
            ], 403);
        }

        return $next($request);
    }
}