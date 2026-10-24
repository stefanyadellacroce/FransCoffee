USE franscoffee;

-- Phase 6 — Customer portal preferences and notification lookup performance.
-- Run this once when upgrading from Phase 5.
ALTER TABLE customer_profiles
    ADD COLUMN notifications_enabled TINYINT(1) NOT NULL DEFAULT 1 AFTER preferred_category;

CREATE INDEX idx_notifications_user_read
    ON notifications (user_id, read_at, created_at);

-- Existing order-received notices should open the new customer tracking page.
UPDATE notifications n
JOIN orders o ON o.user_id = n.user_id
SET n.target_url = CONCAT('customer/order-details.php?order=', o.order_number)
WHERE n.title = 'Order received'
  AND n.target_url LIKE 'order-confirmation.php?order=%'
  AND n.message LIKE CONCAT('%', o.order_number, '%');
