<?php

return [
    // Receive and verify events before allowing them to mutate client profiles.
    'processing_enabled' => env('CRM_EVENT_PROCESSING_ENABLED', false),
    // Enable reception separately from switching the COS to local reads.
    'hubspot' => [
        'enabled' => env('CRM_HUBSPOT_WEBHOOKS_ENABLED', false),
        'outbound_enabled' => env('CRM_HUBSPOT_OUTBOUND_ENABLED', false),
        // Shared by import/sync jobs; keep the default aligned with the app's existing 250 ms gap.
        'request_rate_per_second' => max(1, (int) env('CRM_HUBSPOT_REQUEST_RATE_PER_SECOND', 4)),
        // App-side daily ceiling; set from the portal's remaining shared quota before large imports.
        'daily_request_budget' => max(0, (int) env('CRM_HUBSPOT_DAILY_REQUEST_BUDGET', 10000)),
        // Stores full properties for each subscribed HubSpot object type in the private mirror tables.
        'object_mirror_enabled' => env('CRM_HUBSPOT_OBJECT_MIRROR_ENABLED', true),
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
