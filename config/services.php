<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'mailgun' => [
        'domain' => env('MAILGUN_DOMAIN'),
        'secret' => env('MAILGUN_SECRET'),
        'endpoint' => env('MAILGUN_ENDPOINT', 'api.mailgun.net'),
        'scheme' => 'https',
    ],

    'postmark' => [
        'token' => env('POSTMARK_TOKEN'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'cashfree' => [
        'app_id' => env('CASHFREE_APP_ID', ''),
        'secret_key' => env('CASHFREE_SECRET_KEY', ''),
        'api_url' => env('CASHFREE_API_URL', 'https://sandbox.cashfree.com/pg'),
        'api_version' => env('CASHFREE_API_VERSION', '2023-08-01'),
        'ssl_verify' => env('CASHFREE_SSL_VERIFY', true),
        'return_url' => env('CASHFREE_RETURN_URL', 'https://rozgold.techotd.in/payment-success'),
        'webhook_secret' => env('CASHFREE_WEBHOOK_SECRET', ''),
    ],

    'cashfree_payout' => [
        'client_id'     => env('CASHFREE_PAYOUT_CLIENT_ID', ''),
        'client_secret' => env('CASHFREE_PAYOUT_CLIENT_SECRET', ''),
        'api_url'       => env('CASHFREE_PAYOUT_API_URL', 'https://sandbox.cashfree.com/payout'),
    ],

    'cashfree_verification' => [
        'client_id'     => env('CASHFREE_VERIFICATION_CLIENT_ID', ''),
        'client_secret' => env('CASHFREE_VERIFICATION_CLIENT_SECRET', ''),
        'api_url'       => env('CASHFREE_VERIFICATION_API_URL', 'https://sandbox.cashfree.com/verification'),
    ],

    'smsmenow' => [
        'enabled' => env('SMSMENOW_ENABLED', false),
        'user' => env('SMSMENOW_USER', ''),
        'password' => env('SMSMENOW_PASSWORD', ''),
        'senderid' => env('SMSMENOW_SENDERID', 'ROZGLD'),
        'url' => env('SMSMENOW_URL', 'http://sms.smsmenow.in/sendsms.jsp'),
        'jsessionid' => env('SMSMENOW_JSESSIONID', ''),
    ],

    'quickekyc' => [
        'environment' => env('QUICKEKYC_ENVIRONMENT', ''), // 'production' or 'sandbox'
        'api_url' => env('QUICKEKYC_API_URL',''),
        'sandbox_url' => env('QUICKEKYC_SANDBOX_URL', ''),
        'api_key' => env('QUICKEKYC_API_KEY', ''),
        'sandbox_api_key' => env('QUICKEKYC_SANDBOX_API_KEY', ''),
        'webhook_secret' => env('QUICKEKYC_WEBHOOK_SECRET', ''),
    ],

    'augmont' => [
        'api_url' => env('AUGMONT_API_URL', 'https://uat-api.augmontgold.com/api/merchant/v1'),
        'email' => env('AUGMONT_EMAIL', ''),
        'password' => env('AUGMONT_PASSWORD', ''),
        'webhook_secret' => env('AUGMONT_WEBHOOK_SECRET', ''),
    ],

    'razorpay' => [
        'key_id' => env('RAZORPAY_KEY_ID', ''),
        'key_secret' => env('RAZORPAY_KEY_SECRET', ''),
        'webhook_secret' => env('RAZORPAY_WEBHOOK_SECRET', ''),
    ],

    'twilio' => [
        'account_sid' => env('TWILIO_ACCOUNT_SID', ''),
        'auth_token' => env('TWILIO_AUTH_TOKEN', ''),
        'phone_number' => env('TWILIO_PHONE_NUMBER', ''),
    ],

];
