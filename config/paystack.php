<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Paystack Keys
    |--------------------------------------------------------------------------
    |
    | Get your keys from: https://dashboard.paystack.co/#/settings/developer
    |
    */

    'secret_key' => env('PAYSTACK_SECRET_KEY'),
    'public_key' => env('PAYSTACK_PUBLIC_KEY'),

    /*
    |--------------------------------------------------------------------------
    | Paystack API URL
    |--------------------------------------------------------------------------
    */

    'base_url' => env('PAYSTACK_URL', 'https://api.paystack.co'),

    /*
    |--------------------------------------------------------------------------
    | Merchant Email
    |--------------------------------------------------------------------------
    */

    'merchant_email' => env('PAYSTACK_MERCHANT_EMAIL'),

    /*
    |--------------------------------------------------------------------------
    | Payment Callback URL
    |--------------------------------------------------------------------------
    */

    'callback_url' => env('PAYSTACK_CALLBACK_URL', '/api/payments/callback'),

    /*
    |--------------------------------------------------------------------------
    | Webhook URL
    |--------------------------------------------------------------------------
    |
    | Register this URL in your Paystack dashboard for webhooks
    |
    */

    'webhook_url' => env('PAYSTACK_WEBHOOK_URL', '/api/payments/webhook'),

    /*
    |--------------------------------------------------------------------------
    | Default Currency
    |--------------------------------------------------------------------------
    |
    | Supported: NGN, GHS, ZAR, USD, KES
    |
    */

    'currency' => env('PAYSTACK_CURRENCY', 'KES'),

    /*
    |--------------------------------------------------------------------------
    | Subscription Plans
    |--------------------------------------------------------------------------
    |
    | Define your organizer subscription plans here
    | Amounts in USD cents (multiply by 100)
    |
    */

    'plans' => [
        'starter' => [
            'name' => 'Starter',
            'amount' => 0, // Free
            'currency' => 'USD',
            'interval' => 'monthly',
            'tournaments_limit' => 2,
            'players_limit' => 16,
            'can_collect_entry_fee' => false,
            'entry_fee_percentage' => 0,
            'entry_fee_flat' => 0,
            'show_branding' => true,
            'organizer_accounts' => 1,
            'description' => 'Perfect for trying out tournaments',
            'features' => [
                'Up to 16 players per tournament',
                '2 tournaments per month',
                'Basic bracket management',
                'Manual result entry',
                'Community support',
            ],
            'limitations' => [
                'CueSports branding',
                'No entry fee collection',
            ],
        ],
        'pro' => [
            'name' => 'Pro',
            'amount' => 1200, // $12.00 USD
            'currency' => 'USD',
            'interval' => 'monthly',
            'tournaments_limit' => null, // unlimited
            'players_limit' => null, // unlimited
            'can_collect_entry_fee' => true,
            'entry_fee_percentage' => 3, // 3%
            'entry_fee_flat' => 500, // KES 5 (in cents)
            'entry_fee_flat_currency' => 'KES',
            'show_branding' => false,
            'organizer_accounts' => 1,
            'is_popular' => true,
            'description' => 'For serious tournament organizers',
            'features' => [
                'Unlimited players',
                'Unlimited tournaments',
                'Elo rating integration',
                'Online entry fee collection',
                'Player notifications',
                'Remove CueSports branding',
                'Export results & reports',
                'Priority email support',
            ],
        ],
        'business' => [
            'name' => 'Business',
            'amount' => 3900, // $39.00 USD
            'currency' => 'USD',
            'interval' => 'monthly',
            'tournaments_limit' => null, // unlimited
            'players_limit' => null, // unlimited
            'can_collect_entry_fee' => true,
            'entry_fee_percentage' => 2, // 2% flat
            'entry_fee_flat' => 0, // no flat fee
            'show_branding' => false,
            'organizer_accounts' => 5,
            'description' => 'For venues & tournament series',
            'features' => [
                'Everything in Pro',
                '5 organizer accounts',
                'Recurring tournaments',
                'League management',
                'Custom branding',
                'Analytics dashboard',
                'API access',
                'Dedicated support',
            ],
        ],
    ],
];
