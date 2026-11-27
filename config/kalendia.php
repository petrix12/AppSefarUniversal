<?php

return [
    'base_url' => 'https://sefar-universal-inc.app.kalendia.co/wp-json/kalendia/v1/automations',
    'token' => env('KALENDIA_TOKEN'),
    'timezone' => 'America/Bogota',
    'currency' => env('KALENDIA_CURRENCY', 'EUR'),
    'stripe_webhook_secret' => env('STRIPE_KALENDIA_WEBHOOK_SECRET'),
    'webhook_secret' => env('KALENDIA_WEBHOOK_SECRET'),
    'webhook_business_id' => env('KALENDIA_WEBHOOK_BUSINESS_ID'),
];
