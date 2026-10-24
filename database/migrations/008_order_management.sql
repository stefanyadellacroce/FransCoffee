-- Franscoffee Shop Phase 8: Order Management
-- Import once after Phase 7.

ALTER TABLE payments
    ADD COLUMN verification_note VARCHAR(255) NULL AFTER verified_at;

CREATE INDEX idx_orders_status_placed ON orders (status, placed_at);
CREATE INDEX idx_payments_status ON payments (status);
CREATE INDEX idx_order_history_order_created ON order_status_history (order_id, created_at);
