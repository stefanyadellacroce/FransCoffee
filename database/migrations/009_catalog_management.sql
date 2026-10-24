-- Franscoffee Shop — Phase 9: Product & Category Management
-- Import this only when upgrading an existing Phase 8 database.

ALTER TABLE product_sizes
    ADD COLUMN sort_order INT NOT NULL DEFAULT 0 AFTER price_adjustment,
    ADD COLUMN created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP AFTER is_active,
    ADD COLUMN updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP AFTER created_at,
    ADD UNIQUE KEY uq_product_size_name (product_id, name),
    ADD INDEX idx_product_sizes_active_sort (product_id, is_active, sort_order);

ALTER TABLE product_addons
    ADD COLUMN sort_order INT NOT NULL DEFAULT 0 AFTER price,
    ADD COLUMN created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP AFTER is_active,
    ADD COLUMN updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP AFTER created_at,
    ADD UNIQUE KEY uq_product_addon_name (product_id, name),
    ADD INDEX idx_product_addons_active_sort (product_id, is_active, sort_order);

-- Give the existing Small / Medium / Large seed sizes a predictable display order.
UPDATE product_sizes SET sort_order = CASE LOWER(name)
    WHEN 'small' THEN 10
    WHEN 'medium' THEN 20
    WHEN 'large' THEN 30
    ELSE sort_order
END;

-- Stable default ordering for current add-ons.
UPDATE product_addons SET sort_order = id WHERE sort_order = 0;
