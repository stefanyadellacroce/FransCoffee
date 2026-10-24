# Franscoffee Shop — Phase 4 Testing

Phase 4 adds database-backed Favorites, guest/customer Cart, quantity controls, drink customizations, server-calculated item pricing, and guest-cart merge after customer login.

## Upgrade from Phase 3

1. Copy the Phase 4 project files over your existing `C:\xampp\htdocs\franscoffee` folder.
2. Start Apache and MySQL in XAMPP.
3. Open phpMyAdmin and select the existing `franscoffee` database.
4. Import only `database/migrations/004_cart_favorites.sql`.
5. Do **not** re-import the full `database/franscoffee.sql` when upgrading an existing database.

## Test 1 — Guest cart

1. Sign out.
2. Open `http://localhost/franscoffee/menu.php`.
3. Open Spanish Latte, choose Medium, Iced, 50% sugar, Oat Milk, and Caramel.
4. Set quantity to 2 and click **Add to cart**.
5. Open the bag icon in the navbar.
6. Confirm the customizations, quantity, unit price, and line total are present.

Expected: the cart works before login and survives page navigation.

## Test 2 — Server-side price calculation

On Spanish Latte, select Medium (+₱20), Oat Milk (+₱30), and Caramel (+₱25).

Base ₱165 + ₱20 + ₱30 + ₱25 = **₱240 per item**.

Expected: cart unit price is ₱240.00. The server calculates this from database choices; the browser-submitted total is not trusted.

## Test 3 — Quantity update and removal

1. In Cart, change quantity and click Update.
2. Confirm subtotal changes.
3. Remove one item.
4. Use Clear cart and confirm.

Expected: only items belonging to the current cart can be modified.

## Test 4 — Guest cart merge after login

1. Sign out and add a product to the cart.
2. Sign in as `francis.customer@franscoffee.com` / `Customer123!`.
3. Open Cart.

Expected: the guest item is merged into Francis Customer's database cart rather than being lost.

## Test 5 — Favorites

1. Sign in as Francis Customer.
2. Open Menu and click a heart icon.
3. Open `http://localhost/franscoffee/customer/favorites.php`.
4. Confirm the product appears.
5. Use **Quick add** to put the default version into Cart.
6. Remove it from Favorites.

Expected: favorites are stored in the `favorites` table and are limited to CUSTOMER accounts.

## Test 6 — Favorite while signed out

1. Sign out.
2. Click a heart on a menu product.
3. Sign in as Francis Customer.

Expected: you are asked to sign in, then returned to the intended storefront page after successful customer login.

## Intentional Phase boundary

Checkout remains disabled on the Cart summary. Pickup, delivery, addresses, GCash/Maya receiver details, proof upload, and order creation are implemented in Phase 5.
