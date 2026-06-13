<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Rate Limiting Configuration
    |--------------------------------------------------------------------------
    |
    | Configure rate limits for different API endpoints.
    | All limits are per minute unless specified.
    |
    */

    'mobile' => [
        // Per device rate limit
        'per_device' => env('RATE_LIMIT_MOBILE_DEVICE', 60),

        // Per tenant rate limit (aggregate of all devices)
        'per_tenant' => env('RATE_LIMIT_MOBILE_TENANT', 300),

        // Pairing endpoint (more restrictive - prevent brute force)
        'pairing' => env('RATE_LIMIT_PAIRING', 10),

        // Heartbeat (high frequency, generous limit)
        'heartbeat' => env('RATE_LIMIT_HEARTBEAT', 120),
    ],

    'dashboard' => [
        // Per admin user rate limit
        'per_admin' => env('RATE_LIMIT_DASHBOARD_ADMIN', 120),

        // Auth endpoints (prevent brute force)
        'auth' => env('RATE_LIMIT_AUTH', 5),

        // Read operations (higher limit)
        'read' => env('RATE_LIMIT_DASHBOARD_READ', 300),

        // Write operations (lower limit)
        'write' => env('RATE_LIMIT_DASHBOARD_WRITE', 60),
    ],

    'webhook' => [
        // Webhook delivery timeout (seconds)
        'timeout' => env('WEBHOOK_TIMEOUT_SECONDS', 10),

        // Maximum retry attempts
        'max_retries' => env('WEBHOOK_MAX_RETRIES', 6),

        // Retry delays in seconds
        'retry_delays' => [0, 60, 300, 900, 3600, 21600],

        // Stop retrying after this many hours
        'stop_after_hours' => env('WEBHOOK_STOP_AFTER_HOURS', 48),
    ],

    'device' => [
        // Device offline threshold (minutes)
        'offline_threshold_minutes' => env('DEVICE_OFFLINE_THRESHOLD', 30),

        // Pairing token TTL (minutes)
        'pairing_token_ttl_minutes' => env('PAIRING_TOKEN_TTL', 15),
    ],

    'notification' => [
        // Deduplication window (minutes)
        'dedup_window_minutes' => env('NOTIFICATION_DEDUP_WINDOW', 5),

        // Maximum notifications per device per hour (fair use)
        'max_per_hour' => env('NOTIFICATION_MAX_PER_HOUR', 1000),
    ],
];