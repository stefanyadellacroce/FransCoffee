<?php

declare(strict_types=1);
require_once __DIR__ . '/../includes/bootstrap.php';
require_role(['STAFF', 'ADMIN']);

$q = trim((string) ($_GET['q'] ?? ''));
$status = strtoupper(trim((string) ($_GET['status'] ?? 'ALL')));
if (!in_array($status, ['ALL', 'ACTIVE', 'INACTIVE'], true)) $status = 'ALL';

$counts = ops_customer_counts();
$customers = ops_customers(['q' => $q, 'status' => $status]);
$pageTitle = 'Customers';
$bodyClass = 'portal-page';
require __DIR__ . '/../includes/header.php';
?>
<div class="portal-shell">
<?php require __DIR__ . '/../includes/sidebar.php'; ?>
<main class="portal-main">
<header class="portal-page-header">
    <div><span class="eyebrow">CUSTOMER DIRECTORY</span><h1 class="mt-2 mb-1">Customers</h1><p class="text-muted mb-0">Find registered customers and review their order activity before serving them at the counter.</p></div>
    <a class="btn btn-coffee" href="<?= url('staff/pos.php') ?>"><i class="bi bi-shop me-2"></i>Open POS</a>
</header>

<div class="customer-metric-grid mb-4">
    <div class="customer-metric"><span class="customer-metric-icon"><i class="bi bi-people"></i></span><div><small>Registered</small><strong><?= (int)$counts['total'] ?></strong></div></div>
    <div class="customer-metric"><span class="customer-metric-icon"><i class="bi bi-person-check"></i></span><div><small>Active</small><strong><?= (int)$counts['active'] ?></strong></div></div>
    <div class="customer-metric"><span class="customer-metric-icon"><i class="bi bi-person-dash"></i></span><div><small>Inactive</small><strong><?= (int)$counts['inactive'] ?></strong></div></div>
</div>

<section class="portal-card order-filter-card">
<form method="get" class="order-filter-grid">
    <div><label class="form-label" for="customerSearch">Search</label><div class="input-group"><span class="input-group-text"><i class="bi bi-search"></i></span><input class="form-control" id="customerSearch" name="q" value="<?= e($q) ?>" placeholder="Name, email or phone"></div></div>
    <div><label class="form-label" for="customerStatus">Status</label><select class="form-select" id="customerStatus" name="status"><option value="ALL">All customers</option><option value="ACTIVE" <?= $status==='ACTIVE'?'selected':'' ?>>Active</option><option value="INACTIVE" <?= $status==='INACTIVE'?'selected':'' ?>>Inactive</option></select></div>
    <div class="order-filter-actions"><button class="btn btn-coffee" type="submit">Apply filters</button><a class="btn btn-outline-secondary" href="<?= url('staff/customers.php') ?>">Reset</a></div>
</form>
</section>

<section class="portal-card mt-4">
<div class="d-flex justify-content-between align-items-center gap-3 mb-3 flex-wrap"><div><span class="eyebrow">REGISTERED CUSTOMERS</span><h2 class="h4 mt-1 mb-0"><?= count($customers) ?> result<?= count($customers)===1?'':'s' ?></h2></div><small class="text-muted">Read-only customer access for Staff.</small></div>
<?php if (!$customers): ?>
<div class="empty-state compact"><i class="bi bi-people"></i><h3>No matching customers</h3><p>Try another name, email, phone number, or account status.</p></div>
<?php else: ?>
<div class="table-responsive"><table class="table align-middle order-ops-table mb-0"><thead><tr><th>Customer</th><th>Phone</th><th>Status</th><th>Orders</th><th>Paid total</th><th>Last order</th><th class="text-end">Action</th></tr></thead><tbody>
<?php foreach ($customers as $customer): ?>
<tr>
<td><strong><?= e(trim((string)$customer['first_name'].' '.(string)$customer['last_name']) ?: 'Customer') ?></strong><small><?= e((string)$customer['email']) ?></small></td>
<td><?= e((string)($customer['phone'] ?: '—')) ?></td>
<td><span class="badge <?= e(status_badge_class((string)$customer['status'])) ?>"><?= e((string)$customer['status']) ?></span></td>
<td class="fw-semibold"><?= (int)$customer['order_count'] ?></td>
<td class="fw-semibold"><?= money((float)$customer['paid_total']) ?></td>
<td><?php if (!empty($customer['last_order_at'])): ?><span><?= e(date('M j, Y', strtotime((string)$customer['last_order_at']))) ?></span><small><?= e(date('g:i A', strtotime((string)$customer['last_order_at']))) ?></small><?php else: ?><span class="text-muted">No orders yet</span><?php endif; ?></td>
<td class="text-end"><a class="btn btn-sm btn-outline-coffee" href="<?= url('staff/orders.php?q='.rawurlencode((string)$customer['email'])) ?>">View orders</a></td>
</tr>
<?php endforeach; ?>
</tbody></table></div>
<?php endif; ?>
</section>
</main></div>
<?php require __DIR__ . '/../includes/footer.php'; ?>
