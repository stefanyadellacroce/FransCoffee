<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

$port = getenv('FRANSCOFFEE_TEST_DB_PORT');
if (!is_string($port) || !ctype_digit($port) || (int) $port < 1 || (int) $port > 65535 || (int) $port === 3306) {
    fwrite(STDERR, "Set FRANSCOFFEE_TEST_DB_PORT to a separate test database port.\n");
    exit(1);
}

session_save_path(__DIR__);
require __DIR__ . '/../includes/bootstrap.php';
$APP_CONFIG['database']['port'] = $port;

$searches = [
    'storefront products' => fn() => storefront_products(['q' => 'latte']),
    'admin products' => fn() => admin_products(['q' => 'latte']),
    'admin users' => fn() => admin_users(['q' => 'francis']),
    'activity logs' => fn() => admin_activity_logs(['q' => 'LOGIN']),
    'payments' => fn() => admin_payments(['q' => 'FCS']),
    'expenses' => fn() => admin_expenses(['q' => 'Rent']),
    'promotions' => fn() => admin_promotions(['q' => 'WELCOME']),
    'orders' => fn() => ops_orders(['q' => 'FCS']),
    'customers' => fn() => ops_customers(['q' => 'francis']),
    'suppliers' => fn() => admin_suppliers(['q' => 'coffee']),
    'inventory items' => fn() => admin_inventory_items(['q' => 'coffee']),
    'inventory transactions' => fn() => admin_inventory_transactions(['q' => 'coffee']),
];

foreach ($searches as $name => $search) {
    $result = $search();
    if (!is_array($result)) {
        throw new RuntimeException("Invalid result from {$name} search.");
    }
    echo "OK: {$name} search\n";
}

$email = 'smoke-' . bin2hex(random_bytes(8)) . '@example.test';
$result = register_customer([
    'first_name' => 'Smoke',
    'last_name' => 'Test',
    'email' => $email,
    'phone' => '09123456789',
    'password' => 'Test12345!',
    'confirm_password' => 'Test12345!',
]);
if (empty($result['ok'])) {
    throw new RuntimeException('Customer registration failed: ' . json_encode($result['errors'] ?? []));
}
$userId = (int) $result['user_id'];
try {
    $user = find_user_by_email($email);
    if (!$user) {
        throw new RuntimeException('Registered customer could not be found.');
    }
    echo "OK: customer registration\n";

    $_SESSION['user'] = ['id' => $userId, 'role' => 'CUSTOMER'];
    $first = cart_add(1, ['quantity' => 20]);
    $second = cart_add(1, ['quantity' => 5]);
    if (empty($first['ok']) || empty($second['ok']) || cart_count() !== 20) {
        throw new RuntimeException('Cart quantity did not stop at 20.');
    }
    $cart = cart_items();
    $invalidCart = $cart;
    $invalidCart[0]['quantity'] = 21;
    if (empty(checkout_reprice_cart($cart)['ok']) || !empty(checkout_reprice_cart($invalidCart)['ok'])) {
        throw new RuntimeException('Checkout quantity validation failed.');
    }
    echo "OK: cart quantity limit\n";
} finally {
    unset($_SESSION['user']);
    db()->prepare('DELETE FROM users WHERE id = :id')->execute(['id' => $userId]);
    session_destroy();
}
