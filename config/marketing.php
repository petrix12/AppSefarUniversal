<?php

return [
    /* A dedicated SES identity keeps campaign traffic separate from the app's mailer. */
    'ses' => [
        'key' => env('MARKETING_SES_ACCESS_KEY_ID'),
        'secret' => env('MARKETING_SES_SECRET_ACCESS_KEY'),
        'region' => env('MARKETING_SES_REGION', 'us-east-1'),
        'from_email' => env('MARKETING_SES_FROM_EMAIL'),
        'from_name' => env('MARKETING_SES_FROM_NAME', env('APP_NAME')),
        'reply_to' => env('MARKETING_SES_REPLY_TO'),
        'configuration_set' => env('MARKETING_SES_CONFIGURATION_SET'),
    ],

    'queue_connection' => env('MARKETING_QUEUE_CONNECTION', 'database'),
    'queue_name' => env('MARKETING_QUEUE_NAME', 'marketing'),

    /* Comma-separated SNS topic ARNs permitted to call the public webhook. */
    'ses_sns_topic_arns' => array_values(array_filter(array_map(
        'trim',
        explode(',', (string) env('MARKETING_SES_SNS_TOPIC_ARNS', ''))
    ))),
];
