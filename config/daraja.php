<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Daraja API Environment
    |--------------------------------------------------------------------------
    | 'sandbox' or 'production'
    */
    'environment' => env('DARAJA_ENVIRONMENT', 'sandbox'),

    /*
    |--------------------------------------------------------------------------
    | API Credentials
    |--------------------------------------------------------------------------
    */
    'consumer_key' => env('DARAJA_CONSUMER_KEY'),
    'consumer_secret' => env('DARAJA_CONSUMER_SECRET'),

    /*
    |--------------------------------------------------------------------------
    | Business Short Code (Paybill or Till Number)
    |--------------------------------------------------------------------------
    */
    'shortcode' => env('DARAJA_SHORTCODE'),
    'passkey' => env('DARAJA_PASSKEY'),

    /*
    |--------------------------------------------------------------------------
    | B2C Configuration (for payouts)
    |--------------------------------------------------------------------------
    */
    'b2c_shortcode' => env('DARAJA_B2C_SHORTCODE'),
    'initiator_name' => env('DARAJA_INITIATOR_NAME'),
    'initiator_password' => env('DARAJA_INITIATOR_PASSWORD'),
    'security_credential' => env('DARAJA_SECURITY_CREDENTIAL'),

    /*
    |--------------------------------------------------------------------------
    | Callback URLs
    |--------------------------------------------------------------------------
    */
    'stk_callback_url' => env('DARAJA_STK_CALLBACK_URL', '/api/payments/mpesa/callback'),
    'b2c_result_url' => env('DARAJA_B2C_RESULT_URL', '/api/payouts/mpesa/result'),
    'b2c_timeout_url' => env('DARAJA_B2C_TIMEOUT_URL', '/api/payouts/mpesa/timeout'),

    /*
    |--------------------------------------------------------------------------
    | API Base URLs
    |--------------------------------------------------------------------------
    */
    'base_url' => [
        'sandbox' => 'https://sandbox.safaricom.co.ke',
        'production' => 'https://api.safaricom.co.ke',
    ],

    /*
    |--------------------------------------------------------------------------
    | Default Currency
    |--------------------------------------------------------------------------
    */
    'currency' => 'KES',
];
