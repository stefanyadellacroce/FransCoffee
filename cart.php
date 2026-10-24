<?php

declare(strict_types=1);
require_once __DIR__ . '/includes/bootstrap.php';

if (is_logged_in() && user_role() !== 'CUSTOMER') {
    flash('error', 'The shopping cart is available for customer accounts.');
    redirect(role_home((string) user_role()));
}

$pageTitle = 'Your Cart';
$activeNav = '';
$footerMode = 'public';
$success = flash('success');
$error = flash('error');
$items = cart_items();
$subtotal = cart_subtotal($items);

require __DIR__ . '/includes/header.php';
require __DIR__ . '/includes/navbar.php';
?>
<main>
    <section class="page-hero page-hero-compact">
        <div class="container-fluid fc-container">
            <span class="eyebrow">YOUR ORDER</span>
            <h1>Cart</h1>
            <p>Review your drinks, food, customizations, and quantities before checkout.</p>
        </div>
    </section>
    <section class="store-section pt-4">
        <div class="container-fluid fc-container">
            <?php if ($success): ?><div class="alert alert-success" role="status"><?= e($success) ?></div><?php endif; ?>
            <?php if ($error): ?><div class="alert alert-danger" role="alert"><?= e($error) ?></div><?php endif; ?>

            <?php if (!$items): ?>
                <div class="empty-state">
                    <i class="bi bi-bag"></i>
                    <h3>Your cart is waiting for something good.</h3>
                    <p>Browse the Franscoffee menu and add a drink, pastry, or meal.</p>
                    <a class="btn btn-coffee" href="<?= url('menu.php') ?>">Explore menu</a>
                </div>
            <?php else: ?>
                <div class="row g-4 align-items-start">
                    <div class="col-lg-8">
                        <div class="cart-list">
                            <?php foreach ($items as $item): ?>
                                <?php $imageProduct = ['image_path' => $item['image_path']]; ?>
                                <article class="cart-item">
                                    <a class="cart-item-image" href="<?= url('product.php?slug=' . urlencode((string) $item['slug'])) ?>"><img src="<?= e(product_image_url($imageProduct)) ?>" alt="<?= e($item['name']) ?>"></a>
                                    <div class="cart-item-main">
                                        <div class="cart-item-heading">
                                            <div>
                                                <div class="product-meta"><?= e($item['category_name']) ?></div>
                                                <h2><a href="<?= url('product.php?slug=' . urlencode((string) $item['slug'])) ?>"><?= e($item['name']) ?></a></h2>
                                            </div>
                                            <strong><?= money((float) $item['line_total']) ?></strong>
                                        </div>
                                        <?php $optionLines = cart_option_lines($item); ?>
                                        <?php if ($optionLines): ?><p class="cart-options"><?= e(implode(' · ', $optionLines)) ?></p><?php endif; ?>
                                        <div class="cart-item-actions">
                                            <form method="post" action="<?= url('actions/cart.php') ?>" class="cart-qty-form">
                                                <?= csrf_field() ?>
                                                <input type="hidden" name="action" value="update">
                                                <input type="hidden" name="item_id" value="<?= (int) $item['id'] ?>">
                                                <input type="hidden" name="return_to" value="cart.php">
                                                <label class="visually-hidden" for="qty-<?= (int) $item['id'] ?>">Quantity</label>
                                                <input id="qty-<?= (int) $item['id'] ?>" class="form-control" type="number" name="quantity" min="1" max="20" value="<?= (int) $item['quantity'] ?>">
                                                <button class="btn btn-sm btn-outline-coffee" type="submit">Update</button>
                                            </form>
                                            <form method="post" action="<?= url('actions/cart.php') ?>">
                                                <?= csrf_field() ?>
                                                <input type="hidden" name="action" value="remove">
                                                <input type="hidden" name="item_id" value="<?= (int) $item['id'] ?>">
                                                <input type="hidden" name="return_to" value="cart.php">
                                                <button class="btn btn-link cart-remove" type="submit"><i class="bi bi-trash3"></i> Remove</button>
                                            </form>
                                        </div>
                                        <small class="text-muted"><?= money((float) $item['unit_price']) ?> each</small>
                                    </div>
                                </article>
                            <?php endforeach; ?>
                        </div>
                        <div class="d-flex flex-wrap justify-content-between gap-3 mt-4">
                            <a class="btn btn-outline-coffee" href="<?= url('menu.php') ?>"><i class="bi bi-arrow-left me-2"></i>Continue shopping</a>
                            <form method="post" action="<?= url('actions/cart.php') ?>" onsubmit="return confirm('Clear all items from your cart?')">
                                <?= csrf_field() ?>
                                <input type="hidden" name="action" value="clear">
                                <input type="hidden" name="return_to" value="cart.php">
                                <button class="btn btn-link cart-remove" type="submit">Clear cart</button>
                            </form>
                        </div>
                    </div>
                    <div class="col-lg-4">
                        <aside class="cart-summary">
                            <span class="eyebrow">ORDER SUMMARY</span>
                            <h2>Almost ready.</h2>
                            <div class="summary-row"><span>Subtotal</span><strong><?= money($subtotal) ?></strong></div>
                            <div class="summary-row"><span>Discount</span><span>—</span></div>
                            <div class="summary-row"><span>Delivery fee</span><span>Calculated at checkout</span></div>
                            <div class="summary-total"><span>Total so far</span><strong><?= money($subtotal) ?></strong></div>
                            <a class="btn btn-coffee btn-lg w-100 mt-3" href="<?= url('checkout.php') ?>">Proceed to checkout <i class="bi bi-arrow-right ms-2"></i></a>
                            <p class="form-note text-center mt-2 mb-0">Final pricing and delivery charges are recalculated securely at checkout.</p>
                        </aside>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </section>
</main>
<?php require __DIR__ . '/includes/footer.php'; ?>
