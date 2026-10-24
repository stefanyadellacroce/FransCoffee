# Franscoffee Shop — Phase 7 Staff POS Testing

Phase 7 adds the operational counter Point of Sale on top of the Phase 6 customer portal.

## 1. Upgrade an existing Phase 6 installation

1. Stop using the app while files are being replaced.
2. Copy the Phase 7 `franscoffee` folder contents over `C:\xampp\htdocs\franscoffee`.
3. Start **Apache** and **MySQL** in XAMPP.
4. Open `http://localhost/phpmyadmin`.
5. Select the existing `franscoffee` database.
6. Import **only** `database/migrations/007_staff_pos.sql` once.
7. Do not re-import `database/franscoffee.sql` unless you intentionally want a clean database.

The migration adds `orders.created_by`, `payments.cash_received`, `payments.change_due`, and enables the `CASH` payment method for POS.

## 2. Staff login

Open:

`http://localhost/franscoffee/login.php`

Use:

- Email: `francis.staff@franscoffee.com`
- Password: `Staff123!`

After login open:

`http://localhost/franscoffee/staff/pos.php`

Admin can also open the same POS page using the Francis Admin account.

## 3. Basic cash sale

1. Search for `Spanish Latte`.
2. Open the product.
3. Choose Medium, Iced, 50% sugar, Oat Milk, and Caramel.
4. Quantity: 2.
5. Add it to the current order.
6. Add a Butter Croissant.
7. Leave Customer as **Walk-in Customer**.
8. Payment: **Cash**.
9. Enter cash greater than or equal to the total.
10. Confirm the displayed change.
11. Click **Complete sale**.

Expected result:

- A POS order number such as `FCS-POS-20260917-0001` is created.
- Payment status is `PAID`.
- Order status is `CONFIRMED` so Phase 8 can move it through preparation.
- The receipt page opens automatically.
- The receipt shows cashier, products/options, discount if any, cash received, and change.
- The browser Print button produces a receipt-friendly print layout.

## 4. Registered customer sale

In POS, search for:

`francis.customer@franscoffee.com`

Select **Francis Customer**, add products, and complete the sale.

Then sign in as:

- Email: `francis.customer@franscoffee.com`
- Password: `Customer123!`

Open `customer/orders.php`.

Expected result: the POS order appears in the same customer order history because it is attached to the customer account. A notification is also created when that customer's in-app notifications are enabled.

## 5. GCash / Maya

1. Build an order.
2. Select **GCash** or **Maya**.
3. The configured receiver account appears in the POS payment section.
4. Enter the confirmed transaction reference number.
5. Complete the sale.

Expected result: payment is recorded as `PAID`, the reference number is stored, and the cashier is recorded as the verifier. No customer proof upload is required at the counter because the cashier is directly confirming receipt of payment.

## 6. Discount tests

Test both:

- Percent: 10%
- Fixed amount: 50

Expected result: the browser preview updates, but the server recalculates the discount again before saving. Percent discounts cannot exceed 100%, and a fixed discount cannot reduce the total below zero.

## 7. Database checks

In phpMyAdmin inspect:

- `orders` — `order_source=POS`, `created_by` points to the cashier, `discount_amount` is stored.
- `order_items` — server-calculated product prices and quantities.
- `order_item_options` — size, temperature, sugar, milk, and add-ons.
- `payments` — method, `PAID`, cash received/change or e-wallet reference, verifier.
- `order_status_history` — initial `CONFIRMED` entry made by the cashier.
- `activity_logs` — `POS_SALE` event.
- `notifications` — registered-customer POS notification when enabled.

## 8. Security / validation tests

- Try opening `staff/pos.php` while logged out: it must redirect to login.
- Try opening it as a Customer: access must be denied.
- Change a price in browser developer tools: the stored price must still come from the database.
- Submit cash below the total: the server must reject the sale.
- Submit GCash/Maya without a reference number: the server must reject the sale.
- Change `customer_id` to another role's user ID: the server must reject it as a customer selection.
- Re-submit with a bad CSRF token: the request must be rejected.

## 9. Phase boundary

Phase 7 intentionally creates POS orders as `CONFIRMED` after successful payment. The full operational order queue and status actions (Preparing, Ready, Completed, payment review for online orders) are Phase 8.
