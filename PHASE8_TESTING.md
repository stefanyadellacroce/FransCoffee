# Franscoffee Shop — Phase 8 Testing

## Upgrade from Phase 7

1. Replace the project files in `C:\xampp\htdocs\franscoffee` with the Phase 8 package.
2. Start Apache and MySQL in XAMPP.
3. Open phpMyAdmin and select the existing `franscoffee` database.
4. Import **only** `database/migrations/008_order_management.sql`.
5. Do not re-import `database/franscoffee.sql` on an existing project.

## Staff order queue

Login with:

- Email: `francis.staff@franscoffee.com`
- Password: `Staff123!`

Open:

`http://localhost/franscoffee/staff/orders.php`

Verify that you can filter by order status, source, payment state, and search by order/customer details.

## Online payment review

1. Login as Francis Customer and place a GCash or Maya order with a test proof image.
2. Login as Staff and open Order Management.
3. Open that order.
4. Confirm the payment shows `PENDING` and the proof image is visible.
5. Click **Approve payment**.
6. Verify payment becomes `PAID` and the customer receives a payment-approved notification.
7. The order can now move from `Pending` to `Confirmed`.

Repeat with another order and choose **Reject payment**. The payment should become `FAILED`, and normal forward status progression should be blocked until the payment issue is resolved/cancelled.

## Cash / COP / COD

For an unpaid cash order, use **Mark payment paid** after payment is physically collected. Verify `verified_by`, `verified_at`, and the optional verification note are stored.

## Status workflow

Pickup order:

`PENDING → CONFIRMED → PREPARING → READY → COMPLETED`

Delivery order:

`PENDING → CONFIRMED → PREPARING → READY → OUT_FOR_DELIVERY → COMPLETED`

Cancellation is allowed from Pending, Confirmed, or Preparing.

Each status update must:

- update `orders.status`
- insert one `order_status_history` row
- record the staff user
- record an activity log
- notify a registered customer when in-app notifications are enabled

## Customer verification

Login as:

- `francis.customer@franscoffee.com`
- `Customer123!`

Open `customer/orders.php` and the updated order. Confirm the customer timeline and notifications match staff actions.

## Security checks

- Customer accounts must receive Access denied on `staff/orders.php` and `staff/order-details.php`.
- Invalid CSRF submissions must be rejected.
- Editing the order number in the URL must never grant a customer access to staff pages.
- Invalid status jumps must be rejected server-side even if a form is manually modified.
- A pending GCash/Maya order cannot be confirmed before staff payment approval.
