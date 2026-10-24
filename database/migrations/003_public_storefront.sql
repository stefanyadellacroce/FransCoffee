USE franscoffee;

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

INSERT INTO product_sizes (product_id,name,price_adjustment)
SELECT p.id,'Small',0 FROM products p WHERE p.slug IN ('americano','cafe-latte','cappuccino','spanish-latte','caramel-macchiato','cold-brew','matcha-latte','signature-chocolate','strawberry-milk','earl-grey-tea')
AND NOT EXISTS (SELECT 1 FROM product_sizes s WHERE s.product_id=p.id AND s.name='Small');
INSERT INTO product_sizes (product_id,name,price_adjustment)
SELECT p.id,'Medium',20 FROM products p WHERE p.slug IN ('americano','cafe-latte','cappuccino','spanish-latte','caramel-macchiato','cold-brew','matcha-latte','signature-chocolate','strawberry-milk','earl-grey-tea')
AND NOT EXISTS (SELECT 1 FROM product_sizes s WHERE s.product_id=p.id AND s.name='Medium');
INSERT INTO product_sizes (product_id,name,price_adjustment)
SELECT p.id,'Large',40 FROM products p WHERE p.slug IN ('americano','cafe-latte','cappuccino','spanish-latte','caramel-macchiato','cold-brew','matcha-latte','signature-chocolate','strawberry-milk','earl-grey-tea')
AND NOT EXISTS (SELECT 1 FROM product_sizes s WHERE s.product_id=p.id AND s.name='Large');

INSERT INTO product_addons (product_id,name,price)
SELECT p.id,'Extra Espresso Shot',35 FROM products p WHERE p.slug IN ('americano','cafe-latte','cappuccino','spanish-latte','caramel-macchiato','cold-brew')
AND NOT EXISTS (SELECT 1 FROM product_addons a WHERE a.product_id=p.id AND a.name='Extra Espresso Shot');
INSERT INTO product_addons (product_id,name,price)
SELECT p.id,'Caramel',25 FROM products p WHERE p.slug IN ('cafe-latte','cappuccino','spanish-latte','caramel-macchiato','cold-brew','signature-chocolate')
AND NOT EXISTS (SELECT 1 FROM product_addons a WHERE a.product_id=p.id AND a.name='Caramel');
INSERT INTO product_addons (product_id,name,price)
SELECT p.id,'Vanilla',25 FROM products p WHERE p.slug IN ('cafe-latte','cappuccino','spanish-latte','caramel-macchiato','cold-brew')
AND NOT EXISTS (SELECT 1 FROM product_addons a WHERE a.product_id=p.id AND a.name='Vanilla');
INSERT INTO product_addons (product_id,name,price)
SELECT p.id,'Whipped Cream',25 FROM products p WHERE p.slug IN ('cafe-latte','caramel-macchiato','signature-chocolate','strawberry-milk')
AND NOT EXISTS (SELECT 1 FROM product_addons a WHERE a.product_id=p.id AND a.name='Whipped Cream');
INSERT INTO product_addons (product_id,name,price)
SELECT p.id,'Oat Milk',30 FROM products p WHERE p.slug IN ('cafe-latte','cappuccino','spanish-latte','caramel-macchiato','matcha-latte','signature-chocolate')
AND NOT EXISTS (SELECT 1 FROM product_addons a WHERE a.product_id=p.id AND a.name='Oat Milk');

INSERT IGNORE INTO settings (setting_key,setting_value) VALUES
('store_address','Cebu City, Cebu'),
('business_hours','Monday–Sunday · 7:00 AM–9:00 PM');
