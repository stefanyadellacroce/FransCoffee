CREATE DATABASE IF NOT EXISTS franscoffee CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE franscoffee;

SET FOREIGN_KEY_CHECKS = 0;
DROP TABLE IF EXISTS contact_messages, password_reset_tokens, activity_logs, notifications, promotion_usages, promotions, expenses, settings, inventory_transactions, inventory_items, suppliers, payment_proofs, payments, payment_methods, order_status_history, order_item_options, order_items, orders, addresses, cart_items, carts, favorites, product_addons, product_sizes, product_images, products, categories, staff_profiles, customer_profiles, users;
SET FOREIGN_KEY_CHECKS = 1;

CREATE TABLE users (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    first_name VARCHAR(80) NOT NULL,
    last_name VARCHAR(80) NOT NULL,
    email VARCHAR(190) NOT NULL UNIQUE,
    phone VARCHAR(30) NULL,
    password_hash VARCHAR(255) NOT NULL,
    role ENUM('ADMIN','STAFF','CUSTOMER') NOT NULL DEFAULT 'CUSTOMER',
    status ENUM('ACTIVE','INACTIVE') NOT NULL DEFAULT 'ACTIVE',
    email_verified_at DATETIME NULL,
    last_login_at DATETIME NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_users_role_status (role, status)
) ENGINE=InnoDB;

CREATE TABLE password_reset_tokens (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id BIGINT UNSIGNED NOT NULL,
    token_hash CHAR(64) NOT NULL UNIQUE,
    expires_at DATETIME NOT NULL,
    used_at DATETIME NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_password_reset_user (user_id),
    INDEX idx_password_reset_expiry (expires_at),
    CONSTRAINT fk_password_reset_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE customer_profiles (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id BIGINT UNSIGNED NOT NULL UNIQUE,
    avatar_path VARCHAR(255) NULL,
    preferred_category VARCHAR(80) NULL,
    notifications_enabled TINYINT(1) NOT NULL DEFAULT 1,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_customer_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE staff_profiles (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id BIGINT UNSIGNED NOT NULL UNIQUE,
    employee_code VARCHAR(40) NOT NULL UNIQUE,
    job_title VARCHAR(80) NOT NULL DEFAULT 'Staff',
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_staff_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE categories (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL UNIQUE,
    slug VARCHAR(120) NOT NULL UNIQUE,
    sort_order INT NOT NULL DEFAULT 0,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE products (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    category_id BIGINT UNSIGNED NOT NULL,
    name VARCHAR(140) NOT NULL,
    slug VARCHAR(160) NOT NULL UNIQUE,
    description TEXT NULL,
    base_price DECIMAL(10,2) NOT NULL DEFAULT 0,
    image_path VARCHAR(255) NULL,
    prep_minutes SMALLINT UNSIGNED NOT NULL DEFAULT 10,
    is_featured TINYINT(1) NOT NULL DEFAULT 0,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_products_category_active (category_id, is_active),
    CONSTRAINT fk_products_category FOREIGN KEY (category_id) REFERENCES categories(id)
) ENGINE=InnoDB;

CREATE TABLE product_images (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    product_id BIGINT UNSIGNED NOT NULL,
    image_path VARCHAR(255) NOT NULL,
    sort_order INT NOT NULL DEFAULT 0,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_product_images_product FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE product_sizes (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    product_id BIGINT UNSIGNED NOT NULL,
    name VARCHAR(50) NOT NULL,
    price_adjustment DECIMAL(10,2) NOT NULL DEFAULT 0,
    sort_order INT NOT NULL DEFAULT 0,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_product_size_name (product_id, name),
    INDEX idx_product_sizes_active_sort (product_id, is_active, sort_order),
    CONSTRAINT fk_product_sizes_product FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE product_addons (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    product_id BIGINT UNSIGNED NOT NULL,
    name VARCHAR(100) NOT NULL,
    price DECIMAL(10,2) NOT NULL DEFAULT 0,
    sort_order INT NOT NULL DEFAULT 0,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_product_addon_name (product_id, name),
    INDEX idx_product_addons_active_sort (product_id, is_active, sort_order),
    CONSTRAINT fk_product_addons_product FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE favorites (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id BIGINT UNSIGNED NOT NULL,
    product_id BIGINT UNSIGNED NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_favorite (user_id, product_id),
    CONSTRAINT fk_favorites_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    CONSTRAINT fk_favorites_product FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE carts (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id BIGINT UNSIGNED NULL,
    session_key VARCHAR(128) NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_carts_user (user_id),
    INDEX idx_carts_session (session_key),
    CONSTRAINT fk_carts_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE cart_items (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    cart_id BIGINT UNSIGNED NOT NULL,
    product_id BIGINT UNSIGNED NOT NULL,
    quantity INT UNSIGNED NOT NULL DEFAULT 1,
    options_json JSON NULL,
    unit_price DECIMAL(10,2) NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_cart_items_cart FOREIGN KEY (cart_id) REFERENCES carts(id) ON DELETE CASCADE,
    CONSTRAINT fk_cart_items_product FOREIGN KEY (product_id) REFERENCES products(id)
) ENGINE=InnoDB;

CREATE TABLE addresses (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id BIGINT UNSIGNED NOT NULL,
    label VARCHAR(60) NOT NULL DEFAULT 'Home',
    street VARCHAR(190) NOT NULL,
    barangay VARCHAR(120) NOT NULL,
    city VARCHAR(120) NOT NULL,
    province VARCHAR(120) NOT NULL,
    postal_code VARCHAR(12) NULL,
    landmark VARCHAR(190) NULL,
    notes VARCHAR(255) NULL,
    is_default TINYINT(1) NOT NULL DEFAULT 0,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_addresses_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE orders (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    order_number VARCHAR(40) NOT NULL UNIQUE,
    user_id BIGINT UNSIGNED NULL,
    created_by BIGINT UNSIGNED NULL,
    order_source ENUM('ONLINE','POS') NOT NULL DEFAULT 'ONLINE',
    fulfillment_type ENUM('PICKUP','DELIVERY') NOT NULL DEFAULT 'PICKUP',
    status ENUM('PENDING','CONFIRMED','PREPARING','READY','OUT_FOR_DELIVERY','COMPLETED','CANCELLED') NOT NULL DEFAULT 'PENDING',
    contact_name VARCHAR(160) NULL,
    contact_email VARCHAR(190) NULL,
    contact_phone VARCHAR(30) NULL,
    delivery_address_id BIGINT UNSIGNED NULL,
    delivery_address_json JSON NULL,
    subtotal DECIMAL(10,2) NOT NULL DEFAULT 0,
    discount_amount DECIMAL(10,2) NOT NULL DEFAULT 0,
    delivery_fee DECIMAL(10,2) NOT NULL DEFAULT 0,
    total_amount DECIMAL(10,2) NOT NULL DEFAULT 0,
    notes TEXT NULL,
    placed_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_orders_user_status (user_id, status),
    INDEX idx_orders_created_by (created_by),
    INDEX idx_orders_status_placed (status, placed_at),
    INDEX idx_orders_placed_at (placed_at),
    INDEX idx_orders_delivery_address (delivery_address_id),
    CONSTRAINT fk_orders_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL,
    CONSTRAINT fk_orders_created_by FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL,
    CONSTRAINT fk_orders_delivery_address FOREIGN KEY (delivery_address_id) REFERENCES addresses(id) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE order_items (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    order_id BIGINT UNSIGNED NOT NULL,
    product_id BIGINT UNSIGNED NULL,
    product_name VARCHAR(140) NOT NULL,
    unit_price DECIMAL(10,2) NOT NULL,
    quantity INT UNSIGNED NOT NULL,
    line_total DECIMAL(10,2) NOT NULL,
    INDEX idx_order_items_product_order (product_id, order_id),
    CONSTRAINT fk_order_items_order FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE,
    CONSTRAINT fk_order_items_product FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE order_item_options (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    order_item_id BIGINT UNSIGNED NOT NULL,
    option_name VARCHAR(100) NOT NULL,
    option_value VARCHAR(140) NOT NULL,
    price_adjustment DECIMAL(10,2) NOT NULL DEFAULT 0,
    CONSTRAINT fk_order_options_item FOREIGN KEY (order_item_id) REFERENCES order_items(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE order_status_history (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    order_id BIGINT UNSIGNED NOT NULL,
    status VARCHAR(40) NOT NULL,
    changed_by BIGINT UNSIGNED NULL,
    note VARCHAR(255) NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_order_history_order_created (order_id, created_at),
    CONSTRAINT fk_status_order FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE,
    CONSTRAINT fk_status_user FOREIGN KEY (changed_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE payment_methods (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    code VARCHAR(40) NOT NULL UNIQUE,
    name VARCHAR(80) NOT NULL,
    is_online TINYINT(1) NOT NULL DEFAULT 0,
    receiver_name VARCHAR(120) NULL,
    receiver_number VARCHAR(60) NULL,
    qr_path VARCHAR(255) NULL,
    is_active TINYINT(1) NOT NULL DEFAULT 1
) ENGINE=InnoDB;

CREATE TABLE payments (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    payment_number VARCHAR(40) NOT NULL UNIQUE,
    order_id BIGINT UNSIGNED NOT NULL,
    payment_method_id BIGINT UNSIGNED NOT NULL,
    amount DECIMAL(10,2) NOT NULL,
    status ENUM('UNPAID','PENDING','PAID','FAILED','REFUNDED') NOT NULL DEFAULT 'UNPAID',
    reference_number VARCHAR(120) NULL,
    cash_received DECIMAL(10,2) NULL,
    change_due DECIMAL(10,2) NOT NULL DEFAULT 0,
    verified_by BIGINT UNSIGNED NULL,
    verified_at DATETIME NULL,
    verification_note VARCHAR(255) NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_payments_status (status),
    INDEX idx_payments_verified_status (status, verified_at),
    INDEX idx_payments_method_status_created (payment_method_id, status, created_at),
    CONSTRAINT fk_payments_order FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE,
    CONSTRAINT fk_payments_method FOREIGN KEY (payment_method_id) REFERENCES payment_methods(id),
    CONSTRAINT fk_payments_verifier FOREIGN KEY (verified_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE payment_proofs (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    payment_id BIGINT UNSIGNED NOT NULL,
    file_path VARCHAR(255) NOT NULL,
    uploaded_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_payment_proofs_payment FOREIGN KEY (payment_id) REFERENCES payments(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE suppliers (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(150) NOT NULL,
    contact_person VARCHAR(120) NULL,
    email VARCHAR(190) NULL,
    phone VARCHAR(40) NULL,
    address TEXT NULL,
    products_supplied TEXT NULL,
    status ENUM('ACTIVE','INACTIVE') NOT NULL DEFAULT 'ACTIVE',
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_supplier_status_name (status, name)
) ENGINE=InnoDB;

CREATE TABLE inventory_items (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    supplier_id BIGINT UNSIGNED NULL,
    item_code VARCHAR(40) NOT NULL UNIQUE,
    item_name VARCHAR(140) NOT NULL,
    category VARCHAR(100) NULL,
    unit VARCHAR(40) NOT NULL,
    quantity DECIMAL(12,3) NOT NULL DEFAULT 0,
    reorder_level DECIMAL(12,3) NOT NULL DEFAULT 0,
    unit_cost DECIMAL(10,2) NOT NULL DEFAULT 0,
    status ENUM('ACTIVE','INACTIVE') NOT NULL DEFAULT 'ACTIVE',
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_inventory_status_stock (status, quantity, reorder_level),
    INDEX idx_inventory_supplier (supplier_id),
    CONSTRAINT fk_inventory_supplier FOREIGN KEY (supplier_id) REFERENCES suppliers(id) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE inventory_transactions (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    inventory_item_id BIGINT UNSIGNED NOT NULL,
    type ENUM('STOCK_IN','STOCK_OUT','ADJUSTMENT','WASTE') NOT NULL,
    quantity DECIMAL(12,3) NOT NULL,
    unit_cost DECIMAL(10,2) NULL,
    balance_after DECIMAL(12,3) NULL,
    reference_type VARCHAR(60) NULL,
    reference_id BIGINT UNSIGNED NULL,
    notes VARCHAR(255) NULL,
    created_by BIGINT UNSIGNED NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_inventory_tx_item_date (inventory_item_id, created_at),
    INDEX idx_inventory_tx_type_date (type, created_at),
    CONSTRAINT fk_inventory_tx_item FOREIGN KEY (inventory_item_id) REFERENCES inventory_items(id) ON DELETE CASCADE,
    CONSTRAINT fk_inventory_tx_user FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE expenses (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    expense_number VARCHAR(40) NOT NULL UNIQUE,
    category ENUM('Inventory','Utilities','Rent','Salary','Repairs','Equipment','Miscellaneous') NOT NULL,
    amount DECIMAL(10,2) NOT NULL,
    expense_date DATE NOT NULL,
    description VARCHAR(500) NOT NULL,
    recorded_by BIGINT UNSIGNED NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_expenses_date_category (expense_date, category),
    CONSTRAINT fk_expenses_user FOREIGN KEY (recorded_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE promotions (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    code VARCHAR(40) NOT NULL UNIQUE,
    name VARCHAR(140) NOT NULL,
    discount_type ENUM('PERCENT','FIXED') NOT NULL,
    discount_value DECIMAL(10,2) NOT NULL,
    minimum_spend DECIMAL(10,2) NOT NULL DEFAULT 0,
    start_date DATE NULL,
    end_date DATE NULL,
    usage_limit INT UNSIGNED NULL,
    per_customer_limit INT UNSIGNED NULL DEFAULT 1,
    status ENUM('ACTIVE','INACTIVE') NOT NULL DEFAULT 'ACTIVE',
    created_by BIGINT UNSIGNED NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_promotions_status_dates (status, start_date, end_date),
    CONSTRAINT fk_promotions_user FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE promotion_usages (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    promotion_id BIGINT UNSIGNED NOT NULL,
    user_id BIGINT UNSIGNED NULL,
    order_id BIGINT UNSIGNED NOT NULL,
    discount_amount DECIMAL(10,2) NOT NULL DEFAULT 0,
    used_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_promotion_usage_order (order_id),
    INDEX idx_promotion_usage_promo_user (promotion_id, user_id),
    CONSTRAINT fk_promotion_usage_promotion FOREIGN KEY (promotion_id) REFERENCES promotions(id),
    CONSTRAINT fk_promotion_usage_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL,
    CONSTRAINT fk_promotion_usage_order FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE notifications (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id BIGINT UNSIGNED NOT NULL,
    title VARCHAR(140) NOT NULL,
    message VARCHAR(255) NOT NULL,
    target_url VARCHAR(255) NULL,
    read_at DATETIME NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_notifications_user_read (user_id, read_at, created_at),
    CONSTRAINT fk_notifications_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE activity_logs (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id BIGINT UNSIGNED NULL,
    action VARCHAR(80) NOT NULL,
    entity_type VARCHAR(80) NULL,
    entity_id BIGINT UNSIGNED NULL,
    ip_address VARCHAR(45) NULL,
    details_json JSON NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_activity_created (created_at),
    CONSTRAINT fk_activity_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE settings (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    setting_key VARCHAR(120) NOT NULL UNIQUE,
    setting_value TEXT NULL,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;

INSERT INTO users (first_name,last_name,email,phone,password_hash,role,status) VALUES
('Francis','Admin','francis.admin@franscoffee.com','09170000001','$2y$12$Ce.5Ig99MiNEa9zTlciVGem8v0ZE2cjifnS2BFBSmlNGfCgkKuvUm','ADMIN','ACTIVE'),
('Francis','Staff','francis.staff@franscoffee.com','09170000002','$2y$12$AiuSVDnB7d7gOWfQgDfwE.gR.1VX6CDAOgOJgQr3CrUC2G5Rq.o2W','STAFF','ACTIVE'),
('Francis','Customer','francis.customer@franscoffee.com','09170000003','$2y$12$HIPzved2Ylbhy9E34JnP5eYfIFtogme0mec661xwqdkNY0bnp.aQO','CUSTOMER','ACTIVE');

INSERT INTO staff_profiles (user_id,employee_code,job_title)
SELECT id,'FCS-STAFF-0001','Cashier' FROM users WHERE email='francis.staff@franscoffee.com';
INSERT INTO customer_profiles (user_id)
SELECT id FROM users WHERE email='francis.customer@franscoffee.com';

INSERT INTO categories (name,slug,sort_order) VALUES
('Espresso','espresso',1),('Hot Coffee','hot-coffee',2),('Iced Coffee','iced-coffee',3),('Non-Coffee','non-coffee',4),('Matcha','matcha',5),('Tea','tea',6),('Pastries','pastries',7),('Meals','meals',8);

INSERT INTO payment_methods (code,name,is_online) VALUES
('CASH','Cash',0),('COP','Cash on Pickup',0),('COD','Cash on Delivery',0);
INSERT INTO payment_methods (code,name,is_online,receiver_name,receiver_number) VALUES
('GCASH','GCash',1,'Franscoffee Shop Demo','0917 000 1001'),('MAYA','Maya',1,'Franscoffee Shop Demo','0917 000 1002');
UPDATE payment_methods SET qr_path='assets/images/payments/gcash-demo-qr.svg' WHERE code='GCASH';
UPDATE payment_methods SET qr_path='assets/images/payments/maya-demo-qr.svg' WHERE code='MAYA';
UPDATE payment_methods SET is_active=1 WHERE code='CASH';

INSERT INTO settings (setting_key,setting_value) VALUES
('shop_name','Franscoffee Shop'),
('tagline','Good Coffee, Brighter Days.'),
('contact_email','francis@franscoffee.com'),
('contact_phone','09XX XXX XXXX'),
('delivery_fee','50.00'),
('pickup_enabled','1'),
('delivery_enabled','1'),
('minimum_order','0.00');

-- Phase 3 public storefront seed
CREATE TABLE IF NOT EXISTS contact_messages (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(120) NOT NULL,
    email VARCHAR(190) NOT NULL,
    phone VARCHAR(30) NULL,
    subject VARCHAR(160) NOT NULL,
    message TEXT NOT NULL,
    status ENUM('NEW','READ','REPLIED') NOT NULL DEFAULT 'NEW',
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_contact_status_created (status, created_at)
) ENGINE=InnoDB;

INSERT INTO products (category_id,name,slug,description,base_price,image_path,prep_minutes,is_featured,is_active)
SELECT c.id,'Americano','americano','A clean espresso-forward cup with hot water for a smooth, bold finish.',110.00,'assets/images/products/americano.svg',5,1,1 FROM categories c
WHERE c.slug='espresso' AND NOT EXISTS (SELECT 1 FROM products WHERE slug='americano');

INSERT INTO products (category_id,name,slug,description,base_price,image_path,prep_minutes,is_featured,is_active)
SELECT c.id,'Café Latte','cafe-latte','Balanced espresso and steamed milk with a soft, creamy finish.',145.00,'assets/images/products/cafe-latte.svg',7,1,1 FROM categories c
WHERE c.slug='hot-coffee' AND NOT EXISTS (SELECT 1 FROM products WHERE slug='cafe-latte');

INSERT INTO products (category_id,name,slug,description,base_price,image_path,prep_minutes,is_featured,is_active)
SELECT c.id,'Cappuccino','cappuccino','Espresso with silky steamed milk and an airy cap of foam.',145.00,'assets/images/products/cappuccino.svg',7,0,1 FROM categories c
WHERE c.slug='hot-coffee' AND NOT EXISTS (SELECT 1 FROM products WHERE slug='cappuccino');

INSERT INTO products (category_id,name,slug,description,base_price,image_path,prep_minutes,is_featured,is_active)
SELECT c.id,'Spanish Latte','spanish-latte','Espresso, milk, and a gentle sweetness for a rich but balanced everyday favorite.',165.00,'assets/images/products/spanish-latte.svg',8,1,1 FROM categories c
WHERE c.slug='hot-coffee' AND NOT EXISTS (SELECT 1 FROM products WHERE slug='spanish-latte');

INSERT INTO products (category_id,name,slug,description,base_price,image_path,prep_minutes,is_featured,is_active)
SELECT c.id,'Caramel Macchiato','caramel-macchiato','Creamy milk, espresso, vanilla, and caramel layered over ice.',175.00,'assets/images/products/caramel-macchiato.svg',8,1,1 FROM categories c
WHERE c.slug='iced-coffee' AND NOT EXISTS (SELECT 1 FROM products WHERE slug='caramel-macchiato');

INSERT INTO products (category_id,name,slug,description,base_price,image_path,prep_minutes,is_featured,is_active)
SELECT c.id,'Cold Brew','cold-brew','Slow-steeped coffee served cold for a mellow body and naturally smooth finish.',155.00,'assets/images/products/cold-brew.svg',4,0,1 FROM categories c
WHERE c.slug='iced-coffee' AND NOT EXISTS (SELECT 1 FROM products WHERE slug='cold-brew');

INSERT INTO products (category_id,name,slug,description,base_price,image_path,prep_minutes,is_featured,is_active)
SELECT c.id,'Matcha Latte','matcha-latte','Earthy matcha whisked smooth with milk for a creamy green-tea drink.',165.00,'assets/images/products/matcha-latte.svg',8,1,1 FROM categories c
WHERE c.slug='matcha' AND NOT EXISTS (SELECT 1 FROM products WHERE slug='matcha-latte');

INSERT INTO products (category_id,name,slug,description,base_price,image_path,prep_minutes,is_featured,is_active)
SELECT c.id,'Signature Chocolate','signature-chocolate','Deep cocoa blended with milk for a comforting non-coffee classic.',150.00,'assets/images/products/signature-chocolate.svg',7,0,1 FROM categories c
WHERE c.slug='non-coffee' AND NOT EXISTS (SELECT 1 FROM products WHERE slug='signature-chocolate');

INSERT INTO products (category_id,name,slug,description,base_price,image_path,prep_minutes,is_featured,is_active)
SELECT c.id,'Strawberry Milk','strawberry-milk','Creamy milk with bright strawberry flavor, served chilled.',155.00,'assets/images/products/strawberry-milk.svg',6,0,1 FROM categories c
WHERE c.slug='non-coffee' AND NOT EXISTS (SELECT 1 FROM products WHERE slug='strawberry-milk');

INSERT INTO products (category_id,name,slug,description,base_price,image_path,prep_minutes,is_featured,is_active)
SELECT c.id,'Earl Grey Tea','earl-grey-tea','Fragrant black tea with citrusy bergamot notes and a clean finish.',120.00,'assets/images/products/earl-grey-tea.svg',6,0,1 FROM categories c
WHERE c.slug='tea' AND NOT EXISTS (SELECT 1 FROM products WHERE slug='earl-grey-tea');

INSERT INTO products (category_id,name,slug,description,base_price,image_path,prep_minutes,is_featured,is_active)
SELECT c.id,'Butter Croissant','butter-croissant','Flaky, buttery pastry with crisp layers and a tender center.',95.00,'assets/images/products/butter-croissant.svg',4,1,1 FROM categories c
WHERE c.slug='pastries' AND NOT EXISTS (SELECT 1 FROM products WHERE slug='butter-croissant');

INSERT INTO products (category_id,name,slug,description,base_price,image_path,prep_minutes,is_featured,is_active)
SELECT c.id,'Cinnamon Roll','cinnamon-roll','Soft swirled bread with cinnamon sugar and a light glaze.',110.00,'assets/images/products/cinnamon-roll.svg',5,0,1 FROM categories c
WHERE c.slug='pastries' AND NOT EXISTS (SELECT 1 FROM products WHERE slug='cinnamon-roll');

INSERT INTO products (category_id,name,slug,description,base_price,image_path,prep_minutes,is_featured,is_active)
SELECT c.id,'Ham & Cheese Sandwich','ham-cheese-sandwich','Toasted bread layered with savory ham and melted cheese.',175.00,'assets/images/products/ham-cheese-sandwich.svg',10,0,1 FROM categories c
WHERE c.slug='meals' AND NOT EXISTS (SELECT 1 FROM products WHERE slug='ham-cheese-sandwich');

INSERT INTO products (category_id,name,slug,description,base_price,image_path,prep_minutes,is_featured,is_active)
SELECT c.id,'Chicken Pesto Pasta','chicken-pesto-pasta','Pasta tossed in herby pesto with tender chicken and parmesan.',225.00,'assets/images/products/chicken-pesto-pasta.svg',14,0,1 FROM categories c
WHERE c.slug='meals' AND NOT EXISTS (SELECT 1 FROM products WHERE slug='chicken-pesto-pasta');

INSERT INTO product_sizes (product_id,name,price_adjustment,sort_order)
SELECT p.id,'Small',0,10 FROM products p WHERE p.slug IN ('americano','cafe-latte','cappuccino','spanish-latte','caramel-macchiato','cold-brew','matcha-latte','signature-chocolate','strawberry-milk','earl-grey-tea')
AND NOT EXISTS (SELECT 1 FROM product_sizes s WHERE s.product_id=p.id AND s.name='Small');
INSERT INTO product_sizes (product_id,name,price_adjustment,sort_order)
SELECT p.id,'Medium',20,20 FROM products p WHERE p.slug IN ('americano','cafe-latte','cappuccino','spanish-latte','caramel-macchiato','cold-brew','matcha-latte','signature-chocolate','strawberry-milk','earl-grey-tea')
AND NOT EXISTS (SELECT 1 FROM product_sizes s WHERE s.product_id=p.id AND s.name='Medium');
INSERT INTO product_sizes (product_id,name,price_adjustment,sort_order)
SELECT p.id,'Large',40,30 FROM products p WHERE p.slug IN ('americano','cafe-latte','cappuccino','spanish-latte','caramel-macchiato','cold-brew','matcha-latte','signature-chocolate','strawberry-milk','earl-grey-tea')
AND NOT EXISTS (SELECT 1 FROM product_sizes s WHERE s.product_id=p.id AND s.name='Large');

INSERT INTO product_addons (product_id,name,price,sort_order)
SELECT p.id,'Extra Espresso Shot',35,10 FROM products p WHERE p.slug IN ('americano','cafe-latte','cappuccino','spanish-latte','caramel-macchiato','cold-brew')
AND NOT EXISTS (SELECT 1 FROM product_addons a WHERE a.product_id=p.id AND a.name='Extra Espresso Shot');
INSERT INTO product_addons (product_id,name,price,sort_order)
SELECT p.id,'Caramel',25,20 FROM products p WHERE p.slug IN ('cafe-latte','cappuccino','spanish-latte','caramel-macchiato','cold-brew','signature-chocolate')
AND NOT EXISTS (SELECT 1 FROM product_addons a WHERE a.product_id=p.id AND a.name='Caramel');
INSERT INTO product_addons (product_id,name,price,sort_order)
SELECT p.id,'Vanilla',25,30 FROM products p WHERE p.slug IN ('cafe-latte','cappuccino','spanish-latte','caramel-macchiato','cold-brew')
AND NOT EXISTS (SELECT 1 FROM product_addons a WHERE a.product_id=p.id AND a.name='Vanilla');
INSERT INTO product_addons (product_id,name,price,sort_order)
SELECT p.id,'Whipped Cream',25,40 FROM products p WHERE p.slug IN ('cafe-latte','caramel-macchiato','signature-chocolate','strawberry-milk')
AND NOT EXISTS (SELECT 1 FROM product_addons a WHERE a.product_id=p.id AND a.name='Whipped Cream');
INSERT INTO product_addons (product_id,name,price,sort_order)
SELECT p.id,'Oat Milk',30,50 FROM products p WHERE p.slug IN ('cafe-latte','cappuccino','spanish-latte','caramel-macchiato','matcha-latte','signature-chocolate')
AND NOT EXISTS (SELECT 1 FROM product_addons a WHERE a.product_id=p.id AND a.name='Oat Milk');

INSERT IGNORE INTO settings (setting_key,setting_value) VALUES
('store_address','Cebu City, Cebu'),
('business_hours','Monday–Sunday · 7:00 AM–9:00 PM');

-- Phase 4 product customization seed
INSERT INTO product_addons (product_id,name,price,sort_order)
SELECT p.id,'Almond Milk',30,60
FROM products p
WHERE p.slug IN ('cafe-latte','cappuccino','spanish-latte','caramel-macchiato','matcha-latte','signature-chocolate')
AND NOT EXISTS (
    SELECT 1 FROM product_addons a
    WHERE a.product_id=p.id AND a.name='Almond Milk'
);


-- Phase 10 inventory & supplier seed
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


-- Phase 11 finance & promotion seed
INSERT INTO promotions (code,name,discount_type,discount_value,minimum_spend,start_date,end_date,usage_limit,per_customer_limit,status,created_by)
SELECT 'WELCOME10','Welcome 10% Off','PERCENT',10.00,200.00,CURDATE(),DATE_ADD(CURDATE(), INTERVAL 365 DAY),100,1,'ACTIVE',u.id FROM users u WHERE u.email='francis.admin@franscoffee.com' AND NOT EXISTS (SELECT 1 FROM promotions WHERE code='WELCOME10');
INSERT INTO promotions (code,name,discount_type,discount_value,minimum_spend,start_date,end_date,usage_limit,per_customer_limit,status,created_by)
SELECT 'BREW50','Brew ₱50 Off','FIXED',50.00,500.00,CURDATE(),DATE_ADD(CURDATE(), INTERVAL 365 DAY),50,2,'ACTIVE',u.id FROM users u WHERE u.email='francis.admin@franscoffee.com' AND NOT EXISTS (SELECT 1 FROM promotions WHERE code='BREW50');
INSERT INTO expenses (expense_number,category,amount,expense_date,description,recorded_by)
SELECT CONCAT('FCS-EXP-',DATE_FORMAT(CURDATE(),'%Y%m%d'),'-0001'),'Utilities',1800.00,CURDATE(),'Sample electricity and water operating expense for Phase 11 testing.',u.id FROM users u WHERE u.email='francis.admin@franscoffee.com' AND NOT EXISTS (SELECT 1 FROM expenses WHERE expense_number=CONCAT('FCS-EXP-',DATE_FORMAT(CURDATE(),'%Y%m%d'),'-0001'));


-- Phase 12 admin & settings defaults
INSERT INTO settings (setting_key,setting_value) VALUES
('business_days','Monday–Sunday'),
('opening_time','07:00'),
('closing_time','21:00'),
('logo_path','')
ON DUPLICATE KEY UPDATE setting_value=setting_value;
