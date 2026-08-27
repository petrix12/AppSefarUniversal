<?php

return [
    /*
     * Safety gate for projecting current operations into the new canonical
     * data layer. It stays false until the field/process audit has an
     * explicit approval. Existing HubSpot, Monday and Teamleader processes
     * continue to use their current paths while this is disabled.
     */
    'canonical_writes_enabled' => env('UNIFICATION_CANONICAL_WRITES_ENABLED', false),
];
