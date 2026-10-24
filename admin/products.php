<?php

declare(strict_types=1);
require_once __DIR__ . '/../includes/bootstrap.php';
require_role('ADMIN');

if (is_post()) {
    verify_csrf_or_abort();
    $id=(int)($_POST['product_id']??0);
    $action=(string)($_POST['action']??'');
    if ($action==='toggle_active') $result=admin_toggle_product($id,'is_active');
    elseif ($action==='toggle_featured') $result=admin_toggle_product($id,'is_featured');
    else $result=['ok'=>false,'message'=>'Choose a valid product action.'];
    flash($result['ok']?'success':'error',$result['message']);
    $return=(string)($_POST['return_query']??'');
    redirect('admin/products.php'.($return!==''?'?'.$return:''));
}

$q=trim((string)($_GET['q']??''));
$categoryId=(int)($_GET['category_id']??0);
$status=strtoupper(trim((string)($_GET['status']??'ALL')));
if(!in_array($status,['ALL','ACTIVE','INACTIVE'],true)) $status='ALL';
$featured=!empty($_GET['featured']);
$categories=admin_categories();
$products=admin_products(['q'=>$q,'category_id'=>$categoryId,'status'=>$status,'featured'=>$featured]);
$counts=admin_product_counts();
$pageTitle='Products'; $bodyClass='portal-page';
require __DIR__.'/../includes/header.php';
$success=flash('success'); $error=flash('error');
$returnQuery=http_build_query(array_filter(['q'=>$q,'category_id'=>$categoryId?:null,'status'=>$status!=='ALL'?$status:null,'featured'=>$featured?1:null],fn($v)=>$v!==null&&$v!==''));
?>
<div class="portal-shell"><?php require __DIR__.'/../includes/sidebar.php'; ?><main class="portal-main catalog-main">
<header class="portal-page-header"><div><span class="eyebrow">CATALOG</span><h1 class="mt-2 mb-1">Products</h1><p class="text-muted mb-0">Manage menu items, availability, pricing, images, sizes, and add-ons.</p></div><a class="btn btn-coffee" href="<?= url('admin/product-form.php') ?>"><i class="bi bi-plus-lg me-2"></i>Add product</a></header>
<?php if($success):?><div class="alert alert-success"><i class="bi bi-check-circle me-2"></i><?=e($success)?></div><?php endif;?>
<?php if($error):?><div class="alert alert-danger"><i class="bi bi-exclamation-circle me-2"></i><?=e($error)?></div><?php endif;?>

<section class="catalog-metrics">
<div><span>Total products</span><strong><?= $counts['total'] ?></strong></div>
<div><span>Available</span><strong><?= $counts['active'] ?></strong></div>
<div><span>Archived</span><strong><?= $counts['inactive'] ?></strong></div>
<div><span>Featured</span><strong><?= $counts['featured'] ?></strong></div>
</section>

<section class="portal-card catalog-filter-card">
<form method="get" class="catalog-filter-grid">
<div><label class="form-label" for="productSearch">Search</label><div class="input-group"><span class="input-group-text"><i class="bi bi-search"></i></span><input class="form-control" id="productSearch" name="q" value="<?=e($q)?>" placeholder="Product name or description"></div></div>
<div><label class="form-label" for="categoryFilter">Category</label><select class="form-select" id="categoryFilter" name="category_id"><option value="0">All categories</option><?php foreach($categories as $category):?><option value="<?= (int)$category['id'] ?>" <?= $categoryId===(int)$category['id']?'selected':'' ?>><?=e((string)$category['name'])?><?= (int)$category['is_active']!==1?' (disabled)':'' ?></option><?php endforeach;?></select></div>
<div><label class="form-label" for="statusFilter">Status</label><select class="form-select" id="statusFilter" name="status"><option value="ALL">All statuses</option><option value="ACTIVE" <?= $status==='ACTIVE'?'selected':'' ?>>Available</option><option value="INACTIVE" <?= $status==='INACTIVE'?'selected':'' ?>>Archived</option></select></div>
<div class="form-check catalog-featured-check"><input class="form-check-input" id="featuredOnly" name="featured" value="1" type="checkbox" <?= $featured?'checked':'' ?>><label class="form-check-label" for="featuredOnly">Featured only</label></div>
<div class="catalog-filter-actions"><button class="btn btn-coffee" type="submit">Apply</button><a class="btn btn-outline-secondary" href="<?=url('admin/products.php')?>">Reset</a></div>
</form>
</section>

<section class="portal-card mt-4">
<div class="panel-heading"><div><span class="eyebrow">MENU ITEMS</span><h2><?=count($products)?> product<?=count($products)===1?'':'s'?></h2></div><a class="btn btn-sm btn-outline-coffee" href="<?=url('admin/categories.php')?>"><i class="bi bi-tags me-1"></i>Categories</a></div>
<?php if(!$products):?><div class="empty-state compact"><i class="bi bi-cup-hot"></i><h3>No products found</h3><p>Try another filter or create a menu item.</p><a class="btn btn-coffee" href="<?=url('admin/product-form.php')?>">Add product</a></div><?php else:?>
<div class="table-responsive"><table class="table align-middle catalog-table mb-0"><thead><tr><th>Product</th><th>Category</th><th>Price</th><th>Customizations</th><th>Status</th><th>Updated</th><th class="text-end">Actions</th></tr></thead><tbody>
<?php foreach($products as $product):?><tr class="<?= (int)$product['is_active']!==1?'catalog-row-muted':'' ?>">
<td><div class="catalog-product-cell"><img src="<?=e(product_image_url($product))?>" alt=""><div><strong><?=e((string)$product['name'])?></strong><small><?=e((string)$product['slug'])?></small></div></div></td>
<td><span><?=e((string)$product['category_name'])?></span><?php if((int)$product['category_active']!==1):?><small class="text-danger d-block">Category disabled</small><?php endif;?></td>
<td class="fw-semibold"><?=money((float)$product['base_price'])?></td>
<td><span class="catalog-option-count"><i class="bi bi-arrows-angle-expand"></i><?= (int)$product['active_sizes'] ?> sizes</span><span class="catalog-option-count"><i class="bi bi-plus-circle"></i><?= (int)$product['active_addons'] ?> add-ons</span></td>
<td><div class="catalog-status-stack"><span class="badge <?= (int)$product['is_active']===1?'text-bg-success':'text-bg-secondary' ?>"><?= (int)$product['is_active']===1?'AVAILABLE':'ARCHIVED' ?></span><?php if((int)$product['is_featured']===1):?><span class="badge catalog-featured-badge"><i class="bi bi-star-fill"></i> Featured</span><?php endif;?></div></td>
<td><span><?=e(date('M j, Y',strtotime((string)$product['updated_at'])))?></span><small class="d-block text-muted"><?=e(date('g:i A',strtotime((string)$product['updated_at'])))?></small></td>
<td class="text-end"><div class="catalog-action-group"><a class="btn btn-sm btn-outline-coffee" href="<?=url('admin/product-form.php?id='.(int)$product['id'])?>">Edit</a><form method="post"><?=csrf_field()?><input type="hidden" name="product_id" value="<?=(int)$product['id']?>"><input type="hidden" name="action" value="toggle_featured"><input type="hidden" name="return_query" value="<?=e($returnQuery)?>"><button class="btn btn-sm btn-outline-secondary" type="submit" title="Toggle featured"><i class="bi <?= (int)$product['is_featured']===1?'bi-star-fill':'bi-star' ?>"></i></button></form><form method="post"><?=csrf_field()?><input type="hidden" name="product_id" value="<?=(int)$product['id']?>"><input type="hidden" name="action" value="toggle_active"><input type="hidden" name="return_query" value="<?=e($returnQuery)?>"><button class="btn btn-sm <?= (int)$product['is_active']===1?'btn-outline-danger':'btn-outline-success' ?>" type="submit" data-confirm="<?= (int)$product['is_active']===1?'Archive this product? It will disappear from the storefront and POS.':'Make this product available again?' ?>"><i class="bi <?= (int)$product['is_active']===1?'bi-archive':'bi-arrow-counterclockwise' ?>"></i></button></form></div></td>
</tr><?php endforeach;?></tbody></table></div><?php endif;?>
</section>
</main></div>
<?php require __DIR__.'/../includes/footer.php'; ?>
