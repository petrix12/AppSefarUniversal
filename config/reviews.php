<?php

return [
    /*
     * This must be Google's direct "write a review" URL, for example:
     * https://search.google.com/local/writereview?placeid=YOUR_PLACE_ID
     * Do not use a Google Maps or Business Profile URL: those can show reviews
     * already posted by other clients.
     */
    'google_write_review_url' => env('GOOGLE_WRITE_REVIEW_URL'),

    'minimum_people' => (int) env('GOOGLE_REVIEW_MINIMUM_PEOPLE', 5),

    'new_client_days' => (int) env('GOOGLE_REVIEW_NEW_CLIENT_DAYS', 30),
];
