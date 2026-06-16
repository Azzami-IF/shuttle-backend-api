<?php

return [
    'enabled' => env('RESPONSE_COMPRESSION', true),
    'minimum_length' => 1024, // Only compress responses > 1KB
    'level' => 6, // 1-9, higher = more compression
    'types' => [
        'application/json',
        'text/plain',
        'text/html',
        'text/xml',
        'application/xml',
    ],
];
