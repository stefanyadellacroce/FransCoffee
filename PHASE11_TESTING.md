# Franscoffee Shop — Phase 11 Testing

## Upgrade from Phase 10

1. Copy the Phase 11 `franscoffee` folder to `C:\xampp\htdocs\franscoffee`.
2. Start Apache and MySQL in XAMPP.
3. Open phpMyAdmin and select the existing `franscoffee` database.
4. Import **only** `database/migrations/011_finance_promotions.sql`.
5. Do not re-import `database/franscoffee.sql` on an existing installation.

## Admin test account

- Email: `francis.admin@franscoffee.com`
- Password: `Admin123!`

## Payments

Open `/admin/payments.php`.

Verify that payments from online checkout and POS appear in one ledger. Test search and filters by status/method. Open an order from the ledger. A PAID payment can be marked REFUNDED; this records the financial state but intentionally does not silently cancel or rewrite the order.

## Expenses

Open `/admin/expenses.php`.

Create an expense such as:

- Category: Utilities
- Amount: 1250.00
- Date: today
- Description: Internet and shop utilities

Expected: a readable number such as `FCS-EXP-YYYYMMDD-0002`, the amount appears in metrics, and the record can be edited.

## Promotions

Open `/admin/promotions.php`.

Seeded test codes:

- `WELCOME10` — 10% off, minimum ₱200, one use per customer
- `BREW50` — ₱50 off, minimum ₱500, two uses per customer

Create/edit a promotion and test activation/deactivation, dates, total usage limit, per-customer limit, and minimum spend.

## Checkout promotion test

1. Sign in as `francis.customer@franscoffee.com` / `Customer123!`.
2. Put at least ₱200 in the cart.
3. Open `/checkout.php`.
4. Enter `WELCOME10` and click **Apply**.
5. The checkout preview should show the discount.
6. Place the order.
7. Verify `orders.discount_amount` contains the discount and `promotion_usages` contains one row linked to that order.
8. Try `WELCOME10` again with the same customer. Expected: rejected because the seeded per-customer limit is 1.

## Security / integrity checks

- Editing the browser subtotal or displayed discount must not alter the committed amount.
- An inactive/expired/not-yet-started promo must be rejected.
- A promo below minimum spend must be rejected.
- A promo at its usage limit must be rejected.
- The same order cannot consume two promotion usages because `promotion_usages.order_id` is unique.
- Only Admin can open Payments, Expenses, and Promotions management pages.
