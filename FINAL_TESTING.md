# Franscoffee Shop — Final End-to-End Testing Checklist

Run these tests after importing `database/franscoffee.sql` into a clean XAMPP MySQL/MariaDB database, or after upgrading an existing Phase 13 installation with the Phase 14 files.

## 1. Environment and database

1. Start Apache and MySQL.
2. Open `http://localhost/franscoffee/`.
3. Confirm the public Home page loads without PHP/MySQL errors.
4. In phpMyAdmin, confirm the `franscoffee` database and the core tables exist.
5. Confirm the seeded Admin, Staff, and Customer accounts exist.

Expected: Home/Menu load, product seed data appears, and no database error is displayed.

## 2. Authentication and RBAC

Test all three seeded accounts.

- Admin must land on `/admin/dashboard.php`.
- Staff must land on `/staff/dashboard.php`.
- Customer must land on `/customer/dashboard.php`.
- Customer opening `/admin/users.php` must receive Access denied.
- Staff opening `/admin/settings.php` must receive Access denied.
- Admin/Staff opening Customer-only pages must be denied.

Deactivate a secondary test user as Admin while that account is signed in in another browser/private window, then refresh the second session.

Expected: the stale session is invalidated and redirected to sign-in.

## 3. Registration and password recovery

1. Register a new Customer with a unique Francis-based test email.
2. Confirm duplicate email registration is rejected.
3. Use Forgot Password for the account.
4. In local debug mode, use the temporary reset link.
5. Set a strong new password and confirm the token cannot be reused.

## 4. Storefront / products

1. Open Menu.
2. Search `latte`.
3. Filter categories.
4. Open Spanish Latte.
5. Change size, temperature, sugar, milk, add-ons, and quantity.
6. Confirm the displayed preview changes.

Expected: only active products/categories/options are offered.

## 5. Favorites and cart

1. While logged out, add products to the cart.
2. Sign in as the Customer.
3. Confirm the guest cart merges into the Customer cart.
4. Add/remove Favorites.
5. Increase/decrease cart quantities.
6. Remove one item and clear the cart.

Expected: cart/favorite state persists correctly and ownership is enforced.

## 6. Checkout and payments

### Pickup / Cash on Pickup
1. Add products and go to Checkout.
2. Choose Store Pickup + Cash on Pickup.
3. Place order.

### Delivery / Cash on Delivery
1. Choose Delivery.
2. Enter and optionally save an address.
3. Choose COD and place order.

### GCash / Maya
1. Choose GCash or Maya.
2. Confirm receiver name/number/QR are shown.
3. Enter a reference number.
4. Upload a JPG/PNG/WEBP payment proof under 5 MB.
5. Place order.

Negative upload test: try a `.php`, `.svg`, `.html`, or oversized image renamed as an image.

Expected: invalid/active-content files are rejected; valid proofs create a PENDING payment.

## 7. Promo codes

Test `WELCOME10` and `BREW50` with valid/invalid subtotals. Test an expired/inactive promo from Admin after changing its settings. Test the same Customer beyond its per-customer usage limit.

Expected: checkout preview and final committed order use the same server-validated discount.

## 8. Customer portal

1. Open My Orders.
2. Open an order detail page and verify item customizations, totals, payment, fulfillment, and timeline.
3. Try changing the order number in the URL to another Customer's order.
4. Open Notifications and mark individual/all notifications read.
5. Edit Profile and upload a valid avatar.
6. Change password with correct and incorrect current password.
7. Add/edit/delete/set-default Saved Addresses.

Expected: ownership checks prevent cross-customer access.

## 9. Staff POS

Login as `francis.staff@franscoffee.com`.

1. Open POS.
2. Add customized products.
3. Complete a walk-in Cash order and verify change.
4. Print receipt.
5. Search/select `francis.customer@franscoffee.com` and complete another sale.
6. Confirm the registered Customer sees that POS order in My Orders.
7. Test GCash/Maya POS reference-number requirements.

## 10. Staff customer directory

Open `/staff/customers.php`.

1. Search by name, email, and phone.
2. Filter Active/Inactive.
3. Confirm order count, paid total, and last-order date are shown.
4. Use **View orders**.

Expected: Staff has read-only customer visibility and no customer-account edit controls.

## 11. Order management

Create an online GCash/Maya order, then as Staff/Admin:

1. Open Order Management.
2. Review payment proof.
3. Approve payment.
4. Move Pickup order: PENDING → CONFIRMED → PREPARING → READY → COMPLETED.
5. Move Delivery order through OUT FOR DELIVERY.
6. Reject another online payment and confirm normal forward processing is blocked.
7. Mark COP/COD payment PAID after payment is received.

Expected: invalid transitions are unavailable/rejected; history and customer notifications update.

## 12. Admin catalog, inventory, suppliers

1. Create/edit/archive/restore a product.
2. Upload JPG/PNG/WEBP product image.
3. Add/disable sizes and add-ons.
4. Create/edit/disable a category.
5. Create/edit supplier.
6. Create inventory item.
7. Record STOCK_IN, STOCK_OUT, WASTE, and ADJUSTMENT.
8. Attempt stock-out greater than current quantity.
9. Cross a reorder threshold and confirm LOW STOCK indicators update.

## 13. Payments, expenses, promotions, users, settings

1. Filter payment ledger and refund an eligible paid payment.
2. Add/edit an Expense and verify readable expense number.
3. Create/disable Promotion.
4. Create Staff/Customer/Admin accounts.
5. Confirm the current Admin cannot deactivate/demote themselves.
6. Confirm the last active Admin cannot be removed from Admin access.
7. Review Activity Logs.
8. Change store address/business hours/delivery fee.
9. Replace GCash/Maya receiver details and QR.
10. Verify checkout/storefront reflect the new settings after reload.

## 14. Reports

Open `/admin/reports.php` and test all report tabs:

- Sales
- Orders
- Inventory
- Payments
- Products
- Expenses

Change From/To dates and compare sample rows with source tables in phpMyAdmin. Open browser Print Preview.

Expected: navigation/sidebar/filter controls do not appear in the printed report layout.

## 15. Responsive/mobile test

At widths around 375 px, 768 px, and 1024 px:

- Public navbar must remain usable.
- Customer/Staff/Admin portal sidebar must become the mobile offcanvas menu below the large breakpoint.
- Open/close the offcanvas and navigate several pages.
- Cart, Checkout, POS, tables, forms, and Reports must remain usable without horizontal page-level overflow.

## 16. Final security checks

- Submit a POST form after manually deleting/changing `_csrf`: request must fail with security validation.
- Try Admin/Staff/Customer URLs with the wrong role.
- Try changing hidden product price/total fields in browser DevTools: committed totals must still come from server-side calculations.
- Try another Customer's address/order IDs: ownership checks must reject access.
- Confirm upload directories do not list contents and do not execute active-content extensions.

If all checks pass in XAMPP, the project is ready for local demonstration. Complete `FINAL_DEPLOYMENT_CHECKLIST.md` before putting the application online.
