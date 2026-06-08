<?php

namespace App\Http\Controllers\Dashboard;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class WebhookTestController
{
    public function __invoke(Request $request): JsonResponse
    {
        // TODO: Build signed sample payload and dispatch to tenant webhook.
        return response()->json(['status' => 'queued']);
    }
}
