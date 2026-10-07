<?php

return [
    'enabled' => (bool) env('SYSTEMS_ACTIVITY_REPORT_ENABLED', false),
    'timezone' => env('SYSTEMS_ACTIVITY_REPORT_TIMEZONE', 'America/Caracas'),
    'run_at' => env('SYSTEMS_ACTIVITY_REPORT_RUN_AT', '08:00'),
    'user_emails' => array_values(array_filter(array_map(
        static fn (string $email): string => strtolower(trim($email)),
        explode(',', (string) env('SYSTEMS_ACTIVITY_REPORT_USERS', ''))
    ))),
    'sources' => ['app', 'monday', 'hubspot', 'teamleader'],
    'trello' => [
        'key' => env('TRELLO_API_KEY'),
        'token' => env('TRELLO_API_TOKEN'),
        'board_shortlink' => env('TRELLO_SYSTEMS_BOARD_SHORTLINK', 'MCT8q89x'),
        'list_name' => env('TRELLO_SYSTEMS_REPORT_LIST', 'Hecho'),
    ],
];
