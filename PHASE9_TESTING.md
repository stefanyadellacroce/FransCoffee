# Franscoffee Shop — Phase 9 Testing Guide

## 1. Upgrade from Phase 8

1. Stop editing the old project while copying files.
2. Replace/copy the Phase 9 project files into `C:\xampp\htdocs\franscoffee`.
3. Start Apache and MySQL in XAMPP.
4. Open phpMyAdmin, select the existing `franscoffee` database, and import only `database/migrations/009_catalog_management.sql`.
5. Do not import the full schema unless you want to erase/reseed the project.

## 2. Login and access control

Login at `http://localhost/franscoffee/login.php` with:

- Admin: `francis.admin@franscoffee.com` / `Admin123!`

Open `http://localhost/franscoffee/admin/products.php`. Confirm an Admin can access it. Log in as Staff or Customer and manually try the same URL; the request should be denied by RBAC.

## 3. Categories

Open `admin/categories.php`.

- Create `Seasonal Drinks` with sort order 9.
- Edit it to `Seasonal Specials`.
- Disable it and confirm it displays DISABLED.
- Re-enable it.
- Try creating a duplicate category name and confirm validation blocks it.

Disabling a category containing products should hide those products from the public menu because storefront queries require both product and category to be active. Existing orders remain unchanged.

## 4. Create a product

Open `admin/product-form.php` and create:

- Name: `Francis Signature Latte`
- Category: Hot Coffee
- Description: `Espresso and creamy milk with a smooth caramel finish.`
- Base price: `170.00`
- Preparation time: `8`
- Available: checked
- Featured: checked
- Image: a JPG/PNG/WEBP smaller than 5 MB

After saving, the page should redirect to the edit screen and unlock Sizes and Add-ons.

## 5. Image security

Try a valid JPG/PNG/WEBP. It should save into `uploads/products/` using a randomized filename. Try an SVG, PHP file renamed as an image, or file larger than 5 MB; it should be rejected.

Use Remove image and confirm the public product falls back to the placeholder. Seed SVG files are trusted application assets and are not part of the upload path.

## 6. Sizes

Create:

- Small / +₱0 / sort 10
- Medium / +₱20 / sort 20
- Large / +₱40 / sort 30

Edit Medium to +₱25 and confirm the new amount appears on the public product page and POS. Disable Large and confirm it no longer appears as a selectable size, while historical order snapshots remain intact.

## 7. Add-ons

Create:

- Extra Espresso Shot / +₱35
- Oat Milk / +₱30
- Caramel / +₱25

Change their sort order and verify the storefront and POS use the new order. Disable one add-on and confirm it cannot be selected for new cart/POS lines.

## 8. Product list quick actions

From `admin/products.php`:

- Toggle Featured and confirm the star/status changes.
- Archive `Francis Signature Latte`; it should disappear from public Menu and POS.
- Restore it; it should appear again.
- Filter by category/status and search by product name.

## 9. Storefront regression

Test:

- `menu.php`
- `product.php?slug=spanish-latte`
- Add a customized item to Cart
- Checkout a normal customer order

Product pricing must continue to be recalculated from current database options on the backend.

## 10. POS regression

Login as Staff: `francis.staff@franscoffee.com` / `Staff123!`

Open `staff/pos.php`. Confirm active Admin-managed products, sizes, and add-ons appear. Complete one cash sale and print the receipt.

## 11. Database checks

Inspect:

- `categories`
- `products`
- `product_sizes`
- `product_addons`
- `activity_logs`

`product_sizes` and `product_addons` should now include `sort_order`, `created_at`, and `updated_at`. Product/category changes should create activity-log entries.

## Expected Phase 9 result

Admin can maintain the complete sellable catalog without direct phpMyAdmin edits. Changes flow into the public Menu/Product pages and Staff POS while archived/disabled items remain preserved for historical data integrity.
