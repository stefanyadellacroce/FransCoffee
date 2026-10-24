<?php

declare(strict_types=1);
require_once __DIR__ . '/../includes/bootstrap.php';
require_role('CUSTOMER');

$userId = (int) current_user()['id'];
$profile = customer_profile($userId);
$activeOrder = customer_active_order($userId);
$recentOrders = customer_recent_orders($userId, 4);
$recommended = customer_recommended_products($userId, 4);
$cartItems = cart_count();
$savedItems = favorite_count($userId);
$unread = customer_unread_notification_count($userId);
$success = flash('success');
$pageTitle = 'Customer Dashboard';
require __DIR__ . '/../includes/header.php';
?>
<div class="portal-shell">
    <?php require __DIR__ . '/../includes/sidebar.php'; ?>
    <main class="portal-main customer-portal-main">
        <?php if ($success): ?><div class="alert alert-success"><?= e($success) ?></div><?php endif; ?>
        <div class="portal-page-header">
            <div><span class="eyebrow">MY FRANSCOFFEE</span><h1 class="mt-2">Hi, <?= e(current_user()['first_name'] ?? 'Francis') ?>.</h1><p>Here’s what’s happening with your coffee orders today.</p></div>
            <a class="btn btn-coffee" href="<?= url('menu.php') ?>"><i class="bi bi-cup-hot me-2"></i>Order coffee</a>
        </div>

        <section class="customer-metric-grid" aria-label="Account overview">
            <a class="customer-metric" href="<?= url('cart.php') ?>"><i class="bi bi-bag"></i><div><strong><?= $cartItems ?></strong><span>Cart items</span></div></a>
            <a class="customer-metric" href="<?= url('customer/favorites.php') ?>"><i class="bi bi-heart"></i><div><strong><?= $savedItems ?></strong><span>Favorites</span></div></a>
            <a class="customer-metric" href="<?= url('customer/orders.php') ?>"><i class="bi bi-receipt"></i><div><strong><?= count($recentOrders) ?></strong><span>Recent orders</span></div></a>
            <a class="customer-metric" href="<?= url('customer/notifications.php') ?>"><i class="bi bi-bell"></i><div><strong><?= $unread ?></strong><span>Unread notices</span></div></a>
        </section>

        <?php if ($activeOrder): ?>
        <section class="customer-panel active-order-card">
            <div class="d-flex flex-column flex-lg-row gap-3 justify-content-between align-items-lg-center">
                <div><span class="eyebrow">ACTIVE ORDER</span><h2><?= e($activeOrder['order_number']) ?></h2><p class="mb-0 text-muted">Placed <?= e(customer_datetime($activeOrder['placed_at'])) ?> · <?= e($activeOrder['fulfillment_type'] === 'DELIVERY' ? 'Delivery' : 'Store pickup') ?></p></div>
                <div class="text-lg-end"><span class="badge <?= e(status_badge_class((string)$activeOrder['status'])) ?> order-status-badge"><?= e(customer_status_label((string)$activeOrder['status'])) ?></span><div class="mt-2 fw-bold"><?= money((float)$activeOrder['total_amount']) ?></div></div>
            </div>
            <div class="mt-3"><a class="btn btn-outline-coffee" href="<?= url('customer/order-details.php?order=' . rawurlencode((string)$activeOrder['order_number'])) ?>">Track order</a></div>
        </section>
        <?php else: ?>
        <section class="customer-panel customer-empty-inline"><div><i class="bi bi-cup-straw"></i></div><div><strong>No active order right now.</strong><span>Ready when you are—your next cup is on the menu.</span></div><a class="btn btn-outline-coffee" href="<?= url('menu.php') ?>">Browse menu</a></section>
        <?php endif; ?>

        <div class="row g-4 mt-1">
            <div class="col-xl-7">
                <section class="customer-panel h-100">
                    <div class="panel-heading"><div><span class="eyebrow">ORDER HISTORY</span><h2>Recent orders</h2></div><a href="<?= url('customer/orders.php') ?>">View all</a></div>
                    <?php if ($recentOrders): ?><div class="order-mini-list">
                        <?php foreach ($recentOrders as $order): ?><a class="order-mini-item" href="<?= url('customer/order-details.php?order=' . rawurlencode((string)$order['order_number'])) ?>"><div><strong><?= e($order['order_number']) ?></strong><small><?= e(customer_datetime($order['placed_at'], 'M j, Y · g:i A')) ?> · <?= e($order['fulfillment_type'] === 'DELIVERY' ? 'Delivery' : 'Pickup') ?></small></div><div class="text-end"><span class="badge <?= e(status_badge_class((string)$order['status'])) ?>"><?= e(customer_status_label((string)$order['status'])) ?></span><strong><?= money((float)$order['total_amount']) ?></strong></div></a><?php endforeach; ?>
                    </div><?php else: ?><div class="customer-empty-small"><i class="bi bi-receipt"></i><p class="mb-0">No orders yet. Your first Franscoffee order will appear here.</p></div><?php endif; ?>
                </section>
            </div>
            <div class="col-xl-5">
                <section class="customer-panel h-100">
                    <div class="panel-heading"><div><span class="eyebrow">ACCOUNT</span><h2>Your preferences</h2></div><a href="<?= url('customer/profile.php') ?>">Edit</a></div>
                    <div class="profile-summary-row"><span>Favorite category</span><strong><?= e($profile['preferred_category'] ? ucwords(str_replace('-', ' ', (string)$profile['preferred_category'])) : 'Not set') ?></strong></div>
                    <div class="profile-summary-row"><span>Notifications</span><strong><?= !empty($profile['notifications_enabled']) ? 'On' : 'Off' ?></strong></div>
                    <div class="profile-summary-row"><span>Member since</span><strong><?= e(customer_datetime($profile['created_at'] ?? null, 'M Y')) ?></strong></div>
                    <a class="btn btn-outline-coffee w-100 mt-3" href="<?= url('customer/addresses.php') ?>">Manage saved addresses</a>
                </section>
            </div>
        </div>

        <section class="customer-panel mt-4">
            <div class="panel-heading"><div><span class="eyebrow">FOR YOU</span><h2>Recommended picks</h2></div><a href="<?= url('menu.php') ?>">Full menu</a></div>
            <?php if ($recommended): ?><div class="row g-3"><?php foreach ($recommended as $product): ?><div class="col-sm-6 col-xl-3"><a class="recommend-card" href="<?= url('product.php?slug=' . rawurlencode((string)$product['slug'])) ?>"><img src="<?= url((string)($product['image_path'] ?: 'assets/images/products/placeholder.svg')) ?>" alt="<?= e($product['name']) ?>"><span><?= e($product['category_name']) ?></span><strong><?= e($product['name']) ?></strong><small><?= money((float)$product['base_price']) ?></small></a></div><?php endforeach; ?></div><?php endif; ?>
        </section>
    </main>
</div>
<?php require __DIR__ . '/../includes/footer.php'; ?>
