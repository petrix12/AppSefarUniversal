<?php

return [
    // External flags stop connected automations only when those automations
    // explicitly enforce them. They do not replace native CRM permissions.
    'hubspot' => [
        'enabled' => env('CLIENT_BLOCK_HUBSPOT_ENABLED', false),
        'property' => env('CLIENT_BLOCK_HUBSPOT_PROPERTY', 'sefar_entregas_bloqueadas'),
    ],
    'monday' => [
        'enabled' => env('CLIENT_BLOCK_MONDAY_ENABLED', false),
        'column' => env('CLIENT_BLOCK_MONDAY_COLUMN', 'sefar_entregas_bloqueadas'),
        'blocked_label' => env('CLIENT_BLOCK_MONDAY_LABEL', 'Bloqueado'),
        'active_label' => env('CLIENT_BLOCK_MONDAY_ACTIVE_LABEL', 'Activo'),
    ],
    'teamleader' => [
        'enabled' => env('CLIENT_BLOCK_TEAMLEADER_ENABLED', false),
        'tag' => env('CLIENT_BLOCK_TEAMLEADER_TAG', 'SEFAR_ENTREGAS_BLOQUEADAS'),
    ],
    'hubspot_poll_enabled' => env('CLIENT_BLOCK_HUBSPOT_POLL_ENABLED', false),
];
