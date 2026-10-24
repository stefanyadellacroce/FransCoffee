# Franscoffee Shop — Final Build (Phase 14)

Franscoffee Shop is a complete PHP 8+ / MySQL-MariaDB coffee-shop ordering and operations web application with a warm, minimal interface based on the Franscoffee brand.

**Built by Stefanya Marie Dela Cruz.**

## Included modules

### Public / Customer
- Responsive storefront, menu search, categories, product details
- Customer registration/login/logout/password-reset foundation
- Favorites and persistent guest/customer cart
- Drink sizes, temperature, sugar, milk, add-ons, and server-side repricing
- Pickup/delivery checkout, saved addresses, GCash/Maya/manual proof upload
- Promo-code validation and usage tracking
- Customer dashboard, My Orders, order tracking, notifications, profile, addresses

### Staff / Cashier
- POS with walk-in or registered customer selection
- Product search/categories/customization, discounts, Cash/GCash/Maya
- Cash received/change calculation and printable receipts
- Order queue, secure order details, payment review, controlled status transitions
- Read-only customer directory with customer search and order summaries

### Admin
- Operational dashboard
- Categories and product CRUD, availability, featured items, secure image uploads
- Sizes and add-ons
- Inventory, stock-in/out/waste/adjustments, low-stock alerts
- Suppliers
- Payments, refunds, expenses, promotions
- Users, role/status controls, activity logs
- Store/payment/order settings
- Sales, Orders, Inventory, Payments, Products, and Expenses reports with print layouts

## Final security and reliability polish

- PDO prepared statements and server-side validation
- CSRF protection on detected POST forms
- Session ID regeneration on login and stricter PHP session handling
- Authenticated sessions are revalidated against current account role/status each request
- RBAC on every Admin, Staff, and Customer portal page
- Server-side cart/checkout/POS pricing
- Server-side promotion validation
- Upload MIME/type/size checks and randomized filenames
- Apache upload-folder rules block executable/active-content file types
- Security response headers (`nosniff`, `SAMEORIGIN`, referrer and permissions policy)
- Responsive portal offcanvas navigation on mobile

## Local XAMPP setup

1. Extract/copy the `franscoffee` folder to:
   `C:\xampp\htdocs\franscoffee`
2. Start **Apache** and **MySQL** in XAMPP.
3. Open phpMyAdmin: `http://localhost/phpmyadmin`
4. For a clean install, import:
   `database/franscoffee.sql`
5. Open:
   `http://localhost/franscoffee/`

### Upgrade from Phase 13

Phase 14 has **no database schema changes**. Replace the Phase 13 project files with this final package. `database/migrations/014_final_polish.sql` is an optional no-op marker and does not modify the schema.

## Test accounts

- Admin: `francis.admin@franscoffee.com` / `Admin123!`
- Staff: `francis.staff@franscoffee.com` / `Staff123!`
- Customer: `francis.customer@franscoffee.com` / `Customer123!`

## Important local-development note

`config.php` is configured for the default XAMPP MySQL account (`root` with an empty password), `http://localhost/franscoffee`, and debug mode. Before online deployment, follow `FINAL_DEPLOYMENT_CHECKLIST.md`.

## Final verification performed in the build environment

- PHP syntax lint across all PHP files
- JavaScript syntax validation
- Static internal PHP route validation
- CSRF marker audit for detected POST forms
- Portal RBAC presence audit
- Foreign-key referenced-table consistency scan
- Seeded Francis password-hash verification
- ZIP integrity verification

The database smoke check has been run against a fresh MariaDB instance. Complete browser flows still need the manual checks in `FINAL_TESTING.md`.

### Database smoke check

After importing `database/franscoffee.sql` into a separate local MariaDB instance, set `FRANSCOFFEE_TEST_DB_PORT` to that instance's port and run `C:\xampp\php\php.exe tests\smoke.php` from the project directory. The check covers searchable lists, customer registration, and the cart/checkout quantity limit. It refuses the default port 3306 and removes the customer account it creates.
