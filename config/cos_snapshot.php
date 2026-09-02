<?php

return [
    /*
    | El lote programado solo considera clientes con pay > 1 y contrato = 1.
    | Un cliente se vuelve elegible otra vez cuando vence arraycos_expire.
    */
    'cache_ttl_days' => (int) env('COS_SNAPSHOT_CACHE_TTL_DAYS', 30),

    // Límite de clientes por corrida diaria. Ajustar según las cuotas de las APIs.
    'daily_limit' => (int) env('COS_SNAPSHOT_DAILY_LIMIT', 50),

    // Pausa entre clientes para distribuir las llamadas a HubSpot, Teamleader y Monday.
    'inter_client_delay_seconds' => (int) env('COS_SNAPSHOT_INTER_CLIENT_DELAY_SECONDS', 2),

    // El primer cálculo también informa al cliente de su estatus actual.
    'notify_on_initial_snapshot' => (bool) env('COS_SNAPSHOT_NOTIFY_ON_INITIAL', true),
];
