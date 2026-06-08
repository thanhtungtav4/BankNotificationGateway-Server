<?php

namespace App\Services\Webhooks;

final class WebhookSignatureService
{
    public function sign(string $timestamp, string $rawBody, string $secret): string
    {
        return hash_hmac('sha256', $timestamp . '.' . $rawBody, $secret);
    }
}
