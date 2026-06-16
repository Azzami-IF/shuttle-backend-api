<?php

return [
    'email_from' => env('MAIL_FROM_ADDRESS', 'noreply@shuttle.app'),
    'sms_provider' => env('SMS_PROVIDER', 'twilio'),
    'twilio_sid' => env('TWILIO_ACCOUNT_SID'),
    'twilio_token' => env('TWILIO_AUTH_TOKEN'),
    'twilio_phone' => env('TWILIO_PHONE_NUMBER'),
    'push_provider' => env('PUSH_PROVIDER', 'fcm'),
    'fcm_api_key' => env('FCM_API_KEY'),
    'queue' => env('QUEUE_DRIVER', 'database'),
];