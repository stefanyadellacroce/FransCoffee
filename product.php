<?php

declare(strict_types=1);
require_once __DIR__ . '/includes/bootstrap.php';

$slug = trim((string) ($_GET['slug'] ?? ''));
$product = $slug !== '' ? storefront_product_by_slug($slug) : null;

if (!$product) {
    http_response_code(404);
    $pageTitle = 'Product not found';
    $activeNav = 'menu';
    $footerMode = 'public';
    require __DIR__ . '/includes/header.php';
    require __DIR__ . '/includes/navbar.php';
    ?>
    <main class="store-section"><div class="container-fluid fc-container"><div class="empty-state"><i class="bi bi-cup"></i><h1>Product not found.</h1><p>The item may be unavailable or the link may be incorrect.</p><a class="btn btn-coffee" href="<?= url('menu.php') ?>">Back to menu</a></div></div></main>
    <?php require __DIR__ . '/includes/footer.php'; exit;
}

$pageTitle = (string) $product['name'];
$activeNav = 'menu';
$footerMode = 'public';
$success = flash('success');
$error = flash('error');
$sizes = storefront_product_sizes((int) $product['id']);
$allAddons = storefront_product_addons((int) $product['id']);
$milkAddons = [];
$addons = [];
foreach ($allAddons as $addon) {
    if (stripos((string) $addon['name'], 'milk') !== false) $milkAddons[] = $addon;
    else $addons[] = $addon;
}
$isDrink = !empty($sizes);
$isFavorite = is_logged_in() && user_role() === 'CUSTOMER' ? favorite_is_saved((int) $product['id']) : false;
$returnTo = 'product.php?slug=' . urlencode((string) $product['slug']);
$related = array_values(array_filter(
    storefront_products(['category' => (string) $product['category_slug'], 'limit' => 4]),
    fn(array $item): bool => (int) $item['id'] !== (int) $product['id']
));
$related = array_slice($related, 0, 3);

require __DIR__ . '/includes/header.php';
require __DIR__ . '/includes/navbar.php';
?>
<main>
    <section class="product-detail-section">
        <div class="container-fluid fc-container">
            <?php if ($success): ?><div class="alert alert-success mt-3" role="status"><?= e($success) ?></div><?php endif; ?>
            <?php if ($error): ?><div class="alert alert-danger mt-3" role="alert"><?= e($error) ?></div><?php endif; ?>
            <nav class="product-breadcrumb" aria-label="Breadcrumb">
                <a href="<?= url('index.php') ?>">Home</a><i class="bi bi-chevron-right"></i>
                <a href="<?= url('menu.php?category=' . urlencode((string) $product['category_slug'])) ?>"><?= e($product['category_name']) ?></a><i class="bi bi-chevron-right"></i>
                <span><?= e($product['name']) ?></span>
            </nav>
            <div class="row g-5 align-items-start mt-1">
                <div class="col-lg-6">
                    <div class="product-detail-image"><img src="<?= e(product_image_url($product)) ?>" alt="<?= e($product['name']) ?>"></div>
                </div>
                <div class="col-lg-6">
                    <div class="product-detail-copy">
                        <div class="d-flex align-items-start justify-content-between gap-3">
                            <div>
                                <div class="product-meta mb-2"><?= e($product['category_name']) ?> · Ready in about <?= (int) $product['prep_minutes'] ?> minutes</div>
                                <h1><?= e($product['name']) ?></h1>
                            </div>
                            <form method="post" action="<?= url('actions/favorite.php') ?>">
                                <?= csrf_field() ?>
                                <input type="hidden" name="product_id" value="<?= (int) $product['id'] ?>">
                                <input type="hidden" name="return_to" value="<?= e($returnTo) ?>">
                                <button class="favorite-button <?= $isFavorite ? 'active' : '' ?>" type="submit" aria-label="<?= $isFavorite ? 'Remove from favorites' : 'Save to favorites' ?>" title="<?= $isFavorite ? 'Remove from favorites' : 'Save to favorites' ?>">
                                    <i class="bi <?= $isFavorite ? 'bi-heart-fill' : 'bi-heart' ?>"></i>
                                </button>
                            </form>
                        </div>
                        <div class="product-price mt-3" data-base-price="<?= e((string) $product['base_price']) ?>"><?= money((float) $product['base_price']) ?></div>
                        <p class="product-description mt-4"><?= e((string) $product['description']) ?></p>

                        <form method="post" action="<?= url('actions/cart.php') ?>" data-cart-form>
                            <?= csrf_field() ?>
                            <input type="hidden" name="action" value="add">
                            <input type="hidden" name="product_id" value="<?= (int) $product['id'] ?>">
                            <input type="hidden" name="return_to" value="<?= e($returnTo) ?>">

                            <?php if ($sizes): ?>
                                <div class="product-option-group mt-4">
                                    <div class="option-heading"><strong>Size</strong><span>Choose one</span></div>
                                    <div class="option-choice-grid">
                                        <?php foreach ($sizes as $index => $size): ?>
                                            <label class="option-choice">
                                                <input type="radio" name="size_id" value="<?= (int) $size['id'] ?>" data-price-adjustment="<?= e((string) $size['price_adjustment']) ?>" <?= $index === 0 ? 'checked' : '' ?>>
                                                <span><strong><?= e($size['name']) ?></strong><small><?= (float) $size['price_adjustment'] > 0 ? '+' . money((float) $size['price_adjustment']) : 'Base price' ?></small></span>
                                            </label>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                            <?php endif; ?>

                            <?php if ($isDrink): ?>
                                <div class="product-option-group mt-4">
                                    <div class="option-heading"><strong>Temperature</strong><span>Choose one</span></div>
                                    <div class="option-choice-grid option-choice-grid-2">
                                        <?php $defaultTemperature = commerce_default_temperature($product); ?>
                                        <?php foreach (['HOT' => ['Hot','bi-cup-hot'], 'ICED' => ['Iced','bi-snow']] as $value => [$label,$icon]): ?>
                                            <label class="option-choice">
                                                <input type="radio" name="temperature" value="<?= e($value) ?>" <?= $defaultTemperature === $value ? 'checked' : '' ?>>
                                                <span><strong><i class="bi <?= e($icon) ?> me-1"></i><?= e($label) ?></strong><small>Same price</small></span>
                                            </label>
                                        <?php endforeach; ?>
                                    </div>
                                </div>

                                <div class="product-option-group mt-4">
                                    <div class="option-heading"><strong>Sugar level</strong><span>Choose one</span></div>
                                    <div class="sugar-choice-row">
                                        <?php foreach (['0','25','50','75','100'] as $sugar): ?>
                                            <label class="sugar-choice"><input type="radio" name="sugar_level" value="<?= $sugar ?>" <?= $sugar === '100' ? 'checked' : '' ?>><span><?= $sugar ?>%</span></label>
                                        <?php endforeach; ?>
                                    </div>
                                </div>

                                <?php if ($milkAddons): ?>
                                <div class="product-option-group mt-4">
                                    <div class="option-heading"><strong>Milk</strong><span>Choose one</span></div>
                                    <div class="option-choice-grid">
                                        <label class="option-choice">
                                            <input type="radio" name="milk_addon_id" value="0" data-milk-price="0" checked>
                                            <span><strong>Regular Milk</strong><small>Included</small></span>
                                        </label>
                                        <?php foreach ($milkAddons as $milk): ?>
                                            <label class="option-choice">
                                                <input type="radio" name="milk_addon_id" value="<?= (int) $milk['id'] ?>" data-milk-price="<?= e((string) $milk['price']) ?>">
                                                <span><strong><?= e($milk['name']) ?></strong><small>+<?= money((float) $milk['price']) ?></small></span>
                                            </label>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                                <?php endif; ?>
                            <?php endif; ?>

                            <?php if ($addons): ?>
                                <div class="product-option-group mt-4">
                                    <div class="option-heading"><strong>Add-ons</strong><span>Optional</span></div>
                                    <div class="addon-list">
                                        <?php foreach ($addons as $addon): ?>
                                            <label class="addon-choice">
                                                <span><input class="form-check-input" name="addon_ids[]" value="<?= (int) $addon['id'] ?>" type="checkbox" data-addon-price="<?= e((string) $addon['price']) ?>"> <?= e($addon['name']) ?></span>
                                                <strong>+<?= money((float) $addon['price']) ?></strong>
                                            </label>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                            <?php endif; ?>

                            <div class="product-option-group mt-4">
                                <div class="option-heading"><strong>Quantity</strong><span>Maximum 20 per line</span></div>
                                <div class="quantity-picker" data-quantity-picker>
                                    <button type="button" data-qty-minus aria-label="Decrease quantity"><i class="bi bi-dash"></i></button>
                                    <input type="number" name="quantity" value="1" min="1" max="20" inputmode="numeric" aria-label="Quantity">
                                    <button type="button" data-qty-plus aria-label="Increase quantity"><i class="bi bi-plus"></i></button>
                                </div>
                            </div>

                            <div class="product-preview-total mt-4">
                                <span>Item price</span>
                                <strong data-product-preview-total><?= money((float) $product['base_price']) ?></strong>
                            </div>
                            <button class="btn btn-coffee btn-lg w-100 mt-3" type="submit"><i class="bi bi-bag-plus me-2"></i>Add to cart</button>
                            <p class="form-note text-center mt-2 mb-0">Prices are recalculated on the server before the item is saved.</p>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <?php if ($related): ?>
    <section class="store-section store-section-soft">
        <div class="container-fluid fc-container">
            <div class="section-heading"><span class="eyebrow">YOU MAY ALSO LIKE</span><h2>More from <?= e($product['category_name']) ?>.</h2></div>
            <div class="row g-4 mt-1">
                <?php foreach ($related as $item): ?>
                    <div class="col-md-4">
                        <article class="product-card h-100">
                            <a class="product-image-link" href="<?= url('product.php?slug=' . urlencode((string) $item['slug'])) ?>"><img src="<?= e(product_image_url($item)) ?>" alt="<?= e($item['name']) ?>"></a>
                            <div class="product-card-body"><div class="product-meta"><?= e($item['category_name']) ?></div><h3><a href="<?= url('product.php?slug=' . urlencode((string) $item['slug'])) ?>"><?= e($item['name']) ?></a></h3><div class="product-card-footer"><strong><?= money((float) $item['base_price']) ?></strong><a class="text-link" href="<?= url('product.php?slug=' . urlencode((string) $item['slug'])) ?>">Choose Options <i class="bi bi-arrow-right"></i></a></div></div>
                        </article>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </section>
    <?php endif; ?>
</main>
<?php require __DIR__ . '/includes/footer.php'; ?>
