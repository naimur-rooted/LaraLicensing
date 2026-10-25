<?php

return [
    'paths' => ['*'],
    'allowed_methods' => ['*'],
    'allowed_origins' => ['*'],
    'allowed_origins_patterns' => [],
    'allowed_headers' => ['*'],
    'exposed_headers' => ['X-Request-Id', 'ETag', 'X-Lara-KeyId', 'X-Lara-Timestamp', 'X-Lara-Nonce', 'X-Lara-Signature'],
    'max_age' => 0,
    'supports_credentials' => false,
];
