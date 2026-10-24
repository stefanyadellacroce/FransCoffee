<?php

declare(strict_types=1);
require_once __DIR__ . '/../includes/bootstrap.php';
require_role('ADMIN');

$id = (int)($_GET['id'] ?? $_POST['item_id'] ?? 0);
$item = $id > 0 ? admin_inventory_item_by_id($id) : null;
if ($id > 0 && !$item) { flash('error', 'Inventory item not found.'); redirect('admin/inventory.php'); }

$errors = [];
$preservePostedItem = false;
if (is_post()) {
    verify_csrf_or_abort();
    $action = (string)($_POST['action'] ?? 'save_item');
    if ($action === 'save_item') {
        $result = admin_save_inventory_item($_POST, $id);
        if ($result['ok']) { flash('success', $result['message']); redirect('admin/inventory-form.php?id=' . (int)$result['id']); }
        $errors = $result['errors'];
        $item = array_merge($item ?? [], $_POST);
        $preservePostedItem = true;
    } elseif ($action === 'record_transaction' && $id > 0) {
        $result = admin_record_inventory_transaction($id, $_POST);
        if ($result['ok']) { flash('success', $result['message']); redirect('admin/inventory-form.php?id=' . $id); }
        $errors = $result['errors'];
    }
}

if ($id > 0 && !$preservePostedItem) $item = admin_inventory_item_by_id($id);
$form = $item ?? ['item_code' => inventory_generate_item_code(), 'item_name' => '', 'category' => '', 'unit' => '', 'opening_quantity' => '0', 'reorder_level' => '0', 'unit_cost' => '0', 'supplier_id' => 0, 'status' => 'ACTIVE'];
$suppliers = admin_suppliers();
$recent = $id > 0 ? admin_inventory_transactions(['item_id' => $id], 8) : [];
$pageTitle = $id > 0 ? 'Manage Inventory Item' : 'Add Inventory Item';
$bodyClass = 'portal-page';
require __DIR__ . '/../includes/header.php';
$success = flash('success');
?>
<div class="portal-shell"><?php require __DIR__ . '/../includes/sidebar.php'; ?><main class="portal-main inventory-main">
<header class="portal-page-header"><div><span class="eyebrow">INVENTORY</span><h1 class="mt-2 mb-1"><?= $id > 0 ? 'Manage stock item' : 'Add stock item' ?></h1><p class="text-muted mb-0"><?= $id > 0 ? 'Edit item details and record auditable stock movement.' : 'Create an ingredient or supply and record its opening balance.' ?></p></div><a class="btn btn-outline-coffee" href="<?= url('admin/inventory.php') ?>"><i class="bi bi-arrow-left me-2"></i>Inventory</a></header>
<?php if ($success): ?><div class="alert alert-success"><?= e($success) ?></div><?php endif; ?>
<?php if ($errors): ?><div class="alert alert-danger"><strong>Please fix the following:</strong><ul class="mb-0 mt-2"><?php foreach ($errors as $error): ?><li><?= e($error) ?></li><?php endforeach; ?></ul></div><?php endif; ?>

<div class="row g-4 align-items-start"><div class="col-xl-7">
<section class="portal-card"><div class="panel-heading"><div><span class="eyebrow">ITEM DETAILS</span><h2><?= $id > 0 ? e((string)$form['item_name']) : 'New inventory item' ?></h2></div><?php if ($id > 0): ?><span class="badge <?= $form['status'] === 'ACTIVE' ? 'text-bg-success' : 'text-bg-secondary' ?>"><?= e((string)$form['status']) ?></span><?php endif; ?></div>
<form method="post" class="row g-3"><?= csrf_field() ?><input type="hidden" name="action" value="save_item"><input type="hidden" name="item_id" value="<?= $id ?>">
<div class="col-md-5"><label class="form-label" for="itemCode">Item code</label><input class="form-control" id="itemCode" name="item_code" maxlength="40" required value="<?= e((string)($form['item_code'] ?? '')) ?>"></div>
<div class="col-md-7"><label class="form-label" for="itemName">Item name</label><input class="form-control" id="itemName" name="item_name" maxlength="140" required value="<?= e((string)($form['item_name'] ?? '')) ?>" placeholder="Coffee Beans"></div>
<div class="col-md-6"><label class="form-label" for="itemCategory">Category</label><input class="form-control" id="itemCategory" name="category" maxlength="100" value="<?= e((string)($form['category'] ?? '')) ?>" placeholder="Coffee / Dairy / Packaging"></div>
<div class="col-md-6"><label class="form-label" for="itemUnit">Unit</label><input class="form-control" id="itemUnit" name="unit" maxlength="40" required value="<?= e((string)($form['unit'] ?? '')) ?>" placeholder="kg, L, pcs, bottle"></div>
<?php if ($id === 0): ?><div class="col-md-4"><label class="form-label" for="openingQty">Opening quantity</label><input class="form-control" id="openingQty" type="number" min="0" step="0.001" name="opening_quantity" value="<?= e((string)($form['opening_quantity'] ?? '0')) ?>"><div class="form-text">Recorded as an opening STOCK_IN transaction.</div></div><?php endif; ?>
<div class="col-md-<?= $id === 0 ? '4' : '6' ?>"><label class="form-label" for="reorderLevel">Reorder level</label><input class="form-control" id="reorderLevel" type="number" min="0" step="0.001" name="reorder_level" required value="<?= e((string)($form['reorder_level'] ?? '0')) ?>"></div>
<div class="col-md-<?= $id === 0 ? '4' : '6' ?>"><label class="form-label" for="unitCost">Unit cost</label><div class="input-group"><span class="input-group-text">₱</span><input class="form-control" id="unitCost" type="number" min="0" step="0.01" name="unit_cost" required value="<?= e((string)($form['unit_cost'] ?? '0')) ?>"></div></div>
<div class="col-md-8"><label class="form-label" for="supplierId">Primary supplier</label><select class="form-select" id="supplierId" name="supplier_id"><option value="0">No supplier assigned</option><?php foreach ($suppliers as $supplier): ?><option value="<?= (int)$supplier['id'] ?>" <?= (int)($form['supplier_id'] ?? 0) === (int)$supplier['id'] ? 'selected' : '' ?>><?= e((string)$supplier['name']) ?><?= $supplier['status'] !== 'ACTIVE' ? ' (inactive)' : '' ?></option><?php endforeach; ?></select></div>
<div class="col-md-4"><label class="form-label" for="itemStatus">Status</label><select class="form-select" id="itemStatus" name="status"><option value="ACTIVE" <?= ($form['status'] ?? 'ACTIVE') === 'ACTIVE' ? 'selected' : '' ?>>Active</option><option value="INACTIVE" <?= ($form['status'] ?? '') === 'INACTIVE' ? 'selected' : '' ?>>Archived</option></select></div>
<div class="col-12 d-flex justify-content-end gap-2"><a class="btn btn-outline-secondary" href="<?= url('admin/inventory.php') ?>">Cancel</a><button class="btn btn-coffee" type="submit"><i class="bi bi-check2 me-2"></i><?= $id > 0 ? 'Save changes' : 'Create item' ?></button></div>
</form></section>

<?php if ($id > 0): ?><section class="portal-card mt-4"><div class="panel-heading"><div><span class="eyebrow">RECENT ACTIVITY</span><h2>Stock history</h2></div><a class="btn btn-sm btn-outline-coffee" href="<?= url('admin/inventory-transactions.php?item_id=' . $id) ?>">View all</a></div>
<?php if (!$recent): ?><p class="text-muted mb-0">No stock movements recorded yet.</p><?php else: ?><div class="inventory-mini-history"><?php foreach ($recent as $tx): ?><div><span class="inventory-tx-icon <?= strtolower((string)$tx['type']) ?>"><i class="bi <?= $tx['type'] === 'STOCK_IN' ? 'bi-arrow-down-circle' : ($tx['type'] === 'ADJUSTMENT' ? 'bi-sliders' : 'bi-arrow-up-circle') ?>"></i></span><div><strong><?= e(str_replace('_', ' ', (string)$tx['type'])) ?></strong><small><?= e(date('M j, Y · g:i A', strtotime((string)$tx['created_at']))) ?><?= !empty($tx['notes']) ? ' · ' . e((string)$tx['notes']) : '' ?></small></div><div class="text-end"><strong><?= e(inventory_decimal((float)$tx['quantity'])) ?> <?= e((string)$tx['unit']) ?></strong><small>Balance <?= e(inventory_decimal((float)$tx['balance_after'])) ?></small></div></div><?php endforeach; ?></div><?php endif; ?></section><?php endif; ?>
</div>

<div class="col-xl-5">
<?php if ($id > 0): ?><section class="portal-card inventory-stock-card"><span class="eyebrow">CURRENT STOCK</span><div class="inventory-balance"><strong><?= e(inventory_decimal((float)$item['quantity'])) ?></strong><span><?= e((string)$item['unit']) ?></span></div><p>Reorder level: <strong><?= e(inventory_decimal((float)$item['reorder_level'])) ?> <?= e((string)$item['unit']) ?></strong></p><?php if ((float)$item['quantity'] <= (float)$item['reorder_level']): ?><div class="inventory-low-callout"><i class="bi bi-exclamation-triangle"></i><div><strong>Low stock</strong><span>This item is at or below its reorder level.</span></div></div><?php endif; ?></section>
<section class="portal-card mt-4"><span class="eyebrow">RECORD MOVEMENT</span><h2 class="inventory-form-heading">Stock transaction</h2><p class="text-muted small">Use transactions instead of editing the on-hand quantity directly so every adjustment stays traceable.</p>
<form method="post" id="inventoryTransactionForm" class="row g-3"><?= csrf_field() ?><input type="hidden" name="action" value="record_transaction"><input type="hidden" name="item_id" value="<?= $id ?>">
<div class="col-12"><label class="form-label" for="txType">Transaction type</label><select class="form-select" id="txType" name="type" required><option value="STOCK_IN">Stock In</option><option value="STOCK_OUT">Stock Out / Usage</option><option value="WASTE">Waste</option><option value="ADJUSTMENT">Physical Count Adjustment</option></select></div>
<div class="col-md-6" data-tx-quantity><label class="form-label" for="txQty">Quantity</label><input class="form-control" id="txQty" name="quantity" type="number" min="0.001" step="0.001" value="1"></div>
<div class="col-md-6 d-none" data-tx-physical><label class="form-label" for="physicalCount">New physical count</label><input class="form-control" id="physicalCount" name="physical_count" type="number" min="0" step="0.001" value="<?= e(inventory_decimal((float)$item['quantity'])) ?>"></div>
<div class="col-md-6" data-tx-cost><label class="form-label" for="txCost">Unit cost <span class="text-muted">(optional)</span></label><div class="input-group"><span class="input-group-text">₱</span><input class="form-control" id="txCost" name="unit_cost" type="number" min="0" step="0.01" placeholder="<?= e(number_format((float)$item['unit_cost'], 2, '.', '')) ?>"></div></div>
<div class="col-12"><label class="form-label" for="txNotes">Notes</label><textarea class="form-control" id="txNotes" name="notes" rows="3" maxlength="255" placeholder="Delivery received, milk used, damaged cups, stock count correction..."></textarea></div>
<div class="col-12"><button class="btn btn-coffee w-100" type="submit"><i class="bi bi-plus-slash-minus me-2"></i>Record transaction</button></div>
</form></section><?php else: ?><section class="portal-card inventory-guide-card"><span class="inventory-side-icon"><i class="bi bi-journal-check"></i></span><span class="eyebrow">AUDITABLE STOCK</span><h2>Start with an opening balance</h2><p>Create the item with its current quantity. Afterwards, use stock transactions for every movement so Franscoffee keeps a clear history.</p><ul><li>Stock In adds received supplies.</li><li>Stock Out records normal usage.</li><li>Waste records damaged or spoiled stock.</li><li>Adjustment reconciles the system to a physical count.</li></ul></section><?php endif; ?>
</div></div>
</main></div><?php require __DIR__ . '/../includes/footer.php'; ?>
