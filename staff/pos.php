<?php

declare(strict_types=1);
require_once __DIR__ . '/../includes/bootstrap.php';
require_role(['STAFF', 'ADMIN']);

$errors = [];
if (is_post()) {
    verify_csrf_or_abort();
    $result = pos_place_order((int) current_user()['id'], $_POST);
    if (!empty($result['ok'])) {
        flash('success', 'POS sale completed successfully.');
        redirect('staff/receipt.php?order=' . rawurlencode((string) $result['order_number']));
    }
    $errors = $result['errors'] ?? ['Unable to complete the POS sale.'];
}

$catalog = pos_catalog();
$categories = storefront_categories();
$customers = pos_customers();
$payments = pos_payment_methods();
foreach ($catalog as &$product) {
    $product['image_url'] = product_image_url($product);
}
unset($product);

$pageTitle = 'Point of Sale';
$bodyClass = 'portal-page';
require __DIR__ . '/../includes/header.php';
?>
<div class="portal-shell">
    <?php require __DIR__ . '/../includes/sidebar.php'; ?>
    <main class="portal-main pos-main">
        <header class="portal-page-header pos-page-header">
            <div>
                <span class="eyebrow">STAFF / CASHIER</span>
                <h1 class="mt-2 mb-1">Point of Sale</h1>
                <p class="text-muted mb-0">Build a walk-in order, take payment, and print the receipt.</p>
            </div>
            <div class="pos-live-chip"><span></span> Register ready</div>
        </header>

        <?php if ($errors): ?>
            <div class="alert alert-danger" role="alert"><strong>Sale not completed.</strong><ul class="mb-0 mt-2"><?php foreach ($errors as $error): ?><li><?= e((string) $error) ?></li><?php endforeach; ?></ul></div>
        <?php endif; ?>

        <form method="post" data-pos-form autocomplete="off">
            <?= csrf_field() ?>
            <input type="hidden" name="items_json" value="[]" data-pos-items-json>
            <input type="hidden" name="customer_id" value="0" data-pos-customer-id>

            <div class="pos-layout">
                <section class="pos-catalog-panel" aria-label="Product catalog">
                    <div class="pos-toolbar">
                        <label class="pos-search">
                            <i class="bi bi-search"></i>
                            <input type="search" placeholder="Search drinks, pastries, meals…" data-pos-product-search>
                        </label>
                        <div class="pos-category-tabs" data-pos-category-tabs>
                            <button type="button" class="active" data-pos-category="all">All</button>
                            <?php foreach ($categories as $category): ?>
                                <button type="button" data-pos-category="<?= e((string) $category['slug']) ?>"><?= e((string) $category['name']) ?></button>
                            <?php endforeach; ?>
                        </div>
                    </div>

                    <div class="pos-product-grid" data-pos-product-grid>
                        <?php foreach ($catalog as $product): ?>
                            <article class="pos-product-card" data-pos-product-card data-product-id="<?= (int) $product['id'] ?>" data-category="<?= e((string) $product['category_slug']) ?>" data-search="<?= e(strtolower((string) ($product['name'] . ' ' . $product['category_name']))) ?>">
                                <button type="button" class="pos-product-button" data-pos-open-product="<?= (int) $product['id'] ?>">
                                    <img src="<?= e((string) $product['image_url']) ?>" alt="<?= e((string) $product['name']) ?>">
                                    <span class="pos-product-category"><?= e((string) $product['category_name']) ?></span>
                                    <strong><?= e((string) $product['name']) ?></strong>
                                    <span class="pos-product-price"><?= money((float) $product['base_price']) ?></span>
                                </button>
                            </article>
                        <?php endforeach; ?>
                    </div>
                    <div class="pos-empty-products" data-pos-empty-products hidden><i class="bi bi-search"></i><strong>No products found</strong><span>Try another name or category.</span></div>
                </section>

                <aside class="pos-order-panel">
                    <div class="pos-order-head">
                        <div><span class="eyebrow">CURRENT ORDER</span><h2>Walk-in sale</h2></div>
                        <button type="button" class="btn btn-link btn-sm text-danger text-decoration-none" data-pos-clear>Clear</button>
                    </div>

                    <div class="pos-customer-box">
                        <label class="form-label">Customer <span class="text-muted fw-normal">(optional)</span></label>
                        <div class="pos-customer-search-wrap">
                            <i class="bi bi-person"></i>
                            <input type="search" class="form-control" placeholder="Walk-in or search customer" data-pos-customer-search>
                            <button type="button" class="pos-customer-clear" aria-label="Clear customer" data-pos-customer-clear hidden><i class="bi bi-x"></i></button>
                        </div>
                        <div class="pos-customer-results" data-pos-customer-results hidden></div>
                        <div class="pos-selected-customer" data-pos-selected-customer><i class="bi bi-person-walking"></i><div><strong>Walk-in Customer</strong><span>No account attached</span></div></div>
                    </div>

                    <div class="pos-cart-lines" data-pos-cart-lines>
                        <div class="pos-empty-cart"><i class="bi bi-bag"></i><strong>No items yet</strong><span>Select a product to start the order.</span></div>
                    </div>

                    <div class="pos-discount-row">
                        <div><label class="form-label" for="discount_type">Discount</label><select class="form-select form-select-sm" name="discount_type" id="discount_type" data-pos-discount-type><option value="NONE">None</option><option value="PERCENT">Percent</option><option value="FIXED">Fixed amount</option></select></div>
                        <div><label class="form-label" for="discount_value">Value</label><input class="form-control form-control-sm" type="number" name="discount_value" id="discount_value" min="0" step="0.01" value="0" data-pos-discount-value disabled></div>
                    </div>

                    <div class="pos-totals">
                        <div><span>Subtotal</span><strong data-pos-subtotal>₱0.00</strong></div>
                        <div><span>Discount</span><strong class="text-success" data-pos-discount>−₱0.00</strong></div>
                        <div class="pos-total-line"><span>Total</span><strong data-pos-total>₱0.00</strong></div>
                    </div>

                    <div class="pos-payment-section">
                        <label class="form-label">Payment method</label>
                        <div class="pos-payment-methods">
                            <?php foreach ($payments as $index => $method): ?>
                                <label class="pos-payment-choice">
                                    <input type="radio" name="payment_method" value="<?= e((string) $method['code']) ?>" <?= $index === 0 ? 'checked' : '' ?> data-pos-payment data-receiver-name="<?= e((string) ($method['receiver_name'] ?? '')) ?>" data-receiver-number="<?= e((string) ($method['receiver_number'] ?? '')) ?>">
                                    <span><i class="bi <?= $method['code'] === 'CASH' ? 'bi-cash-stack' : 'bi-phone' ?>"></i><?= e((string) $method['name']) ?></span>
                                </label>
                            <?php endforeach; ?>
                        </div>
                        <div class="pos-cash-fields" data-pos-cash-fields>
                            <label class="form-label" for="cash_received">Cash received</label>
                            <input class="form-control" type="number" name="cash_received" id="cash_received" min="0" step="0.01" placeholder="0.00" data-pos-cash-received>
                            <div class="pos-change-row"><span>Change</span><strong data-pos-change>₱0.00</strong></div>
                        </div>
                        <div class="pos-reference-fields" data-pos-reference-fields hidden>
                            <div class="pos-ewallet-receiver" data-pos-ewallet-receiver hidden><span>Receive payment to</span><strong data-pos-receiver-name></strong><small data-pos-receiver-number></small></div>
                            <label class="form-label" for="reference_number">Transaction reference</label>
                            <input class="form-control" type="text" name="reference_number" id="reference_number" maxlength="120" placeholder="GCash / Maya reference no.">
                            <small class="text-muted">Confirm the transfer before completing the sale.</small>
                        </div>
                    </div>

                    <div class="mb-3"><label class="form-label" for="order_notes">Order notes</label><textarea class="form-control" name="order_notes" id="order_notes" rows="2" maxlength="500" placeholder="Optional preparation notes"></textarea></div>
                    <button class="btn btn-coffee btn-lg w-100" type="submit" data-pos-submit disabled><i class="bi bi-check2-circle me-2"></i>Complete sale</button>
                </aside>
            </div>
        </form>
    </main>
</div>

<div class="modal fade" id="posProductModal" tabindex="-1" aria-labelledby="posProductModalTitle" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content pos-product-modal">
            <div class="modal-header border-0 pb-0"><div><span class="eyebrow" data-pos-modal-category>MENU ITEM</span><h2 class="modal-title" id="posProductModalTitle" data-pos-modal-title>Product</h2></div><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button></div>
            <div class="modal-body">
                <div class="pos-modal-product-summary"><img src="" alt="" data-pos-modal-image><div><span>Base price</span><strong data-pos-modal-base>₱0.00</strong></div></div>
                <div data-pos-size-section><label class="form-label">Size</label><div class="pos-option-grid" data-pos-sizes></div></div>
                <div data-pos-temperature-section class="mt-3"><label class="form-label">Temperature</label><div class="pos-option-grid"><label><input type="radio" name="pos_modal_temperature" value="HOT"><span>Hot</span></label><label><input type="radio" name="pos_modal_temperature" value="ICED"><span>Iced</span></label></div></div>
                <div data-pos-sugar-section class="mt-3"><label class="form-label">Sugar level</label><select class="form-select" data-pos-sugar><option value="0">0%</option><option value="25">25%</option><option value="50">50%</option><option value="75">75%</option><option value="100" selected>100%</option></select></div>
                <div data-pos-milk-section class="mt-3"><label class="form-label">Milk</label><div class="pos-option-grid" data-pos-milks></div></div>
                <div data-pos-addons-section class="mt-3"><label class="form-label">Add-ons</label><div class="pos-addon-list" data-pos-addons></div></div>
                <div class="pos-modal-footer-row mt-4"><div class="quantity-picker" data-pos-modal-quantity><button type="button" data-pos-modal-minus aria-label="Decrease quantity">−</button><input type="number" min="1" max="20" value="1" data-pos-modal-qty><button type="button" data-pos-modal-plus aria-label="Increase quantity">+</button></div><div class="text-end"><small class="text-muted d-block">Item total</small><strong class="pos-modal-total" data-pos-modal-total>₱0.00</strong></div></div>
            </div>
            <div class="modal-footer border-0 pt-0"><button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button><button type="button" class="btn btn-coffee" data-pos-add-item><i class="bi bi-plus-lg me-1"></i>Add to order</button></div>
        </div>
    </div>
</div>

<script type="application/json" id="posCatalogData"><?= json_encode($catalog, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?></script>
<script type="application/json" id="posCustomerData"><?= json_encode($customers, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?></script>
<?php require __DIR__ . '/../includes/footer.php'; ?>
