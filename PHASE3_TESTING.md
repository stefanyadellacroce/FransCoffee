# Phase 3 Testing — Franscoffee Shop

## 1. Upgrade from Phase 2

1. Stop editing your current project and make a quick backup of `C:\xampp\htdocs\franscoffee`.
2. Copy the Phase 3 `franscoffee` files over your existing Phase 2 folder.
3. Start Apache and MySQL in XAMPP.
4. Open phpMyAdmin.
5. Select the `franscoffee` database.
6. Import `database/migrations/003_public_storefront.sql`.
7. Do not re-import the full `franscoffee.sql` when preserving existing data.

Expected: `contact_messages` is created and 14 public menu products appear.

## 2. Home page

Open:

`http://localhost/franscoffee/`

Check:

- Hero and approved Franscoffee branding render.
- Category chips open filtered menu pages.
- Featured products come from MySQL.
- `Explore menu`, `Order now`, `Our story`, and `Contact us` navigate correctly.
- Public footer appears.

## 3. Menu

Open:

`http://localhost/franscoffee/menu.php`

Check:

- All products display after migration.
- Category filters work.
- Search `latte` and confirm matching products are returned.
- Clear returns to the full menu.
- Empty searches show a usable empty state rather than a blank grid.

## 4. Product Details

Open:

`http://localhost/franscoffee/product.php?slug=spanish-latte`

Check:

- Product name, category, description, price, prep time, and image load from MySQL.
- Small / Medium / Large are shown.
- Selecting Medium or Large updates the estimated price.
- Selecting add-ons increases the estimated price.
- The Add to Cart button is deliberately disabled until Phase 4.

Also test an invalid URL:

`http://localhost/franscoffee/product.php?slug=not-a-real-product`

Expected: friendly 404 product state.

## 5. About

Open:

`http://localhost/franscoffee/about.php`

Check desktop and mobile layouts, story content, value cards, navigation, and footer.

## 6. Contact form

Open:

`http://localhost/franscoffee/contact.php`

Submit a valid message.

Expected:

- Success message appears after redirect.
- phpMyAdmin → `contact_messages` contains a row with status `NEW`.
- Empty/invalid fields show validation feedback.
- Form has CSRF protection.

## 7. Authentication regression

Verify Phase 2 still works:

- `francis.admin@franscoffee.com` / `Admin123!`
- `francis.staff@franscoffee.com` / `Staff123!`
- `francis.customer@franscoffee.com` / `Customer123!`

Check that each role still lands on its own dashboard and Sign out still works.

## 8. Responsive test

Use browser responsive mode around:

- 1440px desktop
- 1024px laptop/tablet
- 768px tablet
- 390px mobile

Check navbar collapse, product grids, hero, menu filters, product options, contact form, and footer.

## Common errors

### `Table 'franscoffee.contact_messages' doesn't exist`
Run `database/migrations/003_public_storefront.sql` against the existing Phase 2 database.

### Home/Menu show no products
The Phase 3 migration was not imported, or the `products` seed statements did not run. Check `products` in phpMyAdmin.

### Database connection error
Confirm XAMPP MySQL is running and `config.php` uses your correct host, database, username, and password.

### Styling or SVG images missing
Confirm the complete `assets/` directory from Phase 3 was copied into `C:\xampp\htdocs\franscoffee\assets` and hard-refresh the browser with `Ctrl+F5`.
