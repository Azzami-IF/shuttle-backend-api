<?php

return [
    'write_operations' => env('RATE_LIMIT_WRITE', 60), // per minute
    'read_operations' => env('RATE_LIMIT_READ', 120),
    'tracking' => env('RATE_LIMIT_TRACKING', 300),
];
