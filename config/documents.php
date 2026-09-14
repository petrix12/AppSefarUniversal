<?php

return [
    // A page visit checks this timestamp, but HubSpot is only called once the
    // interval has expired. Keep it low enough for an active portal session
    // without turning ordinary navigation into API traffic.
    'hubspot_review_interval_minutes' => env('HUBSPOT_DOCUMENT_REVIEW_INTERVAL_MINUTES', 360),
];
