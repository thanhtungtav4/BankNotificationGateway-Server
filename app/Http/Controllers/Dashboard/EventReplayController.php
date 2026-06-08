<?php

namespace App\Http\Controllers\Dashboard;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class EventReplayController
{
    public function __invoke(string $event_id, Request $request): JsonResponse
    {
        // TODO: Re-dispatch a previously parsed transaction webhook.
        return response()->json(['event_id' => $event_id, 'status' => 'queued']);
    }
}
