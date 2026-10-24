USE franscoffee;

-- Phase 4 uses the carts, cart_items, and favorites tables already created in Phase 1.
-- This migration adds the second milk choice used by the product customization UI.
INSERT INTO product_addons (product_id,name,price)
SELECT p.id,'Almond Milk',30
FROM products p
WHERE p.slug IN ('cafe-latte','cappuccino','spanish-latte','caramel-macchiato','matcha-latte','signature-chocolate')
AND NOT EXISTS (
    SELECT 1 FROM product_addons a
    WHERE a.product_id=p.id AND a.name='Almond Milk'
);
