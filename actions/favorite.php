<?php

declare(strict_types=1);
require_once __DIR__ . '/../includes/bootstrap.php';

if (!is_post()) {
    http_response_code(405);
    exit('Method not allowed');
}
verify_csrf_or_abort();

$returnTo = commerce_return_path($_POST['return_to'] ?? null, 'menu.php');
if (!is_logged_in()) {
    remember_intended_url($returnTo);
    flash('error', 'Sign in with a customer account to save favorites.');
    redirect('login.php');
}
if (user_role() !== 'CUSTOMER') {
    flash('error', 'Favorites are available for customer accounts.');
    redirect($returnTo);
}

$productId = (int) ($_POST['product_id'] ?? 0);
$result = favorite_toggle($productId, (int) current_user()['id']);
if (!$result['ok']) {
    flash('error', 'That product is not available.');
} else {
    flash('success', $result['saved'] ? 'Saved to favorites.' : 'Removed from favorites.');
}
redirect($returnTo);
