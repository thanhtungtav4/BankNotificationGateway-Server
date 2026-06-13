<?php

return [
    'default' => env('AI_PROVIDER', 'vps-163'),

    'providers' => [
        'vps-163' => [
            'base_url' => env('AI_VPS_163_BASE_URL', 'http://163.61.110.132:3001/v1'),
            'api_key' => env('AI_VPS_163_API_KEY', 'sk-80c6f26e1d3336a7-5ahrqn-6975d32c'),
            'model' => env('AI_VPS_163_MODEL', 'claude_sonet_4.5'),
        ],
        'vps-103' => [
            'base_url' => env('AI_VPS_103_BASE_URL', 'http://103.157.204.253:3001/v1'),
            'api_key' => env('AI_VPS_103_API_KEY', 'sk-80c6f26e1d3336a7-5ahrqn-6975d32c'),
            'model' => env('AI_VPS_103_MODEL', 'claude_sonet_4.5'),
        ],
        'opencode' => [
            'base_url' => env('AI_OPENCODE_BASE_URL', 'https://opencode.ai/zen/v1'),
            'api_key' => env('AI_OPENCODE_API_KEY', 'sk-SbRGNbrJVRn8QCraEle2VXRVq1oqBAnm6dTC9zSTqQSliA1YNvREtSEEaOroy6xB'),
            'model' => env('AI_OPENCODE_MODEL', 'qwen3.6-plus'),
        ],
    ],
];
