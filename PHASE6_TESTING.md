# Phase 6 — Customer Portal Testing

## 1. Upgrade the database

If Phase 5 is already installed:

1. Start Apache and MySQL in XAMPP.
2. Open `http://localhost/phpmyadmin`.
3. Select the `franscoffee` database.
4. Import `database/migrations/006_customer_portal.sql` once.
5. Do not import the full schema over an existing database.

Expected result: `customer_profiles` now contains `notifications_enabled`, and existing Phase 5 "Order received" notices link to the Phase 6 order-details page.

## 2. Sign in as the customer

Open:

`http://localhost/franscoffee/login.php`

Use:

- Email: `francis.customer@franscoffee.com`
- Password: `Customer123!`

Expected: redirect to `/customer/dashboard.php`.

## 3. Customer Dashboard

Expected:

- Cart, Favorites, Recent Orders, and Unread Notices metrics display.
- If there is a non-completed order, an Active Order card appears.
- Recent Orders links open only the signed-in customer's orders.
- Recommended products appear from the preferred category when one is configured.

If there are no orders yet, create one using Menu → Cart → Checkout first.

## 4. My Orders and filters

Open:

`http://localhost/franscoffee/customer/orders.php`

Test the filters:

- All
- Pending
- Confirmed
- Preparing
- Ready
- Out for delivery
- Completed
- Cancelled

Expected: only matching customer-owned orders appear, with total, payment state, fulfillment, item count, and date/time.

## 5. Order Details / Tracking

Open an order from My Orders.

Expected:

- Order number and total
- Current order status
- Real rows from `order_status_history`
- Ordered items and selected options
- Pickup or delivery information
- Payment method and payment status
- Payment reference where applicable
- Submitted payment-proof thumbnail where applicable

Security check: manually change the `order=` query string to another account's order number. Expected: 404 / Order not found.

## 6. Notifications

Open:

`http://localhost/franscoffee/customer/notifications.php`

Expected:

- New notices display as unread.
- Mark read works for one notice.
- Mark all as read clears the unread count.
- Sidebar/navbar unread badges update after refresh.
- Order notification links open the related order tracking page.

## 7. Profile

Open:

`http://localhost/franscoffee/customer/profile.php`

Test:

- Update first/last name and phone.
- Choose a favorite product category.
- Toggle in-app notifications.
- Upload JPG, PNG, or WEBP profile photo under 3 MB.

Expected:

- Saved data appears after refresh.
- Updated name appears in the session/sidebar immediately.
- Uploaded profile path is stored in `customer_profiles.avatar_path`.
- Unsupported or oversized uploads are rejected.

Notification preference check: turn notifications off, create a new order, and verify that no new "Order received" notification is inserted for that customer. My Orders should still show the order normally.

## 8. Change Password

On Profile, use the Security section.

Expected:

- Wrong current password is rejected.
- Weak new password is rejected.
- Valid password change succeeds using `password_hash()`.
- The new password works on the next login.

If you change the Francis test password, remember that the original test credential will no longer work unless you reset it.

## 9. Saved Addresses

Open:

`http://localhost/franscoffee/customer/addresses.php`

Test:

- Add an address.
- Add a second address.
- Set one as default.
- Edit an address.
- Delete a non-default address.
- Delete the default address.

Expected:

- Only addresses owned by the signed-in customer can be changed.
- Exactly one remaining address becomes default automatically when needed.
- Saved addresses appear during Delivery checkout.
- Old placed orders keep their delivery snapshot even if a saved address is later changed or deleted.

## 10. RBAC / direct URL tests

Sign out and directly visit:

- `/customer/orders.php`
- `/customer/profile.php`
- `/customer/addresses.php`

Expected: redirect to login.

Sign in as Admin or Staff and try the same customer URLs.

Expected: Access denied.

## 11. Regression tests

Confirm the earlier phases still work:

- Public Home/Menu/Product pages
- Login/Register/Logout
- Favorites
- Cart quantity/update/remove
- Product customizations
- Pickup checkout
- Delivery checkout
- GCash/Maya proof upload
- Order confirmation

## Database tables used by Phase 6

- `users`
- `customer_profiles`
- `addresses`
- `orders`
- `order_items`
- `order_item_options`
- `order_status_history`
- `payments`
- `payment_methods`
- `payment_proofs`
- `notifications`
- `favorites`
- `carts`
- `cart_items`
- `activity_logs`
