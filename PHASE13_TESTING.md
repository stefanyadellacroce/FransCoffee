# Franscoffee Shop — Phase 13 Reports Testing

## Upgrade from Phase 12

1. Copy the Phase 13 `franscoffee` folder to `C:\xampp\htdocs\franscoffee` and replace the older project files.
2. Start Apache and MySQL in XAMPP.
3. Open phpMyAdmin and select the existing `franscoffee` database.
4. Import **only** `database/migrations/013_reports.sql`.
5. Do not import the full `database/franscoffee.sql` over an existing database unless you intentionally want a fresh reset.

## Login

- Email: `francis.admin@franscoffee.com`
- Password: `Admin123!`

Open `http://localhost/franscoffee/admin/reports.php`.

## Test checklist

### 1. Reports navigation
Confirm the Admin sidebar **Reports** link is enabled. Switch between Sales, Orders, Inventory, Payments, Products, and Expenses.

### 2. Date filtering
Choose a From and To date, click **Apply dates**, and confirm the period label and data change. Entering the dates in reverse order should still resolve to a valid chronological range.

### 3. Sales report
Create/verify a paid order and record an expense in the same date range. Confirm Paid Revenue, Average Sale, Expenses, and Net After Expenses update. The sales report includes only payments whose status is `PAID`.

### 4. Orders report
Place online and POS orders. Confirm totals, source counts, pickup/delivery counts, status breakdown, and order table reflect those records.

### 5. Inventory report
Create a STOCK_IN and WASTE transaction. Confirm the current inventory snapshot, low-stock count, stock value, and movement ledger update.

### 6. Payments report
Test a PAID, PENDING/UNPAID, FAILED, or REFUNDED payment if available. Confirm status totals, method breakdown, and payment ledger match Admin > Payments.

### 7. Products report
Complete orders containing multiple products. Confirm quantity sold, line revenue, order counts, and best-seller bars update. Product report revenue is item-line revenue before order-level promo/discount allocation; use Sales Report for actual collected paid revenue.

### 8. Expenses report
Record expenses in multiple categories. Confirm total, average, category distribution, and ledger match Admin > Expenses.

### 9. Printing
Click **Print report**. Print Preview should hide the sidebar/filter controls and show the Franscoffee report header, report tables, selected date range, and footer. Landscape A4 is used for wide operational tables.

### 10. Access control
Log in as Staff or Customer and manually open `/admin/reports.php`. Access must be denied/redirected by Admin role protection.

## Expected limitations

- Reports are live database reports, not cached snapshots.
- Product report line revenue is based on `order_items.line_total`; order-level discounts are reflected in Sales/Orders, not allocated across product rows.
- Current Inventory Snapshot is current-state data; its movement ledger uses the selected date range.
