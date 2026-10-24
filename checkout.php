<?php

declare(strict_types=1);
require_once __DIR__ . '/includes/bootstrap.php';

if (!is_logged_in()) {
    remember_intended_url('checkout.php');
    flash('error', 'Sign in as a customer to continue to checkout. Your cart will be kept.');
    redirect('login.php');
}
if (user_role() !== 'CUSTOMER') {
    flash('error', 'Checkout is available for customer accounts.');
    redirect(role_home((string) user_role()));
}

$user = current_user();
$customer = checkout_customer((int) $user['id']);
$cart = cart_items();
if (!$cart) {
    flash('error', 'Your cart is empty.');
    redirect('cart.php');
}
$repriced = checkout_reprice_cart($cart);
if (!$repriced['ok']) {
    flash('error', $repriced['error']);
    redirect('cart.php');
}
$promoCodePreview = strtoupper(trim((string) ($_POST['promo_code'] ?? '')));
$promoPreview = promotion_validate($promoCodePreview, (float) $repriced['subtotal'], (int) $user['id']);
$promoDiscountPreview = $promoPreview['ok'] ? (float) ($promoPreview['discount'] ?? 0) : 0.0;

$addresses = customer_addresses((int) $user['id']);
$methods = checkout_payment_methods();
$errors = [];
if (is_post()) {
    verify_csrf_or_abort();
    $result = checkout_place_order((int) $user['id'], $_POST, $_FILES);
    if ($result['ok']) {
        flash('success', 'Order placed successfully.');
        redirect('order-confirmation.php?order=' . rawurlencode((string) $result['order_number']));
    }
    $errors = $result['errors'] ?? ['Unable to place the order.'];
}

$pickupEnabled = shop_setting('pickup_enabled', '1') === '1';
$deliveryEnabled = shop_setting('delivery_enabled', '1') === '1';
$deliveryFee = checkout_delivery_fee('DELIVERY');
$defaultFulfillment = strtoupper((string) ($_POST['fulfillment_type'] ?? ($pickupEnabled ? 'PICKUP' : 'DELIVERY')));
$pageTitle = 'Checkout';
$footerMode = 'public';
require __DIR__ . '/includes/header.php';
require __DIR__ . '/includes/navbar.php';
?>
<main>
<section class="page-hero page-hero-compact"><div class="container-fluid fc-container"><span class="eyebrow">SECURE CHECKOUT</span><h1>Finish your order.</h1><p>Choose pickup or delivery, review your details, then select how you want to pay.</p></div></section>
<section class="store-section pt-4">
<div class="container-fluid fc-container">
<?php if ($errors): ?><div class="alert alert-danger" role="alert"><strong>Please check the following:</strong><ul class="mb-0 mt-2"><?php foreach ($errors as $error): ?><li><?= e($error) ?></li><?php endforeach; ?></ul></div><?php endif; ?>
<form method="post" enctype="multipart/form-data" data-checkout-form data-disable-on-submit>
<?= csrf_field() ?>
<div class="row g-4 align-items-start">
<div class="col-lg-8">
<section class="checkout-card"><div class="checkout-step"><span>1</span><div><span class="eyebrow">CONTACT</span><h2>Your details</h2></div></div><div class="row g-3">
<div class="col-md-6"><label class="form-label" for="contact_name">Full name</label><input class="form-control" id="contact_name" name="contact_name" maxlength="160" required value="<?= e((string) ($_POST['contact_name'] ?? trim($customer['first_name'].' '.$customer['last_name']))) ?>"></div>
<div class="col-md-6"><label class="form-label" for="contact_phone">Phone</label><input class="form-control" id="contact_phone" name="contact_phone" maxlength="30" required value="<?= e((string) ($_POST['contact_phone'] ?? ($customer['phone'] ?? ''))) ?>"></div>
<div class="col-12"><label class="form-label" for="contact_email">Email</label><input class="form-control" type="email" id="contact_email" name="contact_email" maxlength="190" required value="<?= e((string) ($_POST['contact_email'] ?? $customer['email'])) ?>"></div>
</div></section>

<section class="checkout-card"><div class="checkout-step"><span>2</span><div><span class="eyebrow">FULFILLMENT</span><h2>How are you getting it?</h2></div></div><div class="fulfillment-grid">
<?php if ($pickupEnabled): ?><label class="checkout-choice"><input type="radio" name="fulfillment_type" value="PICKUP" <?= $defaultFulfillment==='PICKUP'?'checked':'' ?>><span><i class="bi bi-shop"></i><strong>Store Pickup</strong><small>No delivery fee · <?= e(shop_setting('store_address','Cebu City, Cebu')) ?></small></span></label><?php endif; ?>
<?php if ($deliveryEnabled): ?><label class="checkout-choice"><input type="radio" name="fulfillment_type" value="DELIVERY" <?= $defaultFulfillment==='DELIVERY'?'checked':'' ?>><span><i class="bi bi-bicycle"></i><strong>Delivery</strong><small><?= money($deliveryFee) ?> delivery fee</small></span></label><?php endif; ?>
</div>
<div data-delivery-section class="delivery-section mt-4">
<?php if ($addresses): ?><div class="mb-4"><div class="option-heading"><strong>Saved addresses</strong><span>Optional</span></div><div class="saved-address-list">
<label class="saved-address"><input type="radio" name="saved_address_id" value="0" <?= (int)($_POST['saved_address_id']??0)===0?'checked':'' ?>><span><strong>Use a new address</strong><small>Enter delivery details below.</small></span></label>
<?php foreach ($addresses as $address): ?><label class="saved-address"><input type="radio" name="saved_address_id" value="<?= (int)$address['id'] ?>" <?= (int)($_POST['saved_address_id']??0)===(int)$address['id']?'checked':'' ?>><span><strong><?= e($address['label']) ?><?= (int)$address['is_default']===1?' · Default':'' ?></strong><small><?= e($address['street'].', '.$address['barangay'].', '.$address['city'].', '.$address['province']) ?></small></span></label><?php endforeach; ?>
</div></div><?php endif; ?>
<div data-new-address-fields><div class="row g-3">
<div class="col-md-4"><label class="form-label" for="address_label">Address label</label><input class="form-control" id="address_label" name="address_label" maxlength="60" value="<?= e((string)($_POST['address_label']??'Home')) ?>"></div>
<div class="col-md-8"><label class="form-label" for="street">Street / house / building</label><input class="form-control" id="street" name="street" maxlength="190" value="<?= e((string)($_POST['street']??'')) ?>"></div>
<div class="col-md-6"><label class="form-label" for="barangay">Barangay</label><input class="form-control" id="barangay" name="barangay" maxlength="120" value="<?= e((string)($_POST['barangay']??'')) ?>"></div>
<div class="col-md-6"><label class="form-label" for="city">City / Municipality</label><input class="form-control" id="city" name="city" maxlength="120" value="<?= e((string)($_POST['city']??'')) ?>"></div>
<div class="col-md-8"><label class="form-label" for="province">Province</label><input class="form-control" id="province" name="province" maxlength="120" value="<?= e((string)($_POST['province']??'Cebu')) ?>"></div>
<div class="col-md-4"><label class="form-label" for="postal_code">Postal code</label><input class="form-control" id="postal_code" name="postal_code" maxlength="12" value="<?= e((string)($_POST['postal_code']??'')) ?>"></div>
<div class="col-md-6"><label class="form-label" for="landmark">Landmark</label><input class="form-control" id="landmark" name="landmark" maxlength="190" value="<?= e((string)($_POST['landmark']??'')) ?>"></div>
<div class="col-md-6"><label class="form-label" for="delivery_notes">Delivery notes</label><input class="form-control" id="delivery_notes" name="delivery_notes" maxlength="255" value="<?= e((string)($_POST['delivery_notes']??'')) ?>"></div>
<div class="col-12"><div class="form-check"><input class="form-check-input" type="checkbox" id="save_address" name="save_address" value="1" <?= !empty($_POST['save_address'])?'checked':'' ?>><label class="form-check-label" for="save_address">Save this address to my account</label></div><div class="form-check mt-2"><input class="form-check-input" type="checkbox" id="set_default_address" name="set_default_address" value="1" <?= !empty($_POST['set_default_address'])?'checked':'' ?>><label class="form-check-label" for="set_default_address">Make it my default address</label></div></div>
</div></div></div></section>

<section class="checkout-card"><div class="checkout-step"><span>3</span><div><span class="eyebrow">PAYMENT</span><h2>Choose a payment method</h2></div></div><div class="payment-methods">
<?php foreach ($methods as $method): ?><label class="payment-choice" data-payment-code="<?= e($method['code']) ?>"><input type="radio" name="payment_method" value="<?= e($method['code']) ?>" <?= ($_POST['payment_method']??'')===$method['code']?'checked':'' ?>><span><strong><?= e($method['name']) ?></strong><?php if ((int)$method['is_online']===1): ?><small>Manual verification required</small><?php else: ?><small>Pay when your order is received</small><?php endif; ?></span></label><?php endforeach; ?>
</div>
<?php foreach ($methods as $method): ?><?php if ((int)$method['is_online']===1): ?><div class="ewallet-panel" data-ewallet-panel="<?= e($method['code']) ?>"><div class="ewallet-receiver"><div><span>Send payment to</span><strong><?= e((string)$method['receiver_name']) ?></strong><button type="button" class="copy-payment" data-copy-text="<?= e((string)$method['receiver_number']) ?>"><i class="bi bi-copy"></i> <?= e((string)$method['receiver_number']) ?></button></div><?php if (!empty($method['qr_path'])): ?><img src="<?= e(url((string)$method['qr_path'])) ?>" alt="<?= e($method['name']) ?> payment QR placeholder"><?php endif; ?></div><p class="form-note">Use the configured receiver details above. The included QR is a demo placeholder for local testing and must be replaced with the shop's real payment QR before production.</p></div><?php endif; ?><?php endforeach; ?>
<div data-online-payment-fields class="online-payment-fields"><div class="row g-3 mt-1"><div class="col-md-6"><label class="form-label" for="reference_number">Reference number</label><input class="form-control" id="reference_number" name="reference_number" maxlength="120" value="<?= e((string)($_POST['reference_number']??'')) ?>"></div><div class="col-md-6"><label class="form-label" for="payment_proof">Proof of payment</label><input class="form-control" type="file" id="payment_proof" name="payment_proof" accept="image/jpeg,image/png,image/webp"><small class="form-text">JPG, PNG or WEBP · maximum 5 MB.</small></div></div></div>
</section>

<section class="checkout-card"><div class="checkout-step"><span>4</span><div><span class="eyebrow">NOTES</span><h2>Anything we should know?</h2></div></div><label class="form-label" for="order_notes">Order notes <span class="text-muted">(optional)</span></label><textarea class="form-control" id="order_notes" name="order_notes" rows="3" maxlength="1000" placeholder="Example: Less ice, call when outside…"><?= e((string)($_POST['order_notes']??'')) ?></textarea></section>
</div>
<div class="col-lg-4"><aside class="cart-summary checkout-summary" data-promo-endpoint="<?= e(url('actions/promo.php')) ?>"><span class="eyebrow">ORDER REVIEW</span><h2><?= count($repriced['items']) ?> item<?= count($repriced['items'])===1?'':'s' ?></h2><div class="checkout-review-items"><?php foreach ($repriced['items'] as $item): ?><div class="checkout-review-item"><div><strong><?= e($item['product_name']) ?> × <?= (int)$item['quantity'] ?></strong><small><?= e(implode(' · ', array_filter([($item['options']['size']['name']??null),($item['options']['temperature']??null),($item['options']['sugar_level']??null)]))) ?></small></div><span><?= money((float)$item['line_total']) ?></span></div><?php endforeach; ?></div><div class="promo-apply-box"><label class="form-label" for="promo_code">Promo code</label><div class="input-group"><input class="form-control text-uppercase" id="promo_code" name="promo_code" maxlength="40" value="<?= e($promoCodePreview) ?>" placeholder="WELCOME10" data-promo-code><button class="btn btn-outline-coffee" type="button" data-promo-apply>Apply</button></div><small class="form-text" data-promo-message><?= $promoCodePreview !== '' ? e((string)($promoPreview['message'] ?? '')) : 'Enter a valid code before placing the order.' ?></small></div><div class="summary-row"><span>Subtotal</span><strong data-checkout-subtotal="<?= e((string)$repriced['subtotal']) ?>"><?= money((float)$repriced['subtotal']) ?></strong></div><div class="summary-row"><span>Discount</span><span data-checkout-discount data-discount="<?= e((string)$promoDiscountPreview) ?>">−<?= money($promoDiscountPreview) ?></span></div><div class="summary-row"><span>Delivery fee</span><span data-checkout-delivery data-delivery-fee="<?= e((string)$deliveryFee) ?>"><?= $defaultFulfillment==='DELIVERY'?money($deliveryFee):money(0) ?></span></div><div class="summary-total"><span>Total</span><strong data-checkout-total><?= money(max(0,(float)$repriced['subtotal']-$promoDiscountPreview)+($defaultFulfillment==='DELIVERY'?$deliveryFee:0)) ?></strong></div><button class="btn btn-coffee btn-lg w-100 mt-3" type="submit"><i class="bi bi-bag-check me-2"></i>Place Order</button><p class="form-note text-center mt-2 mb-0">Prices, promotion eligibility, and totals are calculated again on the server before the order is created.</p></aside></div>
</div></form></div></section></main>
<?php require __DIR__ . '/includes/footer.php'; ?>
