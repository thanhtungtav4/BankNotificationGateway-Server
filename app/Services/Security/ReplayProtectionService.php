<?php

namespace App\Services\Security;

use Carbon\CarbonImmutable;
use Illuminate\Http\Exceptions\HttpResponseException;

final class ReplayProtectionService
{
    public function assertFresh(string $timestamp, int $toleranceSeconds): void
    {
        if (! ctype_digit($timestamp)) {
            throw new HttpResponseException(response()->json(['message' => 'Invalid timestamp'], 401));
        }

        $requestTime = CarbonImmutable::createFromTimestamp((int) $timestamp);
        $delta = abs(now()->diffInSeconds($requestTime, false));

        if ($delta > $toleranceSeconds) {
            throw new HttpResponseException(response()->json(['message' => 'Stale timestamp'], 401));
        }
    }
}
