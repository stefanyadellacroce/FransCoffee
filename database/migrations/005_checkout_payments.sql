USE franscoffee;

-- Run this migration ONCE when upgrading from Phase 4 to Phase 5.
ALTER TABLE addresses
    ADD COLUMN notes VARCHAR(255) NULL AFTER landmark;

ALTER TABLE orders
    ADD COLUMN contact_name VARCHAR(160) NULL AFTER status,
    ADD COLUMN contact_email VARCHAR(190) NULL AFTER contact_name,
    ADD COLUMN contact_phone VARCHAR(30) NULL AFTER contact_email,
    ADD COLUMN delivery_address_id BIGINT UNSIGNED NULL AFTER contact_phone,
    ADD COLUMN delivery_address_json JSON NULL AFTER delivery_address_id,
    ADD INDEX idx_orders_delivery_address (delivery_address_id),
    ADD CONSTRAINT fk_orders_delivery_address FOREIGN KEY (delivery_address_id) REFERENCES addresses(id) ON DELETE SET NULL;

UPDATE payment_methods SET receiver_name='Franscoffee Shop Demo', receiver_number='0917 000 1001', qr_path='assets/images/payments/gcash-demo-qr.svg', is_active=1 WHERE code='GCASH';
UPDATE payment_methods SET receiver_name='Franscoffee Shop Demo', receiver_number='0917 000 1002', qr_path='assets/images/payments/maya-demo-qr.svg', is_active=1 WHERE code='MAYA';
UPDATE payment_methods SET is_active=1 WHERE code IN ('COP','COD');
UPDATE payment_methods SET is_active=0 WHERE code='CASH';

INSERT INTO settings (setting_key,setting_value) VALUES
('minimum_order','0.00')
ON DUPLICATE KEY UPDATE setting_value=VALUES(setting_value);
