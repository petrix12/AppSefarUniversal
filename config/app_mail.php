<?php

$dedicatedCredentials = filled(env('APP_MAIL_SES_ACCESS_KEY_ID')) || filled(env('APP_MAIL_SES_SECRET_ACCESS_KEY'));

return [
    // Filled from mail.default by AppMailServiceProvider before installing the router.
    'original_mailer' => null,
    'ses' => [
        'key' => $dedicatedCredentials ? env('APP_MAIL_SES_ACCESS_KEY_ID') : env('MARKETING_SES_ACCESS_KEY_ID'),
        'secret' => $dedicatedCredentials ? env('APP_MAIL_SES_SECRET_ACCESS_KEY') : env('MARKETING_SES_SECRET_ACCESS_KEY'),
        'region' => env('APP_MAIL_SES_REGION', env('MARKETING_SES_REGION', 'us-east-1')),
        'from_email' => env('APP_MAIL_SES_FROM_EMAIL', env('MARKETING_SES_FROM_EMAIL', env('MAIL_FROM_ADDRESS'))),
        'from_name' => env('APP_MAIL_SES_FROM_NAME', env('MAIL_FROM_NAME', 'App Sefar Universal')),
        'reply_to' => env('APP_MAIL_SES_REPLY_TO', env('MAIL_FROM_ADDRESS')),
        'configuration_set' => env('APP_MAIL_SES_CONFIGURATION_SET', env('MARKETING_SES_CONFIGURATION_SET')),
    ],
    'ses_sns_topic_arns' => array_values(array_filter(array_map('trim', explode(',', (string)
        env('APP_MAIL_SES_SNS_TOPIC_ARNS', env('MARKETING_SES_SNS_TOPIC_ARNS', '')))))),
];
