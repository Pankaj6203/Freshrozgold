<?php

return [

    /*
    |--------------------------------------------------------------------------
    | OTP Configuration
    |--------------------------------------------------------------------------
    */

    'length' => (int) env('OTP_LENGTH', 6),

    'validity_minutes' => (int) env('OTP_VALIDITY_MINUTES', 10),

    'resend_cooldown_seconds' => (int) env('OTP_RESEND_COOLDOWN_SECONDS', 120),

    /*
    |--------------------------------------------------------------------------
    | Test Mode (non-production only)
    |--------------------------------------------------------------------------
    | Comma-separated mobile numbers that bypass real SMS delivery.
    | Only effective when APP_ENV != 'production'.
    */

    'test_numbers' => env('OTP_TEST_NUMBERS', ''),

    'test_otp' => env('OTP_TEST_OTP', '123456'),

    'sms_template' => env(
        'SMS_TEMPLATE',
        'Your OTP is {otp} for RozGold verification. It is Valid for {validity} minutes only. Do not share this OTP with anyone. www.rozgold.in'
    ),
];
