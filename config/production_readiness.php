<?php

return [
    'production_required' => [
        'APP_ENV=production',
        'APP_DEBUG=false',
        'APP_KEY is generated and secret',
        'HTTPS enforced at proxy/load balancer',
        'DEBUG_DEVICE_SECRET_HEADER_ENABLED=false',
        'DASHBOARD_FALLBACK_ENABLED=false',
        'Pairing token TTL <= 15 minutes',
        'Queue worker and scheduler supervised',
        'Database backups configured',
        'Dashboard routes protected by auth middleware',
        'Tenant queries scoped by tenant_id',
        'Webhook retry command scheduled',
    ],
];
