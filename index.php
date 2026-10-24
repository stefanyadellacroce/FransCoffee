<?php

declare(strict_types=1);
require_once __DIR__ . '/includes/bootstrap.php';

$pageTitle = 'Home';
$activeNav = 'home';
$footerMode = 'public';
$featuredProducts = storefront_products(['featured' => true, 'limit' => 6]);
$categories = storefront_categories();

require __DIR__ . '/includes/header.php';
require __DIR__ . '/includes/navbar.php';
?>
<main>
    <section class="store-hero">
        <div class="container-fluid fc-container">
            <div class="row align-items-center g-5 py-5 py-lg-6">
                <div class="col-lg-6">
                    <span class="eyebrow">FRANSCOFFEE SHOP · CRAFTED DAILY</span>
                    <h1 class="store-hero-title">Cozy cups, fresh bakes, and better coffee moments.</h1>
                    <p class="store-hero-copy">Enjoy signature coffee, comforting pastries, and a warm café feel in every order—from your first sip in the morning to your last break of the day.</p>
                    <div class="d-flex flex-wrap gap-3 mt-4">
                        <a class="btn btn-coffee btn-lg" href="<?= url('menu.php') ?>">Explore menu</a>
                        <a class="btn btn-outline-coffee btn-lg" href="<?= url('menu.php') ?>">Order now</a>
                    </div>
                    <div class="hero-meta mt-5">
                        <div><strong>Fresh daily</strong><span>Small-batch drinks & bites</span></div>
                        <div><strong>Made your way</strong><span>Sizes and add-ons available</span></div>
                        <div><strong>Warm service</strong><span>Pickup, delivery, and walk-in orders</span></div>
                    </div>
                </div>
                <div class="col-lg-6">
                    <div class="hero-art-card">
                        <img src="<?= asset('images/backgrounds/homepage-hero-coffee.png') ?>" alt="Franscoffee iced latte and croissant in a cozy café setting">
                        <div class="hero-art-note">
                            <span>Signature pairing</span>
                            <strong>Spanish Latte</strong>
                            <small>bold coffee · buttery pastry · cozy café vibe</small>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <section class="store-section">
        <div class="container-fluid fc-container">
            <div class="section-heading d-flex flex-column flex-lg-row align-items-lg-end justify-content-between gap-3">
                <div>
                    <span class="eyebrow">FIND YOUR FAVORITE</span>
                    <h2>Something for every kind of coffee break.</h2>
                </div>
                <a class="text-link" href="<?= url('menu.php') ?>">Browse full menu <i class="bi bi-arrow-right"></i></a>
            </div>
            <div class="category-strip mt-4">
                <?php foreach ($categories as $category): ?>
                    <a class="category-chip" href="<?= url('menu.php?category=' . urlencode((string) $category['slug'])) ?>">
                        <span class="category-icon"><i class="bi bi-cup-hot"></i></span>
                        <span><?= e($category['name']) ?></span>
                    </a>
                <?php endforeach; ?>
            </div>
        </div>
    </section>

    <section class="store-section store-section-soft">
        <div class="container-fluid fc-container">
            <div class="section-heading d-flex flex-column flex-lg-row align-items-lg-end justify-content-between gap-3">
                <div>
                    <span class="eyebrow">BEST SELLERS</span>
                    <h2>Most-loved picks from Franscoffee.</h2>
                </div>
                <a class="text-link" href="<?= url('menu.php') ?>">See all drinks & food <i class="bi bi-arrow-right"></i></a>
            </div>
            <?php if ($featuredProducts): ?>
                <div class="row g-4 mt-1">
                    <?php foreach ($featuredProducts as $product): ?>
                        <div class="col-sm-6 col-xl-4">
                            <article class="product-card h-100">
                                <form class="product-card-favorite" method="post" action="<?= url('actions/favorite.php') ?>">
                                    <?= csrf_field() ?><input type="hidden" name="product_id" value="<?= (int) $product['id'] ?>"><input type="hidden" name="return_to" value="index.php">
                                    <button type="submit" aria-label="<?= favorite_is_saved((int) $product['id']) ? 'Remove from favorites' : 'Save to favorites' ?>"><i class="bi <?= favorite_is_saved((int) $product['id']) ? 'bi-heart-fill' : 'bi-heart' ?>"></i></button>
                                </form>
                                <a class="product-image-link" href="<?= url('product.php?slug=' . urlencode((string) $product['slug'])) ?>">
                                    <img src="<?= e(product_image_url($product)) ?>" alt="<?= e($product['name']) ?>">
                                    <span class="product-badge">Popular</span>
                                </a>
                                <div class="product-card-body">
                                    <div class="product-meta"><?= e($product['category_name']) ?> · <?= (int) $product['prep_minutes'] ?> min</div>
                                    <h3><a href="<?= url('product.php?slug=' . urlencode((string) $product['slug'])) ?>"><?= e($product['name']) ?></a></h3>
                                    <p><?= e(mb_strimwidth((string) $product['description'], 0, 105, '…')) ?></p>
                                    <div class="product-card-footer">
                                        <strong><?= money((float) $product['base_price']) ?></strong>
                                        <div class="product-card-buttons">
                                            <a class="btn btn-sm btn-outline-coffee" href="<?= url('product.php?slug=' . urlencode((string) $product['slug'])) ?>">Choose Options</a>
                                            <form method="post" action="<?= url('actions/cart.php') ?>">
                                                <?= csrf_field() ?><input type="hidden" name="action" value="add"><input type="hidden" name="product_id" value="<?= (int) $product['id'] ?>"><input type="hidden" name="quantity" value="1"><input type="hidden" name="return_to" value="index.php">
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
                    <i class="bi bi-cup-hot"></i>
                    <h3>Menu products are not seeded yet.</h3>
                    <p>The menu is temporarily unavailable. Please check back soon or contact the shop.</p>
                </div>
            <?php endif; ?>
        </div>
    </section>

    <section class="store-section">
        <div class="container-fluid fc-container">
            <div class="story-panel">
                <div class="story-copy">
                    <span class="eyebrow">OUR EVERYDAY RITUAL</span>
                    <h2>More than coffee, a better day.</h2>
                    <p>Franscoffee is built around simple things done well: balanced drinks, familiar comfort food, a warm atmosphere, and a service flow that never gets in the way of your coffee break.</p>
                    <a class="btn btn-outline-coffee" href="<?= url('about.php') ?>">Our story</a>
                </div>
                <div class="story-stats">
                    <div><strong>8</strong><span>menu categories</span></div>
                    <div><strong>7 days</strong><span>open every week</span></div>
                    <div><strong>1 goal</strong><span>make every cup feel worth it</span></div>
                </div>
            </div>
        </div>
    </section>

    <section class="store-section pt-0">
        <div class="container-fluid fc-container">
            <div class="promo-panel">
                <div>
                    <span class="eyebrow text-white-50">FRANSCOFFEE AFTERNOONS</span>
                    <h2>Pair your drink with something freshly baked.</h2>
                    <p>Explore pastries and comfort-food favorites for your next break.</p>
                </div>
                <a class="btn btn-light" href="<?= url('menu.php?category=pastries') ?>">Explore pastries</a>
            </div>
        </div>
    </section>

    <section class="store-section store-section-soft">
        <div class="container-fluid fc-container">
            <div class="section-heading text-center mx-auto section-heading-centered">
                <span class="eyebrow">WHY PEOPLE COME BACK</span>
                <h2>Comforting coffee, without the fuss.</h2>
            </div>
            <div class="row g-4 mt-2">
                <?php foreach ([
                    ['bi-stars', 'Made with care', 'Every item is presented clearly, with straightforward choices and honest pricing.'],
                    ['bi-clock', 'Easy to choose', 'A focused menu, useful filters, and simple product details keep ordering uncomplicated.'],
                    ['bi-heart', 'Warm by design', 'The experience stays cozy and welcoming from the storefront to your account.'],
                ] as [$icon, $title, $copy]): ?>
                    <div class="col-md-4">
                        <article class="value-card h-100"><i class="bi <?= e($icon) ?>"></i><h3><?= e($title) ?></h3><p><?= e($copy) ?></p></article>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </section>

    <section class="store-section">
        <div class="container-fluid fc-container">
            <div class="visit-panel">
                <div>
                    <span class="eyebrow">VISIT FRANSCOFFEE</span>
                    <h2>Your neighborhood coffee stop.</h2>
                    <p><?= e(shop_setting('store_address', 'Cebu City, Cebu')) ?></p>
                </div>
                <div class="visit-details">
                    <div><i class="bi bi-clock"></i><span><?= e(shop_setting('business_hours', 'Monday–Sunday · 7:00 AM–9:00 PM')) ?></span></div>
                    <div><i class="bi bi-telephone"></i><span><?= e(shop_setting('contact_phone', '09XX XXX XXXX')) ?></span></div>
                    <div><i class="bi bi-envelope"></i><span><?= e(shop_setting('contact_email', 'francis@franscoffee.com')) ?></span></div>
                </div>
                <a class="btn btn-coffee" href="<?= url('contact.php') ?>">Contact us</a>
            </div>
        </div>
    </section>
</main>
<?php require __DIR__ . '/includes/footer.php'; ?>
