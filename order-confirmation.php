<?php

declare(strict_types=1);
require_once __DIR__ . '/includes/bootstrap.php';
require_role('CUSTOMER');

$orderNumber = trim((string) ($_GET['order'] ?? ''));
$order = $orderNumber !== '' ? checkout_order_for_customer($orderNumber, (int) current_user()['id']) : null;
if (!$order) {
    http_response_code(404);
    $pageTitle = 'Order not found';
    $footerMode = 'public';
    require __DIR__ . '/includes/header.php';
    require __DIR__ . '/includes/navbar.php';
    echo '<main class="store-section"><div class="container-fluid fc-container"><div class="empty-state"><i class="bi bi-receipt"></i><h1>Order not found.</h1><p>That order is not available for this account.</p><a class="btn btn-coffee" href="'.e(url('menu.php')).'">Back to menu</a></div></div></main>';
    require __DIR__ . '/includes/footer.php';
    exit;
}
$success = flash('success');
$pageTitle = 'Order ' . $order['order_number'];
$footerMode = 'public';
require __DIR__ . '/includes/header.php';
require __DIR__ . '/includes/navbar.php';
?>
<main>
<section class="confirmation-hero"><div class="container-fluid fc-container text-center"><div class="confirmation-icon"><i class="bi bi-check2"></i></div><span class="eyebrow">ORDER RECEIVED</span><h1>Thank you, <?= e(current_user()['first_name']) ?>.</h1><p>Your order has been placed successfully. Keep your order number for reference.</p><div class="order-number-pill"><?= e($order['order_number']) ?></div></div></section>
<section class="store-section pt-4"><div class="container-fluid fc-container"><div class="row g-4 align-items-start"><div class="col-lg-8">
<?php if ($success): ?><div class="alert alert-success"><?= e($success) ?></div><?php endif; ?>
<section class="checkout-card"><div class="section-heading compact"><span class="eyebrow">ORDER DETAILS</span><h2>What you ordered.</h2></div><div class="confirmation-items"><?php foreach ($order['items'] as $item): ?><div class="confirmation-item"><div><strong><?= e($item['product_name']) ?> × <?= (int)$item['quantity'] ?></strong><?php if ($item['options']): ?><small><?= e(implode(' · ', array_map(fn($opt)=>$opt['option_name'].': '.$opt['option_value'],$item['options']))) ?></small><?php endif; ?></div><span><?= money((float)$item['line_total']) ?></span></div><?php endforeach; ?></div></section>
<section class="checkout-card"><div class="section-heading compact"><span class="eyebrow">FULFILLMENT</span><h2><?= $order['fulfillment_type']==='DELIVERY'?'Delivery':'Store pickup' ?></h2></div><?php if ($order['fulfillment_type']==='DELIVERY' && $order['delivery_address']): ?><p class="mb-1"><strong><?= e($order['delivery_address']['label']??'Delivery address') ?></strong></p><p class="text-muted mb-0"><?= e(($order['delivery_address']['street']??'').', '.($order['delivery_address']['barangay']??'').', '.($order['delivery_address']['city']??'').', '.($order['delivery_address']['province']??'')) ?><?= !empty($order['delivery_address']['postal_code'])?' '.e($order['delivery_address']['postal_code']):'' ?></p><?php else: ?><p class="text-muted mb-0">Pickup at <?= e(shop_setting('store_address','Cebu City, Cebu')) ?>. We’ll update you when the order is ready.</p><?php endif; ?></section>
</div><div class="col-lg-4"><aside class="cart-summary"><span class="eyebrow">PAYMENT</span><h2><?= e((string)$order['payment_method']) ?></h2><div class="summary-row"><span>Status</span><strong><span class="badge <?= e(status_badge_class((string)$order['payment_status'])) ?>"><?= e((string)$order['payment_status']) ?></span></strong></div><?php if (!empty($order['reference_number'])): ?><div class="summary-row"><span>Reference</span><strong><?= e((string)$order['reference_number']) ?></strong></div><?php endif; ?><div class="summary-row"><span>Subtotal</span><strong><?= money((float)$order['subtotal']) ?></strong></div><div class="summary-row"><span>Delivery</span><strong><?= money((float)$order['delivery_fee']) ?></strong></div><div class="summary-total"><span>Total</span><strong><?= money((float)$order['total_amount']) ?></strong></div><?php if ($order['payment_status']==='PENDING'): ?><div class="payment-pending-note mt-3"><i class="bi bi-hourglass-split"></i><div><strong>Payment under review</strong><small>Staff will verify your submitted payment proof before marking it paid.</small></div></div><?php endif; ?><a class="btn btn-coffee w-100 mt-3" href="<?= url('customer/order-details.php?order=' . rawurlencode((string)$order['order_number'])) ?>">Track this order</a><a class="btn btn-outline-coffee w-100 mt-2" href="<?= url('menu.php') ?>">Continue browsing</a></aside></div></div></div></section>
</main>
<?php require __DIR__ . '/includes/footer.php'; ?>
