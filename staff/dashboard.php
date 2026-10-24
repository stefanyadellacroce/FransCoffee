<?php

declare(strict_types=1);
require_once __DIR__ . '/../includes/bootstrap.php';
require_role('STAFF');

$today = db()->query("SELECT COUNT(*) AS order_count, COALESCE(SUM(total_amount),0) AS sales FROM orders WHERE order_source='POS' AND DATE(placed_at)=CURDATE() AND status <> 'CANCELLED'")->fetch();
$recent = db()->query("SELECT o.order_number,o.contact_name,o.total_amount,o.status,o.placed_at,pm.name AS payment_method FROM orders o LEFT JOIN payments p ON p.order_id=o.id LEFT JOIN payment_methods pm ON pm.id=p.payment_method_id WHERE o.order_source='POS' ORDER BY o.placed_at DESC LIMIT 6")->fetchAll();
$pageTitle = 'Staff Dashboard';
$bodyClass = 'portal-page';
require __DIR__ . '/../includes/header.php';
?>
<div class="portal-shell">
    <?php require __DIR__ . '/../includes/sidebar.php'; ?>
    <main class="portal-main">
        <header class="portal-page-header">
            <div><span class="eyebrow">STAFF PORTAL</span><h1 class="mt-2 mb-1">Hello, <?= e(current_user()['first_name'] ?? 'Francis') ?>.</h1><p class="text-muted mb-0">Take walk-in orders and keep the counter moving.</p></div>
            <a class="btn btn-coffee" href="<?= url('staff/pos.php') ?>"><i class="bi bi-shop me-2"></i>Open POS</a>
        </header>
        <div class="customer-metric-grid mb-4">
            <div class="customer-metric"><span class="customer-metric-icon"><i class="bi bi-receipt"></i></span><div><small>POS orders today</small><strong><?= (int) ($today['order_count'] ?? 0) ?></strong></div></div>
            <div class="customer-metric"><span class="customer-metric-icon"><i class="bi bi-cash-stack"></i></span><div><small>POS sales today</small><strong><?= money((float) ($today['sales'] ?? 0)) ?></strong></div></div>
            <div class="customer-metric"><span class="customer-metric-icon"><i class="bi bi-person-badge"></i></span><div><small>Signed in as</small><strong><?= e(current_user()['email'] ?? '') ?></strong></div></div>
        </div>
        <section class="portal-card">
            <div class="d-flex justify-content-between align-items-center mb-3"><div><span class="eyebrow">RECENT POS SALES</span><h2 class="h4 mt-1 mb-0">Latest counter orders</h2></div><a class="btn btn-sm btn-outline-secondary" href="<?= url('staff/pos.php') ?>">New sale</a></div>
            <?php if (!$recent): ?><div class="customer-empty-inline"><i class="bi bi-receipt"></i><div><strong>No POS sales yet</strong><span>Your completed counter sales will appear here.</span></div><a class="btn btn-coffee" href="<?= url('staff/pos.php') ?>">Open POS</a></div><?php else: ?>
                <div class="table-responsive"><table class="table align-middle mb-0"><thead><tr><th>Order</th><th>Customer</th><th>Payment</th><th>Status</th><th class="text-end">Total</th><th>Time</th></tr></thead><tbody><?php foreach ($recent as $row): ?><tr><td><a class="fw-semibold text-decoration-none" href="<?= url('staff/receipt.php?order=' . rawurlencode((string)$row['order_number'])) ?>"><?= e((string)$row['order_number']) ?></a></td><td><?= e((string)($row['contact_name'] ?: 'Walk-in Customer')) ?></td><td><?= e((string)($row['payment_method'] ?: '—')) ?></td><td><span class="status-badge status-<?= strtolower((string)$row['status']) ?>"><?= e((string)$row['status']) ?></span></td><td class="text-end fw-semibold"><?= money((float)$row['total_amount']) ?></td><td><?= e(date('g:i A', strtotime((string)$row['placed_at']))) ?></td></tr><?php endforeach; ?></tbody></table></div>
            <?php endif; ?>
        </section>
    </main>
</div>
<?php require __DIR__ . '/../includes/footer.php'; ?>
