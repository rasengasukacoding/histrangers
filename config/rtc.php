<?php

return [
    'turn_url' => env('RTC_TURN_URL', ''),
    'turn_shared_secret' => env('RTC_TURN_SHARED_SECRET', ''),
    'turn_ttl' => (int) env('RTC_TURN_TTL', 3600),
    'relay_only' => (bool) env('RTC_RELAY_ONLY', false),
    // Only for local demo. Static TURN credentials are deliberately disabled in production.
    'turn_username' => env('RTC_TURN_USERNAME', ''),
    'turn_credential' => env('RTC_TURN_CREDENTIAL', ''),
    'report_retention_days' => (int) env('RTC_REPORT_RETENTION_DAYS', 90),
];
