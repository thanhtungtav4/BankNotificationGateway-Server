<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class EnsureTenantScope
{
    public function handle(Request $request, Closure $next): Response
    {
        $tenantId = $request->user()?->tenant_id;

        abort_if(! $tenantId && ! in_array($request->user()?->role ?? null, ['super_admin', 'admin'], true), 403, 'Tenant scope required');

        app()->instance('currentTenantId', $tenantId);

        return $next($request);
    }
}
