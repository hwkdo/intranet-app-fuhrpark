<?php

declare(strict_types=1);

use Prism\Relay\Enums\Transport;

return [
    'servers' => [
        'fuhrpark' => [
            'transport' => Transport::Http,
            'url' => env('RELAY_FUHRPARK_SERVER_URL', 'http://localhost/mcp/apps/fuhrpark'),
            'timeout' => env('RELAY_FUHRPARK_SERVER_TIMEOUT', 30),
            'headers' => [
                // Bearer Token wird dynamisch zur Laufzeit hinzugefügt.
            ],
        ],
    ],
];
