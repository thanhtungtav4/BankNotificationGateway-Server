<?php

namespace App\Http\Controllers\Dashboard;

use App\Services\AI\AIService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Exception;

final class AIController
{
    public function __construct(
        private readonly AIService $aiService
    ) {}

    /**
     * Generate a regex pattern for a sample notification text.
     */
    public function generateRegex(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'sample_text' => ['required', 'string', 'max:1000'],
            'bank_name' => ['nullable', 'string', 'max:120'],
            'provider' => ['nullable', 'string', 'max:50'],
            'selected_amount' => ['nullable', 'string', 'max:100'],
            'selected_order' => ['nullable', 'string', 'max:100'],
            'selected_content' => ['nullable', 'string', 'max:500'],
        ]);

        try {
            $result = $this->aiService->generateRegex(
                $validated['sample_text'],
                $validated['bank_name'] ?? null,
                $validated['provider'] ?? null,
                $validated['selected_amount'] ?? null,
                $validated['selected_order'] ?? null,
                $validated['selected_content'] ?? null
            );

            return response()->json($result);
        } catch (Exception $e) {
            return response()->json([
                'error' => 'Failed to generate regex using AI.',
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Parse a transaction notification directly using AI.
     */
    public function parseNotification(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'sample_text' => ['required', 'string', 'max:1000'],
            'provider' => ['nullable', 'string', 'max:50'],
        ]);

        try {
            $result = $this->aiService->parseNotification(
                $validated['sample_text'],
                $validated['provider'] ?? null
            );

            return response()->json($result);
        } catch (Exception $e) {
            return response()->json([
                'error' => 'Failed to parse notification using AI.',
                'message' => $e->getMessage(),
            ], 500);
        }
    }
}
