-- Franscoffee Shop — Phase 10: Inventory & Suppliers
-- Import this only when upgrading an existing Phase 9 database.

ALTER TABLE suppliers
    ADD COLUMN products_supplied TEXT NULL AFTER address,
    ADD INDEX idx_supplier_status_name (status, name);

ALTER TABLE inventory_items
    ADD INDEX idx_inventory_status_stock (status, quantity, reorder_level),
    ADD INDEX idx_inventory_supplier (supplier_id);

ALTER TABLE inventory_transactions
    ADD COLUMN balance_after DECIMAL(12,3) NULL AFTER unit_cost,
    ADD INDEX idx_inventory_tx_item_date (inventory_item_id, created_at),
    ADD INDEX idx_inventory_tx_type_date (type, created_at);

INSERT INTO suppliers (name,contact_person,email,phone,address,products_supplied,status)
SELECT 'Cebu Roasters Supply','Francis Dela Cruz','francis.beans@franscoffee.com','0917 555 0101','Cebu City, Cebu','Arabica beans, espresso blend','ACTIVE'
WHERE NOT EXISTS (SELECT 1 FROM suppliers WHERE name='Cebu Roasters Supply');
INSERT INTO suppliers (name,contact_person,email,phone,address,products_supplied,status)
SELECT 'Fresh Dairy & Co.','Francis Reyes','francis.dairy@franscoffee.com','0917 555 0102','Mandaue City, Cebu','Fresh milk, oat milk, almond milk','ACTIVE'
WHERE NOT EXISTS (SELECT 1 FROM suppliers WHERE name='Fresh Dairy & Co.');
INSERT INTO suppliers (name,contact_person,email,phone,address,products_supplied,status)
SELECT 'CupCraft Packaging','Francis Santos','francis.packaging@franscoffee.com','0917 555 0103','Lapu-Lapu City, Cebu','Cups, lids, straws, takeaway packaging','ACTIVE'
WHERE NOT EXISTS (SELECT 1 FROM suppliers WHERE name='CupCraft Packaging');

INSERT INTO inventory_items (supplier_id,item_code,item_name,category,unit,quantity,reorder_level,unit_cost,status)
SELECT s.id,'FCS-INV-0001','Espresso Coffee Beans','Coffee','kg',12.000,5.000,620.00,'ACTIVE' FROM suppliers s WHERE s.name='Cebu Roasters Supply'
AND NOT EXISTS (SELECT 1 FROM inventory_items WHERE item_code='FCS-INV-0001');
INSERT INTO inventory_items (supplier_id,item_code,item_name,category,unit,quantity,reorder_level,unit_cost,status)
SELECT s.id,'FCS-INV-0002','Fresh Milk','Dairy','L',18.000,8.000,92.00,'ACTIVE' FROM suppliers s WHERE s.name='Fresh Dairy & Co.'
AND NOT EXISTS (SELECT 1 FROM inventory_items WHERE item_code='FCS-INV-0002');
INSERT INTO inventory_items (supplier_id,item_code,item_name,category,unit,quantity,reorder_level,unit_cost,status)
SELECT s.id,'FCS-INV-0003','Oat Milk','Dairy','L',5.000,6.000,165.00,'ACTIVE' FROM suppliers s WHERE s.name='Fresh Dairy & Co.'
AND NOT EXISTS (SELECT 1 FROM inventory_items WHERE item_code='FCS-INV-0003');
INSERT INTO inventory_items (supplier_id,item_code,item_name,category,unit,quantity,reorder_level,unit_cost,status)
SELECT s.id,'FCS-INV-0004','Almond Milk','Dairy','L',4.000,5.000,175.00,'ACTIVE' FROM suppliers s WHERE s.name='Fresh Dairy & Co.'
AND NOT EXISTS (SELECT 1 FROM inventory_items WHERE item_code='FCS-INV-0004');
INSERT INTO inventory_items (supplier_id,item_code,item_name,category,unit,quantity,reorder_level,unit_cost,status)
SELECT NULL,'FCS-INV-0005','Caramel Syrup','Syrups','bottle',8.000,3.000,285.00,'ACTIVE'
WHERE NOT EXISTS (SELECT 1 FROM inventory_items WHERE item_code='FCS-INV-0005');
INSERT INTO inventory_items (supplier_id,item_code,item_name,category,unit,quantity,reorder_level,unit_cost,status)
SELECT NULL,'FCS-INV-0006','Matcha Powder','Tea & Matcha','kg',2.500,1.000,980.00,'ACTIVE'
WHERE NOT EXISTS (SELECT 1 FROM inventory_items WHERE item_code='FCS-INV-0006');
INSERT INTO inventory_items (supplier_id,item_code,item_name,category,unit,quantity,reorder_level,unit_cost,status)
SELECT s.id,'FCS-INV-0007','16oz Cold Cups','Packaging','pcs',120.000,100.000,4.50,'ACTIVE' FROM suppliers s WHERE s.name='CupCraft Packaging'
AND NOT EXISTS (SELECT 1 FROM inventory_items WHERE item_code='FCS-INV-0007');
INSERT INTO inventory_items (supplier_id,item_code,item_name,category,unit,quantity,reorder_level,unit_cost,status)
SELECT s.id,'FCS-INV-0008','16oz Cup Lids','Packaging','pcs',85.000,100.000,2.25,'ACTIVE' FROM suppliers s WHERE s.name='CupCraft Packaging'
AND NOT EXISTS (SELECT 1 FROM inventory_items WHERE item_code='FCS-INV-0008');

INSERT INTO inventory_transactions (inventory_item_id,type,quantity,unit_cost,balance_after,reference_type,notes,created_by)
SELECT i.id,'STOCK_IN',i.quantity,i.unit_cost,i.quantity,'OPENING','Phase 10 opening balance',u.id
FROM inventory_items i JOIN users u ON u.email='francis.admin@franscoffee.com'
WHERE i.item_code IN ('FCS-INV-0001','FCS-INV-0002','FCS-INV-0003','FCS-INV-0004','FCS-INV-0005','FCS-INV-0006','FCS-INV-0007','FCS-INV-0008')
AND NOT EXISTS (SELECT 1 FROM inventory_transactions t WHERE t.inventory_item_id=i.id);
