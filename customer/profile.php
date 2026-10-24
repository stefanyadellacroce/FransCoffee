<?php

declare(strict_types=1);
require_once __DIR__ . '/../includes/bootstrap.php';
require_role('CUSTOMER');
$userId=(int)current_user()['id'];
$errors=[];

if(is_post()){
    verify_csrf_or_abort();
    $action=(string)($_POST['action']??'profile');
    if($action==='profile'){
        $result=customer_update_profile($userId,$_POST,$_FILES['avatar']??null);
        if($result['ok']){flash('success','Profile updated successfully.');redirect('customer/profile.php');}
        $errors=$result['errors']??[];
    } elseif($action==='password'){
        $result=customer_change_password($userId,(string)($_POST['current_password']??''),(string)($_POST['new_password']??''),(string)($_POST['confirm_password']??''));
        if($result['ok']){flash('success','Password changed successfully.');redirect('customer/profile.php');}
        $errors=$result['errors']??[];
    }
}

$profile=customer_profile($userId);
$categories=storefront_categories();
$success=flash('success');
$pageTitle='Profile';
require __DIR__.'/../includes/header.php';
?>
<div class="portal-shell"><?php require __DIR__.'/../includes/sidebar.php';?><main class="portal-main customer-portal-main">
<?php if($success):?><div class="alert alert-success"><?= e($success) ?></div><?php endif;?>
<?php if($errors):?><div class="alert alert-danger"><strong>Please check the following:</strong><ul class="mb-0 mt-2"><?php foreach($errors as $error):?><li><?= e($error) ?></li><?php endforeach;?></ul></div><?php endif;?>
<div class="portal-page-header"><div><span class="eyebrow">MY ACCOUNT</span><h1 class="mt-2">Profile</h1><p>Keep your contact details and coffee preferences up to date.</p></div></div>
<div class="row g-4"><div class="col-xl-8">
<section class="customer-panel"><div class="panel-heading"><div><span class="eyebrow">PERSONAL INFORMATION</span><h2>Your details</h2></div></div><form method="post" enctype="multipart/form-data" data-disable-on-submit><?= csrf_field() ?><input type="hidden" name="action" value="profile">
<div class="profile-photo-row"><div class="profile-photo-preview"><?php if(!empty($profile['avatar_path'])):?><img src="<?= url((string)$profile['avatar_path']) ?>" alt="Profile photo"><?php else:?><span><?= e(strtoupper(substr((string)$profile['first_name'],0,1))) ?></span><?php endif;?></div><div><label class="form-label" for="avatar">Profile photo</label><input class="form-control" id="avatar" type="file" name="avatar" accept="image/jpeg,image/png,image/webp"><div class="form-note">JPG, PNG, or WEBP up to 3 MB.</div></div></div>
<div class="row g-3 mt-1"><div class="col-md-6"><label class="form-label" for="first_name">First name</label><input class="form-control" id="first_name" name="first_name" value="<?= e((string)$profile['first_name']) ?>" required maxlength="80"></div><div class="col-md-6"><label class="form-label" for="last_name">Last name</label><input class="form-control" id="last_name" name="last_name" value="<?= e((string)$profile['last_name']) ?>" required maxlength="80"></div><div class="col-md-6"><label class="form-label" for="email">Email</label><input class="form-control" id="email" value="<?= e((string)$profile['email']) ?>" readonly><div class="form-note">Email changes are disabled here for account safety. Contact an administrator if the address must be changed.</div></div><div class="col-md-6"><label class="form-label" for="phone">Phone</label><input class="form-control" id="phone" name="phone" value="<?= e((string)($profile['phone']??'')) ?>" maxlength="30"></div><div class="col-md-6"><label class="form-label" for="preferred_category">Favorite category</label><select class="form-select" id="preferred_category" name="preferred_category"><option value="">No preference</option><?php foreach($categories as $category):?><option value="<?= e($category['slug']) ?>" <?= ($profile['preferred_category']??'')===$category['slug']?'selected':'' ?>><?= e($category['name']) ?></option><?php endforeach;?></select></div><div class="col-md-6 d-flex align-items-end"><div class="form-check form-switch mb-2"><input class="form-check-input" type="checkbox" role="switch" id="notifications_enabled" name="notifications_enabled" value="1" <?= !empty($profile['notifications_enabled'])?'checked':'' ?>><label class="form-check-label" for="notifications_enabled">Receive in-app notifications</label></div></div></div>
<button class="btn btn-coffee mt-4" type="submit">Save changes</button></form></section>
</div><div class="col-xl-4"><section class="customer-panel"><span class="eyebrow">SECURITY</span><h2>Change password</h2><p class="text-muted">Use a strong password you don’t reuse elsewhere.</p><form method="post" data-disable-on-submit><?= csrf_field() ?><input type="hidden" name="action" value="password"><div class="mb-3"><label class="form-label" for="current_password">Current password</label><input class="form-control" type="password" id="current_password" name="current_password" required autocomplete="current-password"></div><div class="mb-3"><label class="form-label" for="new_password">New password</label><input class="form-control" type="password" id="new_password" name="new_password" required autocomplete="new-password"></div><div class="mb-3"><label class="form-label" for="confirm_password">Confirm new password</label><input class="form-control" type="password" id="confirm_password" name="confirm_password" required autocomplete="new-password"></div><button class="btn btn-outline-coffee w-100" type="submit">Update password</button></form></section><section class="customer-panel mt-4"><span class="eyebrow">DELIVERY</span><h2>Saved addresses</h2><p class="text-muted">Manage the addresses available during checkout.</p><a class="btn btn-outline-coffee w-100" href="<?= url('customer/addresses.php') ?>">Manage addresses</a></section></div></div>
</main></div>
<?php require __DIR__.'/../includes/footer.php'; ?>
