<?php

declare(strict_types=1);

return [
    'app' => [
        'name' => 'Franscoffee Shop',
        'tagline' => 'Good Coffee, Brighter Days.',
        'base_url' => 'http://localhost/franscoffee',
        'timezone' => 'Asia/Manila',
        'environment' => 'local',
        'debug' => true,
    ],
    'database' => [
        'host' => '127.0.0.1',
        'port' => '3306',
        'name' => 'franscoffee',
        'username' => 'root',
        'password' => '',
        'charset' => 'utf8mb4',
    ],
    'session' => [
        'name' => 'franscoffee_session',
        'lifetime' => 7200,
    ],
    'auth' => [
        'reset_token_minutes' => 60,
        'max_login_attempts' => 5,
        'login_lock_seconds' => 60,
    ],
];
