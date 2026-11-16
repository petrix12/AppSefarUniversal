<?php

return [
    // Enable reception separately from switching the COS to local reads.
    'hubspot' => [
        'enabled' => env('CRM_HUBSPOT_WEBHOOKS_ENABLED', false),
        'client_secret' => env('CRM_HUBSPOT_CLIENT_SECRET'),
        'portal_id' => env('CRM_HUBSPOT_PORTAL_ID'),
        'signature_version' => env('CRM_HUBSPOT_SIGNATURE_VERSION', 'v3'),
    ],
    'teamleader' => [
        'enabled' => env('CRM_TEAMLEADER_WEBHOOKS_ENABLED', false),
        'secret' => env('CRM_TEAMLEADER_WEBHOOK_SECRET'),
        'account_id' => env('CRM_TEAMLEADER_ACCOUNT_ID'),
    ],
    // Activate only after initial reconciliation, subscriptions and workers are verified.
    'local_reads_enabled' => env('CRM_LOCAL_READS_ENABLED', false),
];
