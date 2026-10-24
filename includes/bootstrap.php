<?php

declare(strict_types=1);

$APP_CONFIG = require __DIR__ . '/../config.php';

date_default_timezone_set($APP_CONFIG['app']['timezone'] ?? 'Asia/Manila');

if (!headers_sent()) {
    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: SAMEORIGIN');
    header('Referrer-Policy: strict-origin-when-cross-origin');
    header('Permissions-Policy: camera=(), microphone=(), geolocation=()');
}

if (session_status() !== PHP_SESSION_ACTIVE) {
    $sessionLifetime = (int) ($APP_CONFIG['session']['lifetime'] ?? 7200);
    ini_set('session.use_strict_mode', '1');
    ini_set('session.gc_maxlifetime', (string) $sessionLifetime);
    session_name($APP_CONFIG['session']['name'] ?? 'franscoffee_session');
    session_set_cookie_params([
        'lifetime' => $sessionLifetime,
        'path' => '/',
        'secure' => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_start();
}

require_once __DIR__ . '/functions.php';
require_once __DIR__ . '/csrf.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/storefront.php';
require_once __DIR__ . '/commerce.php';
require_once __DIR__ . '/checkout.php';
require_once __DIR__ . '/customer.php';
require_once __DIR__ . '/pos.php';
require_once __DIR__ . '/orders.php';

require_once __DIR__ . '/catalog.php';
require_once __DIR__ . '/inventory.php';

require_once __DIR__ . '/finance.php';
require_once __DIR__ . '/admin.php';
require_once __DIR__ . '/reports.php';

validate_authenticated_session();
