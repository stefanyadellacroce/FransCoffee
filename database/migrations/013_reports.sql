-- Franscoffee Shop Phase 13 — Reports
-- Adds reporting-oriented indexes only. No transactional data is changed or deleted.

ALTER TABLE payments ADD INDEX idx_payments_verified_status (status, verified_at);
ALTER TABLE payments ADD INDEX idx_payments_method_status_created (payment_method_id, status, created_at);
ALTER TABLE order_items ADD INDEX idx_order_items_product_order (product_id, order_id);
