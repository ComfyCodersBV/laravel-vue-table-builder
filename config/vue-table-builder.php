<?php

declare(strict_types=1);

return [
    'default_per_page' => 25,
    'per_page_options' => [
        10,
        25,
        50,
        100,
    ],

    'routes' => [
        'middleware' => [],
        'signature_ttl' => 720,
    ],

    'display_timezone' => null,

    'export' => [
        'delimiter' => null,
        'decimal_separator' => null,
        'chunk_size' => 1000,
    ],
];
