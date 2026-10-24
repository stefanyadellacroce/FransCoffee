<?php

declare(strict_types=1);
require_once __DIR__ . '/../includes/bootstrap.php';
require_role('ADMIN');

if (is_post()) {
    verify_csrf_or_abort();
    $id = (int)($_POST['item_id'] ?? 0);
    $action = (string)($_POST['action'] ?? '');
    $result = $action === 'toggle_item'
        ? admin_toggle_inventory_item($id)
        : ['ok' => false, 'message' => 'Choose a valid inventory action.'];
    flash($result['ok'] ? 'success' : 'error', $result['message']);
    redirect('admin/inventory.php');
}

$q = trim((string)($_GET['q'] ?? ''));
$category = trim((string)($_GET['category'] ?? ''));
$supplierId = (int)($_GET['supplier_id'] ?? 0);
$status = strtoupper(trim((string)($_GET['status'] ?? 'ALL')));
$stock = strtoupper(trim((string)($_GET['stock'] ?? 'ALL')));
if (!in_array($status, ['ALL', 'ACTIVE', 'INACTIVE'], true)) $status = 'ALL';
if (!in_array($stock, ['ALL', 'LOW', 'OK'], true)) $stock = 'ALL';

$items = admin_inventory_items([
    'q' => $q,
    'category' => $category,
    'supplier_id' => $supplierId,
    'status' => $status,
    'stock' => $stock,
]);
$metrics = admin_inventory_metrics();
$categories = inventory_categories();
$suppliers = admin_suppliers(['status' => 'ACTIVE']);
$pageTitle = 'Inventory';
$bodyClass = 'portal-page';
require __DIR__ . '/../includes/header.php';
$success = flash('success'); $error = flash('error');
?>
<div class="portal-shell"><?php require __DIR__ . '/../includes/sidebar.php'; ?><main class="portal-main inventory-main">
<header class="portal-page-header"><div><span class="eyebrow">INVENTORY</span><h1 class="mt-2 mb-1">Stock control</h1><p class="text-muted mb-0">Track ingredients and supplies through auditable stock movements and reorder levels.</p></div><div class="d-flex gap-2 flex-wrap"><a class="btn btn-outline-coffee" href="<?= url('admin/inventory-transactions.php') ?>"><i class="bi bi-clock-history me-2"></i>Transactions</a><a class="btn btn-coffee" href="<?= url('admin/inventory-form.php') ?>"><i class="bi bi-plus-lg me-2"></i>Add item</a></div></header>
<?php if ($success): ?><div class="alert alert-success"><i class="bi bi-check-circle me-2"></i><?= e($success) ?></div><?php endif; ?>
<?php if ($error): ?><div class="alert alert-danger"><i class="bi bi-exclamation-circle me-2"></i><?= e($error) ?></div><?php endif; ?>

<section class="inventory-metrics">
<div><span>Active items</span><strong><?= $metrics['active'] ?></strong><small><?= $metrics['total'] ?> total records</small></div>
<div class="<?= $metrics['low'] > 0 ? 'attention' : '' ?>"><span>Low stock</span><strong><?= $metrics['low'] ?></strong><small>At or below reorder level</small></div>
<div><span>Stock value</span><strong><?= money($metrics['value']) ?></strong><small>Approx. current inventory value</small></div>
<div><span>Archived</span><strong><?= $metrics['inactive'] ?></strong><small>History preserved</small></div>
</section>

<section class="portal-card inventory-filter-card">
<form method="get" class="inventory-filter-grid">
<div><label class="form-label" for="inventorySearch">Search</label><div class="input-group"><span class="input-group-text"><i class="bi bi-search"></i></span><input class="form-control" id="inventorySearch" name="q" value="<?= e($q) ?>" placeholder="Code, item, or category"></div></div>
<div><label class="form-label" for="inventoryCategory">Category</label><select class="form-select" id="inventoryCategory" name="category"><option value="">All categories</option><?php foreach ($categories as $option): ?><option value="<?= e((string)$option) ?>" <?= $category === (string)$option ? 'selected' : '' ?>><?= e((string)$option) ?></option><?php endforeach; ?></select></div>
<div><label class="form-label" for="inventorySupplier">Supplier</label><select class="form-select" id="inventorySupplier" name="supplier_id"><option value="0">All suppliers</option><?php foreach ($suppliers as $supplier): ?><option value="<?= (int)$supplier['id'] ?>" <?= $supplierId === (int)$supplier['id'] ? 'selected' : '' ?>><?= e((string)$supplier['name']) ?></option><?php endforeach; ?></select></div>
<div><label class="form-label" for="inventoryStock">Stock</label><select class="form-select" id="inventoryStock" name="stock"><option value="ALL">All</option><option value="LOW" <?= $stock === 'LOW' ? 'selected' : '' ?>>Low stock</option><option value="OK" <?= $stock === 'OK' ? 'selected' : '' ?>>Healthy</option></select></div>
<div><label class="form-label" for="inventoryStatus">Status</label><select class="form-select" id="inventoryStatus" name="status"><option value="ALL">All</option><option value="ACTIVE" <?= $status === 'ACTIVE' ? 'selected' : '' ?>>Active</option><option value="INACTIVE" <?= $status === 'INACTIVE' ? 'selected' : '' ?>>Archived</option></select></div>
<div class="inventory-filter-actions"><button class="btn btn-coffee" type="submit">Apply</button><a class="btn btn-outline-secondary" href="<?= url('admin/inventory.php') ?>">Reset</a></div>
</form>
</section>

<section class="portal-card mt-4">
<div class="panel-heading"><div><span class="eyebrow">STOCK ITEMS</span><h2><?= count($items) ?> result<?= count($items) === 1 ? '' : 's' ?></h2></div><a class="btn btn-sm btn-outline-coffee" href="<?= url('admin/suppliers.php') ?>"><i class="bi bi-truck me-1"></i>Suppliers</a></div>
<?php if (!$items): ?><div class="empty-state compact"><i class="bi bi-box-seam"></i><h3>No inventory items found</h3><p>Try another filter or create the first stock item.</p><a class="btn btn-coffee" href="<?= url('admin/inventory-form.php') ?>">Add inventory item</a></div><?php else: ?>
<div class="table-responsive"><table class="table align-middle inventory-table mb-0"><thead><tr><th>Item</th><th>Category</th><th>On hand</th><th>Reorder</th><th>Unit cost</th><th>Supplier</th><th>Status</th><th class="text-end">Actions</th></tr></thead><tbody>
<?php foreach ($items as $item):
    $item = array_merge([
        'id' => 0,
        'item_name' => 'Unnamed item',
        'item_code' => '—',
        'category' => null,
        'quantity' => 0,
        'reorder_level' => 0,
        'unit' => '',
        'unit_cost' => 0,
        'supplier_name' => null,
        'status' => 'INACTIVE',
        'is_low_stock' => 0,
    ], is_array($item) ? $item : []);
    $low = (int)$item['is_low_stock'] === 1;
?>
<tr class="<?= $item['status'] !== 'ACTIVE' ? 'inventory-row-muted' : '' ?>">
<td><div class="inventory-item-cell"><span class="inventory-item-icon"><i class="bi bi-box"></i></span><div><strong><?= e((string)$item['item_name']) ?></strong><small><?= e((string)$item['item_code']) ?></small></div></div></td>
<td><?= e((string)($item['category'] ?: '—')) ?></td>
<td><div class="inventory-qty <?= $low ? 'low' : '' ?>"><strong><?= e(inventory_decimal((float)$item['quantity'])) ?></strong><span><?= e((string)$item['unit']) ?></span><?php if ($low): ?><em>LOW</em><?php endif; ?></div></td>
<td><?= e(inventory_decimal((float)$item['reorder_level'])) ?> <?= e((string)$item['unit']) ?></td>
<td><?= money((float)$item['unit_cost']) ?></td>
<td><?= e((string)($item['supplier_name'] ?: '—')) ?></td>
<td><span class="badge <?= $item['status'] === 'ACTIVE' ? 'text-bg-success' : 'text-bg-secondary' ?>"><?= e((string)$item['status']) ?></span></td>
<td class="text-end"><div class="catalog-action-group"><a class="btn btn-sm btn-outline-coffee" href="<?= url('admin/inventory-form.php?id=' . (int)$item['id']) ?>">Manage</a><a class="btn btn-sm btn-outline-secondary" title="Transaction history" href="<?= url('admin/inventory-transactions.php?item_id=' . (int)$item['id']) ?>"><i class="bi bi-clock-history"></i></a><form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="toggle_item"><input type="hidden" name="item_id" value="<?= (int)$item['id'] ?>"><button class="btn btn-sm <?= $item['status'] === 'ACTIVE' ? 'btn-outline-danger' : 'btn-outline-success' ?>" type="submit" data-confirm="<?= $item['status'] === 'ACTIVE' ? 'Archive this inventory item? Its stock history will be preserved.' : 'Activate this inventory item?' ?>"><i class="bi <?= $item['status'] === 'ACTIVE' ? 'bi-archive' : 'bi-arrow-counterclockwise' ?>"></i></button></form></div></td>
</tr>
<?php endforeach; ?></tbody></table></div><?php endif; ?>
</section>
</main></div><?php require __DIR__ . '/../includes/footer.php'; ?>
