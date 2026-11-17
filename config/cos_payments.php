<?php

return [
    // Dedicated flow for confirmed portal receipts, independent of CRM import/webhooks.
    'crm_sync_enabled' => env('COS_PAYMENT_CRM_SYNC_ENABLED', true),
];
