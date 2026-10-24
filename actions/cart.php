<?php

declare(strict_types=1);
require_once __DIR__ . '/../includes/bootstrap.php';

if (!is_post()) {
    http_response_code(405);
    exit('Method not allowed');
}
verify_csrf_or_abort();

if (is_logged_in() && user_role() !== 'CUSTOMER') {
    flash('error', 'The shopping cart is available for customer accounts.');
    redirect(role_home((string) user_role()));
}

$action = trim((string) ($_POST['action'] ?? 'add'));
$returnTo = commerce_return_path($_POST['return_to'] ?? null, 'cart.php');

try {
    if ($action === 'add') {
        $productId = (int) ($_POST['product_id'] ?? 0);
        $result = cart_add($productId, $_POST);
        if (!$result['ok']) {
            flash('error', (string) ($result['error'] ?? 'Unable to add that item.'));
        } else {
            flash('success', 'Added to your cart.');
        }
    } elseif ($action === 'update') {
        $itemId = (int) ($_POST['item_id'] ?? 0);
        $quantity = (int) ($_POST['quantity'] ?? 1);
        if (!cart_update_quantity($itemId, $quantity)) flash('error', 'Cart item could not be updated.');
        else flash('success', 'Cart updated.');
    } elseif ($action === 'remove') {
        $itemId = (int) ($_POST['item_id'] ?? 0);
        if (!cart_remove($itemId)) flash('error', 'Cart item could not be removed.');
        else flash('success', 'Item removed from your cart.');
    } elseif ($action === 'clear') {
        cart_clear();
        flash('success', 'Your cart is now empty.');
    }
} catch (Throwable $e) {
    if (app_is_debug()) {
        flash('error', 'Cart error: ' . $e->getMessage());
    } else {
        flash('error', 'Something went wrong while updating your cart.');
    }
}

redirect($returnTo);
