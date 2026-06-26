<?php

return [
    'api_key' => env('DOMPETX_API_KEY'),
    'api_secret' => env('DOMPETX_API_SECRET'),
    'base_url' => env('DOMPETX_BASE_URL', 'https://api.dompetx.com/v1'),
    'webhook_secret' => env('DOMPETX_WEBHOOK_SECRET'),
];
