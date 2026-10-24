<?php

declare(strict_types=1);
require_once __DIR__ . '/../includes/bootstrap.php';
require_role(['STAFF', 'ADMIN']);
$orderNumber = trim((string) ($_GET['order'] ?? ''));
$order = $orderNumber !== '' ? pos_receipt($orderNumber) : null;
if (!$order) {
    http_response_code(404);
    $pageTitle = 'Receipt not found';
    require __DIR__ . '/../includes/header.php';
    echo '<main class="container py-5"><div class="auth-card auth-card-compact text-center"><div class="auth-icon danger"><i class="bi bi-receipt"></i></div><h1>Receipt not found</h1><p class="text-muted">The POS order could not be found.</p><a class="btn btn-coffee" href="' . e(url('staff/pos.php')) . '">Return to POS</a></div></main>';
    require __DIR__ . '/../includes/footer.php';
    exit;
}
$pageTitle = 'Receipt ' . $order['order_number'];
$bodyClass = 'receipt-page';
$success = flash('success');
require __DIR__ . '/../includes/header.php';
?>
<main class="receipt-shell">
    <div class="receipt-actions no-print">
        <a class="btn btn-outline-secondary" href="<?= url('staff/pos.php') ?>"><i class="bi bi-arrow-left me-1"></i>New sale</a>
        <button class="btn btn-coffee" type="button" onclick="window.print()"><i class="bi bi-printer me-1"></i>Print receipt</button>
    </div>
    <?php if ($success): ?><div class="alert alert-success no-print"><?= e($success) ?></div><?php endif; ?>
    <article class="pos-receipt">
        <header class="receipt-brand"><img src="<?= e(shop_logo_url()) ?>" alt="Franscoffee Shop"><h1><?= e(shop_setting('shop_name', 'Franscoffee Shop')) ?></h1><p><?= e(shop_setting('tagline', 'Good Coffee, Brighter Days.')) ?></p><small><?= e(shop_setting('store_address', 'Cebu City, Cebu')) ?></small></header>
        <div class="receipt-meta"><div><span>Receipt</span><strong><?= e((string) $order['order_number']) ?></strong></div><div><span>Date</span><strong><?= e(date('M d, Y · g:i A', strtotime((string) $order['placed_at']))) ?></strong></div><div><span>Cashier</span><strong><?= e((string) ($order['cashier_name'] ?: 'Staff')) ?></strong></div><div><span>Customer</span><strong><?= e((string) ($order['customer_name'] ?: 'Walk-in Customer')) ?></strong></div></div>
        <div class="receipt-items">
            <?php foreach ($order['items'] as $item): ?>
                <div class="receipt-item"><div class="receipt-item-main"><strong><?= (int) $item['quantity'] ?> × <?= e((string) $item['product_name']) ?></strong><span><?= money((float) $item['line_total']) ?></span></div>
                    <?php if ($item['options']): ?><small><?php $labels=[]; foreach ($item['options'] as $option) $labels[] = $option['option_name'] . ': ' . $option['option_value']; echo e(implode(' · ', $labels)); ?></small><?php endif; ?>
                </div>
            <?php endforeach; ?>
        </div>
        <div class="receipt-totals"><div><span>Subtotal</span><strong><?= money((float) $order['subtotal']) ?></strong></div><?php if ((float) $order['discount_amount'] > 0): ?><div><span>Discount</span><strong>−<?= money((float) $order['discount_amount']) ?></strong></div><?php endif; ?><div class="receipt-grand-total"><span>Total</span><strong><?= money((float) $order['total_amount']) ?></strong></div></div>
        <div class="receipt-payment"><div><span>Payment</span><strong><?= e((string) $order['payment_method']) ?> · <?= e((string) $order['payment_status']) ?></strong></div><?php if ($order['payment_code'] === 'CASH'): ?><div><span>Cash</span><strong><?= money((float) $order['cash_received']) ?></strong></div><div><span>Change</span><strong><?= money((float) $order['change_due']) ?></strong></div><?php elseif (!empty($order['reference_number'])): ?><div><span>Reference</span><strong><?= e((string) $order['reference_number']) ?></strong></div><?php endif; ?></div>
        <?php if (!empty($order['notes'])): ?><div class="receipt-notes"><strong>Order notes</strong><p><?= nl2br(e((string) $order['notes'])) ?></p></div><?php endif; ?>
        <footer class="receipt-footer"><strong>Thank you!</strong><p>Keep this receipt for your reference.</p><small><?= e(shop_setting('contact_email', 'francis@franscoffee.com')) ?> · <?= e(shop_setting('contact_phone', '09XX XXX XXXX')) ?></small></footer>
    </article>
</main>
<?php require __DIR__ . '/../includes/footer.php'; ?>
