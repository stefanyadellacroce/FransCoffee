<?php

declare(strict_types=1);
require_once __DIR__.'/../includes/bootstrap.php';
require_role('ADMIN');

if(is_post()){
    verify_csrf_or_abort();
    $action=(string)($_POST['action']??'');
    if($action==='save_category'){
        $id=(int)($_POST['category_id']??0);
        $result=admin_save_category($_POST,$id);
        if(!$result['ok']) { $_SESSION['_category_errors']=$result['errors']; $_SESSION['_category_form']=$_POST; redirect('admin/categories.php'.($id>0?'?edit='.$id:'')); }
    } elseif($action==='toggle_category') {
        $result=admin_toggle_category((int)($_POST['category_id']??0));
    } else $result=['ok'=>false,'message'=>'Choose a valid category action.'];
    flash($result['ok']?'success':'error',$result['message']);
    redirect('admin/categories.php');
}

$editId=(int)($_GET['edit']??0); $editing=$editId>0?admin_category_by_id($editId):null;
if($editId>0 && !$editing){ flash('error','Category not found.'); redirect('admin/categories.php'); }
$form=$_SESSION['_category_form']??$editing??['name'=>'','sort_order'=>count(admin_categories())+1,'is_active'=>1];
$errors=$_SESSION['_category_errors']??[]; unset($_SESSION['_category_form'],$_SESSION['_category_errors']);
$categories=admin_categories();
$pageTitle='Categories'; $bodyClass='portal-page'; require __DIR__.'/../includes/header.php'; $success=flash('success');$error=flash('error');
?>
<div class="portal-shell"><?php require __DIR__.'/../includes/sidebar.php'; ?><main class="portal-main catalog-main">
<header class="portal-page-header"><div><span class="eyebrow">CATALOG</span><h1 class="mt-2 mb-1">Categories</h1><p class="text-muted mb-0">Organize the menu and control which groups appear on the storefront.</p></div><a class="btn btn-outline-coffee" href="<?=url('admin/products.php')?>"><i class="bi bi-arrow-left me-2"></i>Products</a></header>
<?php if($success):?><div class="alert alert-success"><?=e($success)?></div><?php endif;?><?php if($error):?><div class="alert alert-danger"><?=e($error)?></div><?php endif;?>
<div class="row g-4 align-items-start"><div class="col-xl-4"><section class="portal-card catalog-editor-card"><span class="eyebrow"><?= $editing?'EDIT CATEGORY':'NEW CATEGORY' ?></span><h2><?= $editing?'Update category':'Create category' ?></h2>
<?php if($errors):?><div class="alert alert-danger"><ul class="mb-0 ps-3"><?php foreach($errors as $err):?><li><?=e((string)$err)?></li><?php endforeach;?></ul></div><?php endif;?>
<form method="post" data-disable-on-submit><?=csrf_field()?><input type="hidden" name="action" value="save_category"><input type="hidden" name="category_id" value="<?= (int)($editing['id']??0) ?>">
<div class="mb-3"><label class="form-label" for="categoryName">Category name</label><input class="form-control" id="categoryName" name="name" maxlength="100" required value="<?=e((string)($form['name']??''))?>" placeholder="e.g. Seasonal Drinks"></div>
<div class="mb-3"><label class="form-label" for="sortOrder">Sort order</label><input class="form-control" id="sortOrder" name="sort_order" type="number" min="0" max="9999" required value="<?=e((string)($form['sort_order']??0))?>"><div class="form-text">Lower numbers appear first.</div></div>
<div class="form-check form-switch mb-4"><input class="form-check-input" type="checkbox" role="switch" id="categoryActive" name="is_active" value="1" <?= !empty($form['is_active'])?'checked':'' ?>><label class="form-check-label" for="categoryActive">Visible on storefront</label></div>
<div class="d-flex gap-2"><button class="btn btn-coffee flex-grow-1" type="submit"><?= $editing?'Save changes':'Create category' ?></button><?php if($editing):?><a class="btn btn-outline-secondary" href="<?=url('admin/categories.php')?>">Cancel</a><?php endif;?></div>
</form></section></div>
<div class="col-xl-8"><section class="portal-card"><div class="panel-heading"><div><span class="eyebrow">MENU GROUPS</span><h2><?=count($categories)?> categories</h2></div></div>
<div class="catalog-category-list"><?php foreach($categories as $category):?><article class="catalog-category-row <?= (int)$category['is_active']!==1?'is-disabled':'' ?>"><div class="catalog-category-order"><?= (int)$category['sort_order'] ?></div><div class="catalog-category-copy"><strong><?=e((string)$category['name'])?></strong><span>/<?=e((string)$category['slug'])?> · <?= (int)$category['product_count'] ?> product<?= (int)$category['product_count']===1?'':'s' ?></span></div><span class="badge <?= (int)$category['is_active']===1?'text-bg-success':'text-bg-secondary' ?>"><?= (int)$category['is_active']===1?'ACTIVE':'DISABLED' ?></span><div class="catalog-category-actions"><a class="btn btn-sm btn-outline-coffee" href="<?=url('admin/categories.php?edit='.(int)$category['id'])?>">Edit</a><form method="post"><?=csrf_field()?><input type="hidden" name="action" value="toggle_category"><input type="hidden" name="category_id" value="<?=(int)$category['id']?>"><button class="btn btn-sm <?= (int)$category['is_active']===1?'btn-outline-danger':'btn-outline-success' ?>" type="submit" data-confirm="<?= (int)$category['is_active']===1?'Disable this category? Its products will be hidden from the storefront.':'Enable this category?' ?>"><?= (int)$category['is_active']===1?'Disable':'Enable' ?></button></form></div></article><?php endforeach;?></div>
</section></div></div>
</main></div><?php require __DIR__.'/../includes/footer.php'; ?>
