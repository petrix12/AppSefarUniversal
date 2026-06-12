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
    ],

    'postmark' => [
        'token' => env('POSTMARK_TOKEN'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'teamleader' => [
        'client_id' => env('TEAMLEADER_CLIENT_ID'),
        'client_secret' => env('TEAMLEADER_CLIENT_SECRET'),
        'redirect' => env('TEAMLEADER_REDIRECT_URI'),
        'cron_token' => env('TEAMLEADER_CRON_TOKEN'),
        'min_request_gap_ms' => env('TEAMLEADER_MIN_REQUEST_GAP_MS', 800),
        'sync_pages_per_job' => env('TEAMLEADER_SYNC_PAGES_PER_JOB', 2),
        'sync_chunk_size' => env('TEAMLEADER_SYNC_CHUNK_SIZE', 5),
        'sync_chunk_delay_seconds' => env('TEAMLEADER_SYNC_CHUNK_DELAY_SECONDS', 12),
        'download_invoice_pdfs' => env('TEAMLEADER_DOWNLOAD_INVOICE_PDFS', false),
    ],

    'n8n' => [
        'webhook_token' => env('N8N_WEBHOOK_TOKEN'),
    ],

    'jotform' => [
        'cron_token' => env('JOTFORM_CRON_TOKEN'),
    ],

    'tasks_daily_workflow' => [
        'token' => env('TASKS_DAILY_WORKFLOW_TOKEN'),
    ],

    'hubspot' => [
        'coordinator_user_provisioning' => [
            'enabled' => env('HUBSPOT_COORDINATOR_USER_PROVISIONING', true),
            'role_id' => env('HUBSPOT_COORDINATOR_ROLE_ID'),
            'primary_team_id' => env('HUBSPOT_COORDINATOR_PRIMARY_TEAM_ID'),
            'secondary_team_ids' => array_values(array_filter(array_map(
                'trim',
                explode(',', (string) env('HUBSPOT_COORDINATOR_SECONDARY_TEAM_IDS', ''))
            ))),
            'send_welcome_email' => env('HUBSPOT_COORDINATOR_SEND_WELCOME_EMAIL', true),
        ],
    ],

];
