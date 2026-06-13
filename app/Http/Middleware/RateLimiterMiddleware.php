<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Symfony\Component\HttpFoundation\Response;

final class RateLimiterMiddleware
{
    public function handle(Request $request, Closure $next, string $type = 'default'): Response
    {
        $key = $this->resolveKey($request, $type);
        $limit = $this->resolveLimit($type);

        if (RateLimiter::tooManyAttempts($key, $limit)) {
            $retryAfter = RateLimiter::availableIn($key);

            return response()->json([
                'error' => 'rate_limit_exceeded',
                'message' => 'Quá nhiều yêu cầu. Vui lòng thử lại sau.',
                'retry_after_seconds' => $retryAfter,
            ], 429)->withHeaders([
                'Retry-After' => $retryAfter,
                'X-RateLimit-Limit' => $limit,
                'X-RateLimit-Remaining' => 0,
            ]);
        }

        RateLimiter::hit($key, 60);

        $response = $next($request);

        return $response->withHeaders([
            'X-RateLimit-Limit' => $limit,
            'X-RateLimit-Remaining' => RateLimiter::remaining($key, $limit),
        ]);
    }

    private function resolveKey(Request $request, string $type): string
    {
        return match ($type) {
            'mobile_device' => 'mobile:' . ($request->header('X-Device-Id') ?? $request->ip()),
            'mobile_tenant' => 'tenant:' . ($request->input('tenant_id') ?? 'unknown'),
            'auth' => 'auth:' . $request->ip(),
            'dashboard' => 'dashboard:' . ($request->user()?->id ?? $request->ip()),
            default => 'api:' . $request->ip(),
        };
    }

    private function resolveLimit(string $type): int
    {
        return match ($type) {
            'mobile_device' => (int) config('rate-limit.mobile.per_device', 60),
            'mobile_tenant' => (int) config('rate-limit.mobile.per_tenant', 300),
            'mobile_pairing' => (int) config('rate-limit.mobile.pairing', 10),
            'mobile_heartbeat' => (int) config('rate-limit.mobile.heartbeat', 120),
            'auth' => (int) config('rate-limit.dashboard.auth', 5),
            'dashboard' => (int) config('rate-limit.dashboard.per_admin', 120),
            default => 60,
        };
    }
}