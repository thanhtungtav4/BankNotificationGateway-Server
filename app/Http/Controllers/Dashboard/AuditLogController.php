<?php

namespace App\Http\Controllers\Dashboard;

use App\Models\AuditLog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class AuditLogController
{
    public function index(Request $request): JsonResponse
    {
        $query = AuditLog::query()->with('tenant');
        $user = $request->user();

        if ($user instanceof \App\Models\TenantUser) {
            $query->where('tenant_id', $user->tenant_id);
        } else if ($request->filled('tenant_id')) {
            $query->where('tenant_id', $request->input('tenant_id'));
        }

        if ($request->filled('action')) {
            $query->where('action', $request->input('action'));
        }

        if ($request->filled('subject_type')) {
            $query->where('subject_type', $request->input('subject_type'));
        }

        if ($request->filled('from_date')) {
            $query->whereDate('created_at', '>=', $request->input('from_date'));
        }

        if ($request->filled('to_date')) {
            $query->whereDate('created_at', '<=', $request->input('to_date'));
        }

        $perPage = min((int) $request->input('per_page', 50), 100);
        $logs = $query->latest()->paginate($perPage);

        return response()->json($logs);
    }

    public function show(Request $request, int $id): JsonResponse
    {
        $log = AuditLog::query()->with('tenant')->findOrFail($id);
        $user = $request->user();

        if ($user instanceof \App\Models\TenantUser && $log->tenant_id !== $user->tenant_id) {
            abort(403, 'Unauthorized action');
        }

        return response()->json(['data' => $log]);
    }

    public function actions(Request $request): JsonResponse
    {
        $user = $request->user();
        $query = AuditLog::query();

        if ($user instanceof \App\Models\TenantUser) {
            $query->where('tenant_id', $user->tenant_id);
        }

        $actions = $query->select('action')
            ->distinct()
            ->orderBy('action')
            ->pluck('action');

        return response()->json(['data' => $actions]);
    }
}