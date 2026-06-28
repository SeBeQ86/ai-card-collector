<?php

declare(strict_types=1);

return [
    'name'     => 'AI Card Collector',
    'env'      => getenv('APP_ENV') ?: 'local',
    'base_url' => getenv('APP_URL')  ?: 'http://localhost/ai-card-collector/public',

    'db' => [
        'host'    => getenv('DB_HOST')     ?: '127.0.0.1',
        'port'    => getenv('DB_PORT')     ?: '3306',
        'name'    => getenv('DB_DATABASE') ?: 'ai_card_collector',
        'user'    => getenv('DB_USERNAME') ?: 'root',
        'pass'    => getenv('DB_PASSWORD') ?: '',
        'charset' => 'utf8mb4',
    ],
];
