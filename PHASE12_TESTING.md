# Franscoffee Shop — Phase 12 Testing

## Upgrade from Phase 11
1. Back up the `franscoffee` database.
2. Replace the project files in `C:\xampp\htdocs\franscoffee` with the Phase 12 package.
3. Start Apache and MySQL in XAMPP.
4. Open phpMyAdmin, select `franscoffee`, and import only `database/migrations/012_admin_management.sql`.
5. Do not import the full `database/franscoffee.sql` over an existing Phase 11 database.

## Admin login
- Email: `francis.admin@franscoffee.com`
- Password: `Admin123!`
- Dashboard: `http://localhost/franscoffee/admin/dashboard.php`

## Users
Open `http://localhost/franscoffee/admin/users.php`.

Test creating a staff user:
- First name: Francis
- Last name: Cashier Two
- Email: `francis.cashier2@franscoffee.com`
- Role: Staff / Cashier
- Employee code: leave blank (auto-generated)
- Job title: Cashier
- Password: `Staff123!`

Expected:
- User appears in Users.
- A `staff_profiles` row is created.
- Employee code is generated as `FCS-STF-####`.
- New account can sign in to the Staff portal.

Safety checks:
- Try editing your currently signed-in admin and selecting INACTIVE: the save must be rejected.
- If Francis Admin is the only active administrator, editing it to Staff/Customer or Inactive must be rejected.
- Create a second active Admin, then role changes on the first Admin become possible as long as one active Admin remains.

## Activity Logs
Open `http://localhost/franscoffee/admin/activity-logs.php`.
Test filters for CREATE_USER, UPDATE_USER, LOGIN, orders, payments, inventory, and date ranges.
Expected: newest matching entries display with user, action, entity, IP, timestamp, and JSON detail when available.

## Settings
Open `http://localhost/franscoffee/admin/settings.php`.
Test:
- Shop name/tagline/contact information
- Store address and business hours
- Pickup and Delivery toggles
- Minimum order and delivery fee
- Custom logo upload (JPG/PNG/WEBP <= 5 MB)
- GCash/Maya receiver name and number
- GCash/Maya QR upload

Expected:
- Public navbar/footer use the updated shop name/logo.
- Home, Contact, Checkout, receipts, and footer reflect updated address/contact/business hours where applicable.
- Checkout hides a fulfillment method when it is disabled.
- GCash/Maya receiver details update on Checkout/POS immediately.

## Dashboard
Open Admin Dashboard and confirm:
- Paid sales today
- Orders today / pending orders
- Customer count / new customers
- Low-stock count
- Seven-day paid-sales overview
- Top products
- Recent orders
- Quick links

## File upload security
Only JPG, PNG, and WEBP should be accepted for Settings logo/QR uploads. PHP/script/SVG uploads through the Settings forms must be rejected.

## Final regression
Test existing flows after Phase 12:
1. Customer sign-in and storefront
2. Product customization and cart
3. Checkout with pickup/delivery
4. GCash/Maya payment details
5. Customer order history
6. Staff POS
7. Staff order management
8. Product/category management
9. Inventory/supplier management
10. Payments/expenses/promotions
