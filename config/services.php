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

    'google' => [
        'client_id' => env('GOOGLE_CLIENT_ID'),
        'client_secret' => env('GOOGLE_CLIENT_SECRET'),
        'redirect' => env('GOOGLE_REDIRECT_URI', rtrim((string) env('APP_URL'), '/') . '/registerv2/google/callback'),
    ],

    'teamleader' => [
        'client_id' => env('TEAMLEADER_CLIENT_ID'),
        'client_secret' => env('TEAMLEADER_CLIENT_SECRET'),
        'redirect' => env('TEAMLEADER_REDIRECT_URI'),
        'min_request_gap_ms' => env('TEAMLEADER_MIN_REQUEST_GAP_MS', 800),
        'sync_pages_per_job' => env('TEAMLEADER_SYNC_PAGES_PER_JOB', 2),
        'sync_chunk_size' => env('TEAMLEADER_SYNC_CHUNK_SIZE', 5),
        'sync_chunk_delay_seconds' => env('TEAMLEADER_SYNC_CHUNK_DELAY_SECONDS', 12),
        'sync_documents' => env('TEAMLEADER_SYNC_DOCUMENTS', false),
        'download_invoice_pdfs' => env('TEAMLEADER_DOWNLOAD_INVOICE_PDFS', false),
    ],

    'monday' => [
        'token' => env('MONDAY_TOKEN'),
        'api_version' => env('MONDAY_API_VERSION', '2026-10'),
        'connect_timeout' => env('MONDAY_CONNECT_TIMEOUT', 10),
        'timeout' => env('MONDAY_TIMEOUT', 45),
        'mirror_enabled' => env('MONDAY_MIRROR_ENABLED', false),
        'mirror_page_size' => env('MONDAY_MIRROR_PAGE_SIZE', 100),
        'mirror_reads' => env('MONDAY_MIRROR_READS', true),
        'webhook_secret' => env('MONDAY_WEBHOOK_SECRET') ?: (
            env('APP_KEY')
                ? hash_hmac('sha256', 'monday-webhook', (string) env('APP_KEY'))
                : null
        ),
    ],

    'stripe' => [
        'banca_online_webhook_secret' => env('STRIPE_BANCA_ONLINE_WEBHOOK_SECRET', env('STRIPE_WEBHOOK_SECRET')),
    ],

    'openrouter' => [
        'key' => env('OPENROUTER_API_KEY'),
        'url' => 'https://openrouter.ai/api/v1/chat/completions',
        'model' => env('OPENROUTER_MODEL', 'google/gemini-3.5-flash-lite'),
        // Dedicated model for factual Systems activity summaries. Keep this
        // separate from the application's existing OpenRouter use cases.
        'systems_activity_model' => env('OPENROUTER_SYSTEMS_ACTIVITY_MODEL', 'google/gemini-3.5-flash-lite'),
        'systems_activity_timeout' => env('OPENROUTER_SYSTEMS_ACTIVITY_TIMEOUT', 30),
        // Legacy environment defaults; saved per-process settings take precedence.
        'unification_model' => env('OPENROUTER_UNIFICATION_MODEL', 'google/gemini-3.5-flash-lite'),
        // OpenRouter tries these only when the primary model/provider fails
        // (including HTTP 429), keeping the primary model as the usual path.
        'unification_fallback_models' => env('OPENROUTER_UNIFICATION_FALLBACK_MODELS', 'google/gemini-3.1-flash-lite'),
        'unification_timeout' => env('OPENROUTER_UNIFICATION_TIMEOUT', 30),
        // Per-request size. Larger selected batches are split into these
        // chunks so a user can review more than one model request safely.
        'unification_max_candidates' => env('OPENROUTER_UNIFICATION_MAX_CANDIDATES', 40),
        'unification_max_batch_candidates' => env('OPENROUTER_UNIFICATION_MAX_BATCH_CANDIDATES', 200),
        'unification_response_format' => env('OPENROUTER_UNIFICATION_RESPONSE_FORMAT', 'json_object'),
        'auto_association_min_confidence' => env('OPENROUTER_AUTO_ASSOCIATION_MIN_CONFIDENCE', 95),
        'auto_association_min_field_confidence' => env('TEAMLEADER_AUTO_ASSOCIATION_MIN_FIELD_CONFIDENCE', 80),
        'auto_association_min_field_signals' => env('TEAMLEADER_AUTO_ASSOCIATION_MIN_FIELD_SIGNALS', 2),
        'auto_association_min_margin' => env('OPENROUTER_AUTO_ASSOCIATION_MIN_MARGIN', 15),
        'auto_association_cooldown_minutes' => env('OPENROUTER_AUTO_ASSOCIATION_COOLDOWN_MINUTES', 1440),
        // Keep reasoning short so the output budget produces a visible answer.
        'request_options' => [
            'reasoning' => ['effort' => 'minimal', 'exclude' => true],
            'provider' => ['sort' => 'latency'],
        ],
        'timeout' => 30,
        'temperature' => 0.2,
        'max_tokens' => 900,
        'role_ai_session_minutes' => 120,
    ],

    'hubspot' => [
        'key' => env('HUBSPOT_KEY'),
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
