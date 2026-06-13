<?php

namespace App\Services\AI;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Exception;

class AIService
{
    /**
     * Get the ordered list of providers for failover, starting with the requested or default provider.
     */
    protected function getProviderSequence(?string $requestedProvider = null): array
    {
        $config = config('ai');
        $providers = array_keys($config['providers'] ?? []);
        
        $first = $requestedProvider ?: $config['default'];
        
        if (!in_array($first, $providers)) {
            $first = reset($providers);
        }

        if (!$first) {
            return [];
        }

        // Put the first provider at the start, and append the rest
        return array_unique(array_merge([$first], $providers));
    }

    /**
     * Call the chat completions API of a provider.
     */
    protected function callCompletions(string $provider, array $messages, array $options = []): array
    {
        $config = config("ai.providers.{$provider}");
        if (!$config) {
            throw new Exception("AI provider '{$provider}' is not configured.");
        }

        $url = rtrim($config['base_url'], '/') . '/chat/completions';
        $apiKey = $config['api_key'];
        $model = $config['model'];

        $payload = array_merge([
            'model' => $model,
            'messages' => $messages,
            'temperature' => 0.1,
            'stream' => false,
        ], $options);

        Log::info("Calling AI provider: {$provider} with URL: {$url} and model: {$model}");

        $response = Http::withHeaders([
            'Authorization' => "Bearer {$apiKey}",
            'Accept' => 'application/json',
        ])
        ->timeout(60)
        ->post($url, $payload);

        if (!$response->successful()) {
            throw new Exception("AI Provider '{$provider}' returned status code: " . $response->status() . ". Body: " . $response->body());
        }

        $data = $response->json();
        if (!isset($data['choices'][0]['message']['content'])) {
            throw new Exception("AI Provider '{$provider}' response format invalid: " . json_encode($data));
        }

        return [
            'content' => $data['choices'][0]['message']['content'],
            'model' => $model,
            'provider' => $provider,
        ];
    }

    /**
     * Perform chat completion with failover support.
     */
    public function complete(array $messages, ?string $provider = null, array $options = []): array
    {
        $sequence = $this->getProviderSequence($provider);
        if (empty($sequence)) {
            throw new Exception("No AI providers configured in config/ai.php.");
        }

        $errors = [];
        foreach ($sequence as $currentProvider) {
            try {
                return $this->callCompletions($currentProvider, $messages, $options);
            } catch (Exception $e) {
                Log::warning("AI provider '{$currentProvider}' failed. Error: " . $e->getMessage());
                $errors[$currentProvider] = $e->getMessage();
            }
        }

        throw new Exception("All AI providers failed. Failures: " . json_encode($errors));
    }

    /**
     * Ask the AI to generate a regular expression (regex) to parse transaction info from a text.
     */
    public function generateRegex(
        string $sampleText,
        ?string $bankName = null,
        ?string $provider = null,
        ?string $selectedAmount = null,
        ?string $selectedOrder = null,
        ?string $selectedContent = null
    ): array {
        $systemPrompt = "You are an expert system that generates PHP regular expressions (preg_match) to parse transaction notifications/SMS from banks. " .
            "You MUST return ONLY a raw JSON object and nothing else. No markdown wraps, no backticks, no comments, no explanation outside the JSON structure. " .
            "The JSON structure MUST have the following keys:\n" .
            "{\n" .
            "  \"regex\": \"string (a valid PHP regular expression with delimiters and modifiers, e.g., '/pattern/iu')\",\n" .
            "  \"amount_group\": integer (or null, the capture group index of the transaction amount in the regex),\n" .
            "  \"direction_group\": integer (or null, the capture group index of the transaction direction in the regex),\n" .
            "  \"order_code_group\": integer (or null, the capture group index of the order/reference code in the regex),\n" .
            "  \"transfer_content_group\": integer (or null, the capture group index of the transfer description/content in the regex),\n" .
            "  \"bank_name\": \"string (the detected bank name, or null)\",\n" .
            "  \"explanation\": \"string (a brief, clear explanation of how the regex works)\"\n" .
            "}";

        $userPrompt = "Please generate a regex to parse the following notification text:\n" .
            "--- START TEXT ---\n" .
            $sampleText . "\n" .
            "--- END TEXT ---\n" .
            ($bankName ? "Bank Name hint: " . $bankName . "\n" : "");

        if ($selectedAmount) {
            $userPrompt .= "Ground truth / Target Amount value to extract: \"" . $selectedAmount . "\"\n";
        }
        if ($selectedOrder) {
            $userPrompt .= "Ground truth / Target Order Code value to extract: \"" . $selectedOrder . "\"\n";
        }
        if ($selectedContent) {
            $userPrompt .= "Ground truth / Target Transfer Content value to extract: \"" . $selectedContent . "\"\n";
        }

        $userPrompt .= "Remember: The regex should be able to capture amount, direction (in/out), order code (like TOURxxxx, DHxxxx, ORDERxxxx, INVxxxx, BKxxxx etc.), and transfer content. Output ONLY valid JSON.";

        $messages = [
            ['role' => 'system', 'content' => $systemPrompt],
            ['role' => 'user', 'content' => $userPrompt],
        ];

        $result = $this->complete($messages, $provider);
        $content = $this->cleanJsonString($result['content']);

        $decoded = json_decode($content, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            Log::error("Failed to decode AI generated regex JSON: " . json_last_error_msg() . ". Content: " . $result['content']);
            throw new Exception("AI response was not a valid JSON structure: " . $result['content']);
        }

        $decoded['provider_used'] = $result['provider'];
        $decoded['model_used'] = $result['model'];

        return $decoded;
    }

    /**
     * Ask the AI to parse a transaction notification directly.
     */
    public function parseNotification(string $sampleText, ?string $provider = null): array
    {
        $systemPrompt = "You are a precise financial data parsing assistant. " .
            "Your job is to parse bank transaction notifications/SMS and extract the transaction details in JSON format. " .
            "You MUST return ONLY a raw JSON object and nothing else. No markdown wraps, no backticks, no comments, no explanation outside the JSON structure. " .
            "The JSON structure MUST have the following keys:\n" .
            "{\n" .
            "  \"amount\": integer (the transaction amount, or null if not found),\n" .
            "  \"direction\": \"string ('in' if money received/credit, 'out' if money spent/debit/payment, or 'unknown')\",\n" .
            "  \"transfer_content\": \"string (the transaction description/details, or null)\",\n" .
            "  \"order_code\": \"string (the matched order code like DH123456, TOUR9999, ORDER456, etc., or null)\",\n" .
            "  \"bank_name\": \"string (detected bank name, or null)\",\n" .
            "  \"confidence\": float (between 0.0 and 1.0)\n" .
            "}";

        $userPrompt = "Please parse the following notification text:\n" .
            "--- START TEXT ---\n" .
            $sampleText . "\n" .
            "--- END TEXT ---\n" .
            "Output ONLY valid JSON.";

        $messages = [
            ['role' => 'system', 'content' => $systemPrompt],
            ['role' => 'user', 'content' => $userPrompt],
        ];

        $result = $this->complete($messages, $provider);
        $content = $this->cleanJsonString($result['content']);

        $decoded = json_decode($content, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            Log::error("Failed to decode AI parsed notification JSON: " . json_last_error_msg() . ". Content: " . $result['content']);
            throw new Exception("AI response was not a valid JSON structure: " . $result['content']);
        }

        $decoded['provider_used'] = $result['provider'];
        $decoded['model_used'] = $result['model'];

        return $decoded;
    }

    /**
     * Strip markdown code blocks (e.g. ```json ... ```) from a response string.
     */
    protected function cleanJsonString(string $content): string
    {
        $content = trim($content);
        
        // Remove leading ```json or ``` if present
        if (preg_match('/^```(?:json)?\s*(.*?)\s*```$/is', $content, $matches)) {
            $content = $matches[1];
        }
        
        return trim($content);
    }
}
