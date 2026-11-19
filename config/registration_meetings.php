<?php

return [
    'enabled' => (bool) env('REGISTRATION_MEETING_WEBHOOK_ENABLED', true),
    'url' => env('REGISTRATION_MEETING_WEBHOOK_URL', 'https://ia.sefaruniversal.com/webhook/33428247-fdd4-4794-9ec1-5020c63c49aa'),
    'test_url' => env('REGISTRATION_MEETING_WEBHOOK_TEST_URL', 'https://ia.sefaruniversal.com/webhook-test/33428247-fdd4-4794-9ec1-5020c63c49aa'),
];
