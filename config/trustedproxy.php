<?php

return [
    // Explicit proxy IPs only. Empty by default; never trust every client.
    'proxies' => array_values(array_filter(array_map('trim', explode(',', (string) env('TRUSTED_PROXIES', ''))))),
];
