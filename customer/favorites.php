<?php

declare(strict_types=1);
require_once __DIR__ . '/../includes/bootstrap.php';
require_role('CUSTOMER');

$pageTitle = 'Favorites';
$footerMode = 'compact';
$success = flash('success');
$error = flash('error');
$products = favorite_products((int) current_user()['id']);
require __DIR__ . '/../includes/header.php';
?>
<div class="portal-shell">
    <?php require __DIR__ . '/../includes/sidebar.php'; ?>
    <main class="portal-main">
        <?php if ($success): ?><div class="alert alert-success" role="status"><?= e($success) ?></div><?php endif; ?>
        <?php if ($error): ?><div class="alert alert-danger" role="alert"><?= e($error) ?></div><?php endif; ?>
        <div class="portal-page-header">
            <div><span class="eyebrow">MY FRANSCOFFEE</span><h1 class="mt-2">Favorites</h1><p>Keep your go-to drinks and food close for the next order.</p></div>
            <a class="btn btn-coffee" href="<?= url('menu.php') ?>">Browse menu</a>
        </div>

        <?php if (!$products): ?>
            <div class="empty-state mt-4"><i class="bi bi-heart"></i><h3>No favorites yet.</h3><p>Tap the heart on a menu item to save it here.</p><a class="btn btn-outline-coffee" href="<?= url('menu.php') ?>">Find a favorite</a></div>
        <?php else: ?>
            <div class="row g-4 mt-1">
                <?php foreach ($products as $product): ?>
                    <div class="col-md-6 col-xl-4">
                        <article class="product-card h-100">
                            <a class="product-image-link" href="<?= url('product.php?slug=' . urlencode((string) $product['slug'])) ?>"><img src="<?= e(product_image_url($product)) ?>" alt="<?= e($product['name']) ?>"></a>
                            <div class="product-card-body">
                                <div class="product-meta"><?= e($product['category_name']) ?></div>
                                <h3><a href="<?= url('product.php?slug=' . urlencode((string) $product['slug'])) ?>"><?= e($product['name']) ?></a></h3>
                                <p><?= e(mb_strimwidth((string) $product['description'], 0, 90, '…')) ?></p>
                                <div class="d-flex align-items-center justify-content-between gap-2 mb-3"><strong><?= money((float) $product['base_price']) ?></strong><a class="text-link" href="<?= url('product.php?slug=' . urlencode((string) $product['slug'])) ?>">Choose Options</a></div>
                                <div class="favorite-card-actions">
                                    <form method="post" action="<?= url('actions/cart.php') ?>">
                                        <?= csrf_field() ?>
                                        <input type="hidden" name="action" value="add"><input type="hidden" name="product_id" value="<?= (int) $product['id'] ?>"><input type="hidden" name="quantity" value="1"><input type="hidden" name="return_to" value="customer/favorites.php">
                                        <button class="btn btn-coffee btn-sm" type="submit"><i class="bi bi-bag-plus me-1"></i>Quick add</button>
                                    </form>
                                    <form method="post" action="<?= url('actions/favorite.php') ?>">
                                        <?= csrf_field() ?>
                                        <input type="hidden" name="product_id" value="<?= (int) $product['id'] ?>"><input type="hidden" name="return_to" value="customer/favorites.php">
                                        <button class="btn btn-outline-coffee btn-sm" type="submit"><i class="bi bi-heartbreak me-1"></i>Remove</button>
                                    </form>
                                </div>
                            </div>
                        </article>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </main>
</div>
<?php require __DIR__ . '/../includes/footer.php'; ?>
