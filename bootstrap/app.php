<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__ . '/../routes/web.php',
        api: __DIR__ . '/../routes/api.php',
        commands: __DIR__ . '/../routes/console.php',
        health: '/up',
    )
    ->withProviders([
        \App\Providers\AppServiceProvider::class,
    ])
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'auth.admin' => \App\Http\Middleware\EnsureAdminUser::class,
            'quota' => \App\Http\Middleware\EnforcePlanQuota::class,
        ]);

        $middleware->redirectGuestsTo(function ($request) {
            if ($request->is('api/*') || $request->expectsJson()) {
                return null;
            }
            return '/login';
        });
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->render(function (\Illuminate\Validation\ValidationException $e) {
            \Illuminate\Support\Facades\Log::warning('Validation failed: ' . json_encode($e->errors()) . ' | Payload: ' . request()->getContent() . ' | Content-Type: ' . request()->header('Content-Type'));
            return response()->json([
                'error' => 'validation_error',
                'message' => 'Dữ liệu không hợp lệ.',
                'errors' => $e->errors(),
            ], 422);
        });

        $exceptions->render(function (\Illuminate\Auth\AuthenticationException $e) {
            return response()->json([
                'error' => 'unauthenticated',
                'message' => 'Vui lòng đăng nhập để tiếp tục.',
            ], 401);
        });

        $exceptions->render(function (\Symfony\Component\HttpKernel\Exception\NotFoundHttpException $e) {
            return response()->json([
                'error' => 'not_found',
                'message' => 'Endpoint không tồn tại.',
            ], 404);
        });

        $exceptions->render(function (\Symfony\Component\HttpKernel\Exception\ThrottleRequestsException $e) {
            return response()->json([
                'error' => 'rate_limit_exceeded',
                'message' => 'Quá nhiều yêu cầu. Vui lòng thử lại sau.',
                'retry_after' => $e->getHeaders()['Retry-After'] ?? 60,
            ], 429);
        });
    })
    ->create();