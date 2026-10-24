-- Franscoffee Shop Phase 11: Payments / Expenses / Promotions
-- Run once against an existing Phase 10 database.

CREATE TABLE IF NOT EXISTS expenses (
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

CREATE TABLE IF NOT EXISTS promotions (
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

CREATE TABLE IF NOT EXISTS promotion_usages (
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

INSERT INTO promotions (code,name,discount_type,discount_value,minimum_spend,start_date,end_date,usage_limit,per_customer_limit,status,created_by)
SELECT 'WELCOME10','Welcome 10% Off','PERCENT',10.00,200.00,CURDATE(),DATE_ADD(CURDATE(), INTERVAL 365 DAY),100,1,'ACTIVE',u.id
FROM users u WHERE u.email='francis.admin@franscoffee.com'
AND NOT EXISTS (SELECT 1 FROM promotions WHERE code='WELCOME10');

INSERT INTO promotions (code,name,discount_type,discount_value,minimum_spend,start_date,end_date,usage_limit,per_customer_limit,status,created_by)
SELECT 'BREW50','Brew ₱50 Off','FIXED',50.00,500.00,CURDATE(),DATE_ADD(CURDATE(), INTERVAL 365 DAY),50,2,'ACTIVE',u.id
FROM users u WHERE u.email='francis.admin@franscoffee.com'
AND NOT EXISTS (SELECT 1 FROM promotions WHERE code='BREW50');

INSERT INTO expenses (expense_number,category,amount,expense_date,description,recorded_by)
SELECT CONCAT('FCS-EXP-',DATE_FORMAT(CURDATE(),'%Y%m%d'),'-0001'),'Utilities',1800.00,CURDATE(),'Sample electricity and water operating expense for Phase 11 testing.',u.id
FROM users u WHERE u.email='francis.admin@franscoffee.com'
AND NOT EXISTS (SELECT 1 FROM expenses WHERE expense_number=CONCAT('FCS-EXP-',DATE_FORMAT(CURDATE(),'%Y%m%d'),'-0001'));
