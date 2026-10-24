<?php

declare(strict_types=1);
require_once __DIR__ . '/../includes/bootstrap.php';
require_role('ADMIN');
$id = (int)($_GET['id'] ?? $_POST['supplier_id'] ?? 0);
$supplier = $id > 0 ? admin_supplier_by_id($id) : null;
if ($id > 0 && !$supplier) { flash('error', 'Supplier not found.'); redirect('admin/suppliers.php'); }
$errors = [];
if (is_post()) {
    verify_csrf_or_abort();
    $result = admin_save_supplier($_POST, $id);
    if ($result['ok']) { flash('success', $result['message']); redirect('admin/supplier-form.php?id=' . (int)$result['id']); }
    $errors = $result['errors'];
    $supplier = array_merge($supplier ?? [], $_POST);
}
$form = $supplier ?? ['name' => '', 'contact_person' => '', 'email' => '', 'phone' => '', 'address' => '', 'products_supplied' => '', 'status' => 'ACTIVE'];
$pageTitle = $id > 0 ? 'Edit Supplier' : 'Add Supplier'; $bodyClass = 'portal-page'; require __DIR__ . '/../includes/header.php'; $success = flash('success');
?>
<div class="portal-shell"><?php require __DIR__ . '/../includes/sidebar.php'; ?><main class="portal-main inventory-main"><header class="portal-page-header"><div><span class="eyebrow">SUPPLIERS</span><h1 class="mt-2 mb-1"><?= $id > 0 ? 'Edit supplier' : 'Add supplier' ?></h1><p class="text-muted mb-0">Keep vendor contact details and supplied items organized for purchasing and stock work.</p></div><a class="btn btn-outline-coffee" href="<?= url('admin/suppliers.php') ?>"><i class="bi bi-arrow-left me-2"></i>Suppliers</a></header>
<?php if ($success): ?><div class="alert alert-success"><?= e($success) ?></div><?php endif; ?><?php if ($errors): ?><div class="alert alert-danger"><ul class="mb-0"><?php foreach ($errors as $error): ?><li><?= e($error) ?></li><?php endforeach; ?></ul></div><?php endif; ?>
<div class="row justify-content-center"><div class="col-xl-9"><section class="portal-card"><form method="post" class="row g-3"><?= csrf_field() ?><input type="hidden" name="supplier_id" value="<?= $id ?>"><div class="col-md-8"><label class="form-label" for="supplierName">Supplier name</label><input class="form-control" id="supplierName" name="name" required maxlength="150" value="<?= e((string)$form['name']) ?>"></div><div class="col-md-4"><label class="form-label" for="supplierFormStatus">Status</label><select class="form-select" id="supplierFormStatus" name="status"><option value="ACTIVE" <?= $form['status'] === 'ACTIVE' ? 'selected' : '' ?>>Active</option><option value="INACTIVE" <?= $form['status'] === 'INACTIVE' ? 'selected' : '' ?>>Inactive</option></select></div><div class="col-md-6"><label class="form-label" for="contactPerson">Contact person</label><input class="form-control" id="contactPerson" name="contact_person" maxlength="120" value="<?= e((string)($form['contact_person'] ?? '')) ?>"></div><div class="col-md-6"><label class="form-label" for="supplierPhone">Phone</label><input class="form-control" id="supplierPhone" name="phone" maxlength="40" value="<?= e((string)($form['phone'] ?? '')) ?>"></div><div class="col-md-6"><label class="form-label" for="supplierEmail">Email</label><input class="form-control" id="supplierEmail" name="email" type="email" maxlength="190" value="<?= e((string)($form['email'] ?? '')) ?>"></div><div class="col-md-6"><label class="form-label" for="productsSupplied">Products supplied</label><input class="form-control" id="productsSupplied" name="products_supplied" maxlength="2000" value="<?= e((string)($form['products_supplied'] ?? '')) ?>" placeholder="Coffee beans, milk, syrups"></div><div class="col-12"><label class="form-label" for="supplierAddress">Address</label><textarea class="form-control" id="supplierAddress" name="address" rows="4"><?= e((string)($form['address'] ?? '')) ?></textarea></div><div class="col-12 d-flex justify-content-end gap-2"><a class="btn btn-outline-secondary" href="<?= url('admin/suppliers.php') ?>">Cancel</a><button class="btn btn-coffee" type="submit"><i class="bi bi-check2 me-2"></i>Save supplier</button></div></form></section></div></div>
</main></div><?php require __DIR__ . '/../includes/footer.php'; ?>
