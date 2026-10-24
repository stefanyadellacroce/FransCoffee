# Franscoffee Shop — Phase 5 Testing

## Upgrade from Phase 4

1. Replace your project files with this Phase 5 package.
2. Keep Apache and MySQL running in XAMPP.
3. Open phpMyAdmin and select `franscoffee`.
4. Import **only** `database/migrations/005_checkout_payments.sql` once.
5. Do not re-import `database/franscoffee.sql` unless you intentionally want a fresh database.

## Checkout test

1. Open `http://localhost/franscoffee/menu.php` and add at least one item.
2. Open `http://localhost/franscoffee/cart.php` and click **Proceed to checkout**.
3. If signed out, sign in with `francis.customer@franscoffee.com` / `Customer123!`; the cart should remain.
4. Test Store Pickup + Cash on Pickup.
5. Test Delivery + Cash on Delivery. Enter a new Cebu address and choose **Save this address**.
6. Test GCash or Maya using the demo receiver details. Enter a reference number and upload a JPG/PNG/WEBP proof under 5 MB.
7. Click **Place Order**.
8. The confirmation page should display an order number similar to `FCS-ORD-20260917-0001` and the cart should become empty.

## Database verification

Check `orders`, `order_items`, `order_item_options`, `order_status_history`, `payments`, `payment_proofs`, `notifications`, and (if saved) `addresses`.

## Important payment note

The GCash/Maya account numbers and QR images included in Phase 5 are clearly marked **demo values** for local testing. Replace them with the shop's real receiver details before a production deployment. Admin-editable payment settings are scheduled for Phase 12.
