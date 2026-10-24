USE franscoffee;

-- Phase 7 — Staff POS. Run ONCE when upgrading from Phase 6.
ALTER TABLE orders
    ADD COLUMN created_by BIGINT UNSIGNED NULL AFTER user_id,
    ADD INDEX idx_orders_created_by (created_by),
    ADD CONSTRAINT fk_orders_created_by FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL;

ALTER TABLE payments
    ADD COLUMN cash_received DECIMAL(10,2) NULL AFTER reference_number,
    ADD COLUMN change_due DECIMAL(10,2) NOT NULL DEFAULT 0 AFTER cash_received;

UPDATE payment_methods
SET is_active = 1
WHERE code = 'CASH';
