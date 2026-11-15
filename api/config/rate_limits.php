<?php

declare(strict_types=1);

return [
    // A React ERP page legitimately fans out into dashboard, notification,
    // badge, and table queries. Guests remain conservative while signed-in
    // users have room for a fast defense walkthrough.
    'api_guest_per_minute' => (int) env('API_GUEST_RATE_LIMIT', 60),
    // A mounted ERP route can authorize many private channels and issue a
    // burst of page queries at once. Keep authenticated traffic bounded, but
    // leave enough headroom that normal SPA fan-out does not lock the user out.
    'api_authenticated_per_minute' => (int) env('API_AUTHENTICATED_RATE_LIMIT', 1200),
    // Reverb signs each private channel separately. Keep reconnect bursts from
    // consuming the same budget used by normal page and session requests.
    'api_realtime_auth_per_minute' => (int) env('API_REALTIME_AUTH_RATE_LIMIT', 1800),
];
