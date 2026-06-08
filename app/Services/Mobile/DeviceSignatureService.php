<?php

namespace App\Services\Mobile;

final class DeviceSignatureService
{
    public function sign(string $timestamp, string $rawBody, string $secret): string
    {
        return hash_hmac('sha256', $timestamp . '.' . $rawBody, $secret);
    }

    public function verify(string $timestamp, string $rawBody, string $secret, string $signature): bool
    {
        return hash_equals($this->sign($timestamp, $rawBody, $secret), $signature);
    }
}
