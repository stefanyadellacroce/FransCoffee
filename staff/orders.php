<?php

declare(strict_types=1);
require_once __DIR__ . '/../includes/bootstrap.php';
require_role(['STAFF','ADMIN']);

$status = strtoupper(trim((string)($_GET['status'] ?? 'ALL')));
$source = strtoupper(trim((string)($_GET['source'] ?? 'ALL')));
$payment = strtoupper(trim((string)($_GET['payment'] ?? 'ALL')));
$q = trim((string)($_GET['q'] ?? ''));
$allowedStatuses = ['ALL','PENDING','CONFIRMED','PREPARING','READY','OUT_FOR_DELIVERY','COMPLETED','CANCELLED'];
if (!in_array($status,$allowedStatuses,true)) $status='ALL';
if (!in_array($source,['ALL','ONLINE','POS'],true)) $source='ALL';
if (!in_array($payment,['ALL','UNPAID','PENDING','PAID','FAILED','REFUNDED'],true)) $payment='ALL';

$counts = ops_order_counts();
$orders = ops_orders(['status'=>$status,'source'=>$source,'payment'=>$payment,'q'=>$q]);
$pageTitle='Order Management';
$bodyClass='portal-page';
require __DIR__ . '/../includes/header.php';

function orders_url_with(array $changes): string {
    $query = array_merge([
        'status' => $_GET['status'] ?? 'ALL',
        'source' => $_GET['source'] ?? 'ALL',
        'payment' => $_GET['payment'] ?? 'ALL',
        'q' => $_GET['q'] ?? '',
    ], $changes);
    foreach ($query as $k=>$v) if ($v==='' || $v==='ALL') unset($query[$k]);
    return url('staff/orders.php' . ($query ? '?' . http_build_query($query) : ''));
}
?>
<div class="portal-shell">
<?php require __DIR__ . '/../includes/sidebar.php'; ?>
<main class="portal-main order-ops-main">
<header class="portal-page-header">
    <div><span class="eyebrow">OPERATIONS</span><h1 class="mt-2 mb-1">Order management</h1><p class="text-muted mb-0">Review incoming orders, payments, preparation, pickup, and delivery progress.</p></div>
    <a class="btn btn-coffee" href="<?= url('staff/pos.php') ?>"><i class="bi bi-plus-lg me-2"></i>New POS sale</a>
</header>

<nav class="order-status-tabs" aria-label="Order status filters">
<?php foreach ($allowedStatuses as $tab): ?>
    <a class="<?= $status===$tab?'active':'' ?>" href="<?= e(orders_url_with(['status'=>$tab])) ?>"><span><?= e($tab==='ALL'?'All':ops_status_label($tab)) ?></span><em><?= (int)($counts[$tab]??0) ?></em></a>
<?php endforeach; ?>
</nav>

<section class="portal-card order-filter-card">
<form method="get" class="order-filter-grid">
    <div><label class="form-label" for="orderSearch">Search</label><div class="input-group"><span class="input-group-text"><i class="bi bi-search"></i></span><input class="form-control" id="orderSearch" name="q" value="<?= e($q) ?>" placeholder="Order #, customer, email or phone"></div></div>
    <div><label class="form-label" for="sourceFilter">Source</label><select class="form-select" id="sourceFilter" name="source"><option value="ALL">All sources</option><option value="ONLINE" <?= $source==='ONLINE'?'selected':'' ?>>Online</option><option value="POS" <?= $source==='POS'?'selected':'' ?>>POS</option></select></div>
    <div><label class="form-label" for="paymentFilter">Payment</label><select class="form-select" id="paymentFilter" name="payment"><option value="ALL">All payments</option><?php foreach(['UNPAID','PENDING','PAID','FAILED','REFUNDED'] as $p): ?><option value="<?= $p ?>" <?= $payment===$p?'selected':'' ?>><?= ucfirst(strtolower($p)) ?></option><?php endforeach; ?></select></div>
    <?php if ($status!=='ALL'): ?><input type="hidden" name="status" value="<?= e($status) ?>"><?php endif; ?>
    <div class="order-filter-actions"><button class="btn btn-coffee" type="submit">Apply filters</button><a class="btn btn-outline-secondary" href="<?= url('staff/orders.php') ?>">Reset</a></div>
</form>
</section>

<section class="portal-card mt-4">
<div class="d-flex justify-content-between align-items-center gap-3 mb-3 flex-wrap"><div><span class="eyebrow">QUEUE</span><h2 class="h4 mt-1 mb-0"><?= count($orders) ?> order<?= count($orders)===1?'':'s' ?></h2></div><small class="text-muted">Newest urgent states appear first.</small></div>
<?php if (!$orders): ?>
<div class="empty-state compact"><i class="bi bi-inbox"></i><h3>No matching orders</h3><p>Try another status, payment filter, or search term.</p></div>
<?php else: ?>
<div class="table-responsive"><table class="table align-middle order-ops-table mb-0"><thead><tr><th>Order</th><th>Customer</th><th>Type</th><th>Payment</th><th>Status</th><th>Total</th><th>Placed</th><th class="text-end">Action</th></tr></thead><tbody>
<?php foreach($orders as $order): ?>
<tr>
<td><a class="order-number-link" href="<?= url('staff/order-details.php?order='.rawurlencode((string)$order['order_number'])) ?>"><?= e((string)$order['order_number']) ?></a><small><?= e((string)$order['order_source']) ?></small></td>
<td><strong><?= e((string)($order['contact_name'] ?: $order['account_name'] ?: 'Walk-in Customer')) ?></strong><small><?= e((string)($order['contact_email'] ?: 'No email')) ?></small></td>
<td><span class="order-type-pill"><i class="bi <?= $order['fulfillment_type']==='DELIVERY'?'bi-bicycle':'bi-shop' ?>"></i><?= e(ucfirst(strtolower((string)$order['fulfillment_type']))) ?></span></td>
<td><div class="payment-stack"><strong><?= e((string)($order['payment_method'] ?: '—')) ?></strong><span class="badge <?= e(status_badge_class((string)($order['payment_status'] ?: 'UNPAID'))) ?>"><?= e((string)($order['payment_status'] ?: 'UNPAID')) ?></span></div></td>
<td><span class="badge <?= e(status_badge_class((string)$order['status'])) ?>"><?= e(ops_status_label((string)$order['status'])) ?></span></td>
<td class="fw-semibold"><?= money((float)$order['total_amount']) ?></td>
<td><span><?= e(date('M j, Y',strtotime((string)$order['placed_at']))) ?></span><small><?= e(date('g:i A',strtotime((string)$order['placed_at']))) ?></small></td>
<td class="text-end"><a class="btn btn-sm btn-outline-coffee" href="<?= url('staff/order-details.php?order='.rawurlencode((string)$order['order_number'])) ?>">Manage</a></td>
</tr>
<?php endforeach; ?>
</tbody></table></div>
<?php endif; ?>
</section>
</main></div>
<?php require __DIR__ . '/../includes/footer.php'; ?>
