<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class EnsureAdminUser
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        abort_if(! $user || ! in_array($user->role ?? null, ['super_admin', 'admin'], true), 403, 'Admin access required');

        return $next($request);
    }
}
