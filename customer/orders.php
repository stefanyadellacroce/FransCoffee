<?php

declare(strict_types=1);
require_once __DIR__ . '/../includes/bootstrap.php';
require_role('CUSTOMER');
$userId = (int) current_user()['id'];
$status = strtoupper(trim((string)($_GET['status'] ?? 'ALL')));
$allowed = ['ALL','PENDING','CONFIRMED','PREPARING','READY','OUT_FOR_DELIVERY','COMPLETED','CANCELLED'];
if (!in_array($status,$allowed,true)) $status='ALL';
$orders = customer_orders($userId,$status,100);
$counts = customer_order_counts($userId);
$pageTitle = 'My Orders';
require __DIR__ . '/../includes/header.php';
?>
<div class="portal-shell"><?php require __DIR__ . '/../includes/sidebar.php'; ?><main class="portal-main customer-portal-main">
<div class="portal-page-header"><div><span class="eyebrow">ORDER HISTORY</span><h1 class="mt-2">My Orders</h1><p>Review current orders and your complete Franscoffee order history.</p></div><a class="btn btn-coffee" href="<?= url('menu.php') ?>">Order again</a></div>
<div class="order-filter-row" role="navigation" aria-label="Order filters"><?php foreach ($allowed as $filter): ?><a class="order-filter-chip <?= $status===$filter?'active':'' ?>" href="<?= url('customer/orders.php?status='.$filter) ?>"><?= e(customer_status_label($filter)) ?><span><?= (int)($counts[$filter]??0) ?></span></a><?php endforeach; ?></div>
<?php if (!$orders): ?><div class="empty-state mt-4"><i class="bi bi-receipt"></i><h2>No orders here yet.</h2><p><?= $status==='ALL'?'Your Franscoffee orders will appear here after checkout.':'There are no '.strtolower(customer_status_label($status)).' orders.' ?></p><a class="btn btn-coffee" href="<?= url('menu.php') ?>">Browse menu</a></div>
<?php else: ?><div class="customer-order-list mt-4"><?php foreach ($orders as $order): ?><article class="customer-order-card"><div class="order-card-main"><div><div class="d-flex flex-wrap align-items-center gap-2"><strong class="order-card-number"><?= e($order['order_number']) ?></strong><span class="badge <?= e(status_badge_class((string)$order['status'])) ?>"><?= e(customer_status_label((string)$order['status'])) ?></span></div><p><?= e(customer_datetime($order['placed_at'])) ?> · <?= e($order['fulfillment_type']==='DELIVERY'?'Delivery':'Store pickup') ?> · <?= (int)$order['item_quantity'] ?> item<?= (int)$order['item_quantity']===1?'':'s' ?></p></div><div class="order-card-total"><span>Total</span><strong><?= money((float)$order['total_amount']) ?></strong></div></div><div class="order-card-footer"><span>Payment: <strong><?= e((string)($order['payment_method']??'—')) ?></strong> · <span class="payment-text <?= strtolower((string)($order['payment_status']??'unpaid')) ?>"><?= e((string)($order['payment_status']??'UNPAID')) ?></span></span><a class="btn btn-sm btn-outline-coffee" href="<?= url('customer/order-details.php?order='.rawurlencode((string)$order['order_number'])) ?>">View details</a></div></article><?php endforeach; ?></div><?php endif; ?>
</main></div>
<?php require __DIR__ . '/../includes/footer.php'; ?>
