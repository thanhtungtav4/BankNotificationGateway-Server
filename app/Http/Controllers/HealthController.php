<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Redis;

final class HealthController
{
    public function __invoke(): JsonResponse
    {
        $components = [];
        $overallStatus = 'healthy';

        // Database check
        try {
            $start = microtime(true);
            DB::connection()->getPdo();
            DB::select('SELECT 1');
            $latency = round((microtime(true) - $start) * 1000, 2);

            $components['database'] = [
                'status' => 'ok',
                'latency_ms' => $latency,
            ];
        } catch (\Throwable $e) {
            $components['database'] = [
                'status' => 'error',
                'error' => 'Connection failed',
            ];
            $overallStatus = 'unhealthy';
        }

        // Redis check
        try {
            $start = microtime(true);
            Redis::ping();
            $latency = round((microtime(true) - $start) * 1000, 2);

            $components['redis'] = [
                'status' => 'ok',
                'latency_ms' => $latency,
            ];
        } catch (\Throwable $e) {
            $components['redis'] = [
                'status' => 'degraded',
                'error' => 'Connection failed',
            ];
            if ($overallStatus === 'healthy') {
                $overallStatus = 'degraded';
            }
        }

        // Queue check (check for pending jobs in Redis)
        try {
            $queueSize = Cache::get('queue:size', 0);
            $components['queue'] = [
                'status' => 'ok',
                'jobs_pending' => $queueSize,
            ];
        } catch (\Throwable $e) {
            $components['queue'] = [
                'status' => 'unknown',
                'error' => 'Cannot check queue',
            ];
        }

        // Disk check
        try {
            $diskUsage = disk_free_space('/');
            $diskTotal = disk_total_space('/');
            $diskPercent = round((($diskTotal - $diskUsage) / $diskTotal) * 100, 1);

            $components['disk'] = [
                'status' => $diskPercent > 90 ? 'warning' : 'ok',
                'usage_percent' => $diskPercent,
                'free_gb' => round($diskUsage / 1024 / 1024 / 1024, 1),
            ];

            if ($diskPercent > 90 && $overallStatus === 'healthy') {
                $overallStatus = 'degraded';
            }
        } catch (\Throwable $e) {
            $components['disk'] = [
                'status' => 'unknown',
                'error' => 'Cannot check disk',
            ];
        }

        // Memory check
        try {
            $memInfo = file_exists('/proc/meminfo')
                ? parse_meminfo()
                : ['total' => 0, 'available' => 0];

            $memUsed = round((($memInfo['total'] - $memInfo['available']) / $memInfo['total'] * 100), 1);

            $components['memory'] = [
                'status' => $memUsed > 90 ? 'warning' : 'ok',
                'usage_percent' => $memUsed,
            ];

            if ($memUsed > 90 && $overallStatus === 'healthy') {
                $overallStatus = 'degraded';
            }
        } catch (\Throwable $e) {
            $components['memory'] = [
                'status' => 'unknown',
            ];
        }

        $statusCode = $overallStatus === 'healthy' ? 200 : ($overallStatus === 'degraded' ? 200 : 503);

        return response()->json([
            'status' => $overallStatus,
            'timestamp' => now()->toIso8601String(),
            'version' => config('app.version', '1.0.0'),
            'components' => $components,
        ], $statusCode);
    }
}

function parse_meminfo(): array
{
    $memInfo = [];
    if (file_exists('/proc/meminfo')) {
        $lines = file('/proc/meminfo', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        foreach ($lines as $line) {
            if (preg_match('/^(MemTotal|MemAvailable|MemFree):\s+(\d+)/', $line, $matches)) {
                $key = str_replace(['MemTotal', 'MemAvailable', 'MemFree'], ['total', 'available', 'free'], $matches[1]);
                $memInfo[$key] = (int) $matches[2] * 1024; // Convert KB to bytes
            }
        }
    }

    if (!isset($memInfo['total']) || $memInfo['total'] === 0) {
        $memInfo['total'] = 1;
        $memInfo['available'] = 0;
    }

    return $memInfo;
}