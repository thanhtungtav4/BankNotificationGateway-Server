<?php

return [
    'mobile_signature_tolerance_seconds' => (int) env('MOBILE_SIGNATURE_TOLERANCE_SECONDS', 300),
    'webhook_timeout_seconds' => (int) env('WEBHOOK_TIMEOUT_SECONDS', 10),
    'webhook_retry_delays_seconds' => [0, 60, 300, 900, 3600, 21600],
    'dashboard_fallback_enabled' => (bool) env('DASHBOARD_FALLBACK_ENABLED', false),
    'debug_device_secret_header_enabled' => (bool) env('DEBUG_DEVICE_SECRET_HEADER_ENABLED', false),
    'pairing_token_ttl_minutes' => (int) env('PAIRING_TOKEN_TTL_MINUTES', 15),
];
