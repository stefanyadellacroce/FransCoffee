# Franscoffee Shop — Phase 10 Testing

Phase 10 adds Inventory and Supplier Management. It is designed for an existing Phase 9 installation and does not require deleting prior orders, products, customers, or payments.

## 1. Upgrade an existing Phase 9 database

1. Copy the Phase 10 `franscoffee` folder into `C:\xampp\htdocs\franscoffee` and replace the older application files.
2. Start **Apache** and **MySQL** in XAMPP.
3. Open `http://localhost/phpmyadmin`.
4. Select the existing `franscoffee` database.
5. Import **only** `database/migrations/010_inventory_suppliers.sql`.
6. Do not re-import `database/franscoffee.sql` unless you are intentionally doing a fresh installation.

The migration adds supplier product notes, a per-transaction resulting balance, useful inventory indexes, three sample suppliers, and eight starter inventory items.

## 2. Login

Use the Admin test account:

- Email: `francis.admin@franscoffee.com`
- Password: `Admin123!`

Open `http://localhost/franscoffee/admin/inventory.php`.

## 3. Expected seed stock

You should see items such as:

- Espresso Coffee Beans — 12 kg / reorder 5 kg
- Fresh Milk — 18 L / reorder 8 L
- Oat Milk — 5 L / reorder 6 L (**LOW**)
- Almond Milk — 4 L / reorder 5 L (**LOW**)
- 16oz Cold Cups — 120 pcs / reorder 100 pcs
- 16oz Cup Lids — 85 pcs / reorder 100 pcs (**LOW**)

The Inventory sidebar badge should show the number of active low-stock items.

## 4. Stock In test

1. Open **Espresso Coffee Beans** → Manage.
2. Choose **Stock In**.
3. Enter quantity `3`.
4. Optional unit cost: `630`.
5. Notes: `Weekly bean delivery`.
6. Record transaction.

Expected: balance changes from 12 kg to 15 kg and the transaction appears in Recent Activity and Transaction History.

## 5. Stock Out test

1. Record **Stock Out** of `2.5` kg.
2. Expected new balance: 12.5 kg.
3. Attempt to stock out more than the available quantity.

Expected: the invalid transaction is rejected and quantity never goes negative.

## 6. Waste test

1. Open Fresh Milk.
2. Record **Waste** of `1` L with note `Expired carton`.

Expected: the quantity decreases by 1 L and the transaction type is WASTE.

## 7. Physical count adjustment

1. On the same item choose **Physical Count Adjustment**.
2. Enter the actual physical stock count.

Expected: Franscoffee sets the balance to the physical count and stores an ADJUSTMENT transaction. Direct on-hand quantity editing is intentionally unavailable for existing items.

## 8. Low-stock alert test

Use an item currently above its reorder level, such as Cold Cups:

1. Current example: 120 pcs; reorder level: 100 pcs.
2. Record Stock Out of 25 pcs.
3. New balance becomes 95 pcs.

Expected:

- Item is marked LOW.
- Inventory sidebar low-stock badge increases.
- Admin dashboard stock-health count updates.
- A low-stock notification record is inserted for active Admin users.

The alert is created when stock crosses from above the reorder level to at/below it; repeated transactions while it is already low do not spam duplicate crossing alerts.

## 9. Supplier tests

Open `http://localhost/franscoffee/admin/suppliers.php`.

Test:

- Create a supplier.
- Edit contact information.
- Set Products Supplied.
- Deactivate the supplier.
- Confirm inventory items linked to it are preserved.
- Reactivate it.

## 10. Create a new stock item

Suggested test:

- Item code: leave the generated code or use `FCS-INV-0100`
- Item name: Vanilla Syrup
- Category: Syrups
- Unit: bottle
- Opening quantity: 10
- Reorder level: 3
- Unit cost: 280
- Primary supplier: optional

Expected: the item is created and the opening quantity also appears as an opening STOCK_IN transaction.

## 11. Access-control test

Login as Customer or Staff and manually browse to `/admin/inventory.php` or `/admin/suppliers.php`.

Expected: Access denied. Phase 10 inventory administration is Admin-only.

## 12. Regression tests

Confirm these earlier flows still work after the migration:

- Customer login and registration
- Menu and product details
- Favorites and cart
- Checkout and order placement
- Customer order tracking
- Staff POS
- Staff order management/payment review
- Admin product/category management

Phase 10 does not automatically deduct ingredients from recipes when a drink is sold; recipe/BOM consumption was not part of the Phase 10 specification. Stock Out is recorded explicitly for now.
