<?php

declare(strict_types=1);
require_once __DIR__ . '/../includes/bootstrap.php';
require_role('CUSTOMER');
$userId=(int)current_user()['id'];
$errors=[];
$editId=max(0,(int)($_GET['edit']??0));
$editAddress=null;

if(is_post()){
    verify_csrf_or_abort();
    $action=(string)($_POST['action']??'save');
    if($action==='save'){
        $id=max(0,(int)($_POST['address_id']??0));
        $result=customer_address_save($userId,$_POST,$id?:null);
        if($result['ok']){flash('success',$id?'Address updated.':'Address saved.');redirect('customer/addresses.php');}
        $errors=$result['errors']??[];
        $editId=$id;
    } elseif($action==='delete'){
        $id=(int)($_POST['address_id']??0);
        $ok=customer_address_delete($userId,$id);
        flash($ok?'success':'error',$ok?'Address deleted.':'Address not found.');
        redirect('customer/addresses.php');
    } elseif($action==='default'){
        $id=(int)($_POST['address_id']??0);
        $ok=customer_address_set_default($userId,$id);
        flash($ok?'success':'error',$ok?'Default address updated.':'Address not found.');
        redirect('customer/addresses.php');
    }
}

$addresses=customer_addresses($userId);
if($editId>0){foreach($addresses as $address){if((int)$address['id']===$editId){$editAddress=$address;break;}}}
$success=flash('success');$error=flash('error');$pageTitle='Saved Addresses';
require __DIR__.'/../includes/header.php';
?>
<div class="portal-shell"><?php require __DIR__.'/../includes/sidebar.php';?><main class="portal-main customer-portal-main">
<?php if($success):?><div class="alert alert-success"><?= e($success) ?></div><?php endif;?><?php if($error):?><div class="alert alert-danger"><?= e($error) ?></div><?php endif;?><?php if($errors):?><div class="alert alert-danger"><ul class="mb-0"><?php foreach($errors as $msg):?><li><?= e($msg) ?></li><?php endforeach;?></ul></div><?php endif;?>
<div class="portal-page-header"><div><span class="eyebrow">DELIVERY</span><h1 class="mt-2">Saved Addresses</h1><p>Save delivery locations so checkout is quicker next time.</p></div></div>
<div class="row g-4"><div class="col-xl-7"><section class="customer-panel"><div class="panel-heading"><div><span class="eyebrow">YOUR ADDRESSES</span><h2><?= count($addresses) ?> saved</h2></div></div><?php if(!$addresses):?><div class="customer-empty-small"><i class="bi bi-geo-alt"></i><p class="mb-0">No saved addresses yet. Add one using the form.</p></div><?php else:?><div class="saved-address-cards"><?php foreach($addresses as $address):?><article class="saved-address-card"><div class="d-flex gap-3"><div class="address-icon"><i class="bi bi-geo-alt"></i></div><div><div class="d-flex flex-wrap gap-2 align-items-center"><strong><?= e($address['label']) ?></strong><?php if((int)$address['is_default']===1):?><span class="badge text-bg-success">Default</span><?php endif;?></div><p><?= e($address['street'].', '.$address['barangay'].', '.$address['city'].', '.$address['province']) ?><?= $address['postal_code']?' '.e($address['postal_code']):'' ?></p><?php if($address['landmark']):?><small>Landmark: <?= e($address['landmark']) ?></small><?php endif;?></div></div><div class="saved-address-actions"><a class="btn btn-sm btn-outline-coffee" href="<?= url('customer/addresses.php?edit='.(int)$address['id']) ?>">Edit</a><?php if((int)$address['is_default']!==1):?><form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="default"><input type="hidden" name="address_id" value="<?= (int)$address['id'] ?>"><button class="btn btn-sm btn-link" type="submit">Make default</button></form><?php endif;?><form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="delete"><input type="hidden" name="address_id" value="<?= (int)$address['id'] ?>"><button class="btn btn-sm btn-link text-danger" type="submit" data-confirm="Delete this saved address?">Delete</button></form></div></article><?php endforeach;?></div><?php endif;?></section></div>
<div class="col-xl-5"><section class="customer-panel sticky-panel"><span class="eyebrow"><?= $editAddress?'EDIT ADDRESS':'NEW ADDRESS' ?></span><h2><?= $editAddress?'Update saved address':'Add a delivery address' ?></h2><form method="post" class="mt-3" data-disable-on-submit><?= csrf_field() ?><input type="hidden" name="action" value="save"><input type="hidden" name="address_id" value="<?= (int)($editAddress['id']??0) ?>"><div class="mb-3"><label class="form-label" for="address_label">Label</label><input class="form-control" id="address_label" name="address_label" value="<?= e((string)($editAddress['label']??'Home')) ?>" required maxlength="60"></div><div class="mb-3"><label class="form-label" for="street">Street / House / Building</label><input class="form-control" id="street" name="street" value="<?= e((string)($editAddress['street']??'')) ?>" required maxlength="190"></div><div class="row g-3"><div class="col-md-6"><label class="form-label" for="barangay">Barangay</label><input class="form-control" id="barangay" name="barangay" value="<?= e((string)($editAddress['barangay']??'')) ?>" required maxlength="120"></div><div class="col-md-6"><label class="form-label" for="city">City</label><input class="form-control" id="city" name="city" value="<?= e((string)($editAddress['city']??'')) ?>" required maxlength="120"></div><div class="col-md-8"><label class="form-label" for="province">Province</label><input class="form-control" id="province" name="province" value="<?= e((string)($editAddress['province']??'')) ?>" required maxlength="120"></div><div class="col-md-4"><label class="form-label" for="postal_code">Postal</label><input class="form-control" id="postal_code" name="postal_code" value="<?= e((string)($editAddress['postal_code']??'')) ?>" maxlength="12"></div></div><div class="mt-3"><label class="form-label" for="landmark">Landmark <span class="text-muted">(optional)</span></label><input class="form-control" id="landmark" name="landmark" value="<?= e((string)($editAddress['landmark']??'')) ?>" maxlength="190"></div><div class="mt-3"><label class="form-label" for="delivery_notes">Delivery notes <span class="text-muted">(optional)</span></label><textarea class="form-control" id="delivery_notes" name="delivery_notes" rows="3" maxlength="255"><?= e((string)($editAddress['notes']??'')) ?></textarea></div><div class="form-check mt-3"><input class="form-check-input" type="checkbox" id="is_default" name="is_default" value="1" <?= !empty($editAddress['is_default'])?'checked':'' ?>><label class="form-check-label" for="is_default">Set as default address</label></div><div class="d-flex gap-2 mt-4"><button class="btn btn-coffee flex-grow-1" type="submit"><?= $editAddress?'Save changes':'Add address' ?></button><?php if($editAddress):?><a class="btn btn-outline-coffee" href="<?= url('customer/addresses.php') ?>">Cancel</a><?php endif;?></div></form></section></div></div>
</main></div>
<?php require __DIR__.'/../includes/footer.php'; ?>
