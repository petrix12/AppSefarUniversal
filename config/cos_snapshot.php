<?php

return [
    // Búsqueda por enlace únicamente cuando el cliente no tiene monday_id.
    // Lista mínima de análisis y producción; los vínculos existentes se consultan por ID.
    'monday_search_boards' => [
        878831315 => 'ANÁLISIS PRELIMINAR',
        625187241 => 'ANALISIS',
        6524058079 => 'DESLINDE - SIN INFORME',
        3950637564 => 'CNAT LMD - SIN INFORME',
        3469085450 => 'CNAT LMD',
        2213224176 => 'ITALIA',
        1845710504 => 'LEY DE NIETOS',
        1845706367 => 'CONSANGUINIDAD',
        1845701215 => 'CNAT SEFARDI',
        708128239 => 'CNAT GENERAL',
        708123651 => 'SEFARDI PORTUGAL',
        669590637 => 'SEFARDI ESPAÑA',
    ],

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
