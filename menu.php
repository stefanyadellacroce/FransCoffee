<?php

declare(strict_types=1);
require_once __DIR__ . '/includes/bootstrap.php';

$pageTitle = 'Menu';
$activeNav = 'menu';
$footerMode = 'public';
$category = trim((string) ($_GET['category'] ?? ''));
$q = trim((string) ($_GET['q'] ?? ''));
$categories = storefront_categories();
$products = storefront_products(['category' => $category, 'q' => $q]);
$currentReturn = 'menu.php' . (($q !== '' || $category !== '') ? '?' . http_build_query(array_filter(['q' => $q, 'category' => $category], fn($v) => $v !== '')) : '');

$validCategory = $category === '';
foreach ($categories as $item) {
    if ($item['slug'] === $category) {
        $validCategory = true;
        break;
    }
}
if (!$validCategory) {
    $category = '';
    $products = storefront_products(['q' => $q]);
}

require __DIR__ . '/includes/header.php';
require __DIR__ . '/includes/navbar.php';
?>
<main>
    <section class="page-hero page-hero-menu">
        <div class="container-fluid fc-container">
            <span class="eyebrow">OUR MENU</span>
            <h1>Find your next favorite.</h1>
            <p>From espresso classics to iced drinks, pastries, and easy comfort meals.</p>
        </div>
    </section>

    <section class="store-section pt-4" id="menu-search">
        <div class="container-fluid fc-container">
            <form class="menu-toolbar" method="get" action="<?= url('menu.php') ?>">
                <div class="menu-search-wrap">
                    <i class="bi bi-search"></i>
                    <input class="form-control" type="search" name="q" value="<?= e($q) ?>" placeholder="Search coffee, matcha, pastries…" aria-label="Search menu">
                </div>
                <?php if ($category !== ''): ?><input type="hidden" name="category" value="<?= e($category) ?>"><?php endif; ?>
                <button class="btn btn-coffee" type="submit">Search</button>
                <?php if ($q !== '' || $category !== ''): ?><a class="btn btn-outline-coffee" href="<?= url('menu.php') ?>">Clear</a><?php endif; ?>
            </form>

            <div class="menu-filter-row mt-4" role="navigation" aria-label="Menu categories">
                <a class="menu-filter <?= $category === '' ? 'active' : '' ?>" href="<?= url('menu.php' . ($q !== '' ? '?q=' . urlencode($q) : '')) ?>">All</a>
                <?php foreach ($categories as $item): ?>
                    <?php $query = ['category' => $item['slug']]; if ($q !== '') $query['q'] = $q; ?>
                    <a class="menu-filter <?= $category === $item['slug'] ? 'active' : '' ?>" href="<?= url('menu.php?' . http_build_query($query)) ?>"><?= e($item['name']) ?></a>
                <?php endforeach; ?>
            </div>

            <div class="menu-results-heading mt-5">
                <div>
                    <h2><?= $q !== '' ? 'Search results' : ($category !== '' ? e(ucwords(str_replace('-', ' ', $category))) : 'All menu items') ?></h2>
                    <p><?= count($products) ?> <?= count($products) === 1 ? 'item' : 'items' ?> available</p>
                </div>
            </div>

            <?php if ($products): ?>
                <div class="row g-4 mt-1">
                    <?php foreach ($products as $product): ?>
                        <div class="col-sm-6 col-lg-4 col-xl-3">
                            <article class="product-card h-100">
                                <form class="product-card-favorite" method="post" action="<?= url('actions/favorite.php') ?>">
                                    <?= csrf_field() ?><input type="hidden" name="product_id" value="<?= (int) $product['id'] ?>"><input type="hidden" name="return_to" value="<?= e($currentReturn) ?>">
                                    <button type="submit" aria-label="<?= favorite_is_saved((int) $product['id']) ? 'Remove from favorites' : 'Save to favorites' ?>"><i class="bi <?= favorite_is_saved((int) $product['id']) ? 'bi-heart-fill' : 'bi-heart' ?>"></i></button>
                                </form>
                                <a class="product-image-link" href="<?= url('product.php?slug=' . urlencode((string) $product['slug'])) ?>">
                                    <img src="<?= e(product_image_url($product)) ?>" alt="<?= e($product['name']) ?>">
                                    <?php if ((int) $product['is_featured'] === 1): ?><span class="product-badge">Popular</span><?php endif; ?>
                                </a>
                                <div class="product-card-body">
                                    <div class="product-meta"><?= e($product['category_name']) ?> · <?= (int) $product['prep_minutes'] ?> min</div>
                                    <h3><a href="<?= url('product.php?slug=' . urlencode((string) $product['slug'])) ?>"><?= e($product['name']) ?></a></h3>
                                    <p><?= e(mb_strimwidth((string) $product['description'], 0, 95, '…')) ?></p>
                                    <div class="product-card-footer">
                                        <strong><?= money((float) $product['base_price']) ?></strong>
                                        <div class="product-card-buttons">
                                            <a class="btn btn-sm btn-outline-coffee" href="<?= url('product.php?slug=' . urlencode((string) $product['slug'])) ?>">Choose Options</a>
                                            <form method="post" action="<?= url('actions/cart.php') ?>">
                                                <?= csrf_field() ?><input type="hidden" name="action" value="add"><input type="hidden" name="product_id" value="<?= (int) $product['id'] ?>"><input type="hidden" name="quantity" value="1"><input type="hidden" name="return_to" value="<?= e($currentReturn) ?>">
                                                <button class="btn btn-sm btn-coffee" type="submit" title="Quick add with default options"><i class="bi bi-plus-lg"></i></button>
                                            </form>
                                        </div>
                                    </div>
                                </div>
                            </article>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <div class="empty-state mt-4">
                    <i class="bi bi-search"></i>
                    <h3>No menu items matched.</h3>
                    <p>Try another keyword or clear the category filter.</p>
                    <a class="btn btn-outline-coffee" href="<?= url('menu.php') ?>">View all menu items</a>
                </div>
            <?php endif; ?>
        </div>
    </section>
</main>
<?php require __DIR__ . '/includes/footer.php'; ?>
