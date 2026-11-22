<?php

return [
    'base_url' => 'https://sefar-universal-inc.app.kalendia.co/wp-json/kalendia/v1/automations',
    'token' => env('KALENDIA_TOKEN'),
    'timezone' => 'America/Bogota',
    // Must be a method confirmed by Kalendia for payments already received in the App.
    'prepaid_payment_method' => env('KALENDIA_PREPAID_PAYMENT_METHOD'),
    'stripe_webhook_secret' => env('STRIPE_KALENDIA_WEBHOOK_SECRET'),
    'webhook_secret' => env('KALENDIA_WEBHOOK_SECRET'),
    'webhook_business_id' => env('KALENDIA_WEBHOOK_BUSINESS_ID'),
];
