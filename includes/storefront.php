<?php

declare(strict_types=1);

function storefront_categories(bool $activeOnly = true): array
{
    $sql = 'SELECT id, name, slug, sort_order, is_active FROM categories';
    if ($activeOnly) {
        $sql .= ' WHERE is_active = 1';
    }
    $sql .= ' ORDER BY sort_order ASC, name ASC';
    return db()->query($sql)->fetchAll();
}

function storefront_products(array $filters = []): array
{
    $where = ['p.is_active = 1', 'c.is_active = 1'];
    $params = [];

    $category = trim((string) ($filters['category'] ?? ''));
    if ($category !== '') {
        $where[] = 'c.slug = :category';
        $params['category'] = $category;
    }

    $q = trim((string) ($filters['q'] ?? ''));
    if ($q !== '') {
        sql_add_search_filter($where, $params, ['p.name', 'p.description', 'c.name'], $q);
    }

    if (!empty($filters['featured'])) {
        $where[] = 'p.is_featured = 1';
    }

    $limit = max(1, min(100, (int) ($filters['limit'] ?? 100)));
    $sql = 'SELECT p.*, c.name AS category_name, c.slug AS category_slug '
        . 'FROM products p JOIN categories c ON c.id = p.category_id '
        . 'WHERE ' . implode(' AND ', $where)
        . ' ORDER BY p.is_featured DESC, c.sort_order ASC, p.name ASC '
        . 'LIMIT ' . $limit;

    $stmt = db()->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll();
}

function storefront_product_by_slug(string $slug): ?array
{
    $stmt = db()->prepare(
        'SELECT p.*, c.name AS category_name, c.slug AS category_slug '
        . 'FROM products p JOIN categories c ON c.id = p.category_id '
        . 'WHERE p.slug = :slug AND p.is_active = 1 AND c.is_active = 1 LIMIT 1'
    );
    $stmt->execute(['slug' => $slug]);
    $product = $stmt->fetch();
    return $product ?: null;
}

function storefront_product_sizes(int $productId): array
{
    $stmt = db()->prepare(
        'SELECT id, name, price_adjustment FROM product_sizes '
        . 'WHERE product_id = :product_id AND is_active = 1 ORDER BY sort_order ASC, price_adjustment ASC, id ASC'
    );
    $stmt->execute(['product_id' => $productId]);
    return $stmt->fetchAll();
}

function storefront_product_addons(int $productId): array
{
    $stmt = db()->prepare(
        'SELECT id, name, price FROM product_addons '
        . 'WHERE product_id = :product_id AND is_active = 1 ORDER BY sort_order ASC, name ASC'
    );
    $stmt->execute(['product_id' => $productId]);
    return $stmt->fetchAll();
}

function product_image_url(array $product): string
{
    $path = trim((string) ($product['image_path'] ?? ''));
    if ($path === '') {
        return asset('images/products/placeholder.svg');
    }
    if (preg_match('#^https?://#i', $path)) {
        return $path;
    }
    if (str_starts_with($path, 'assets/') || str_starts_with($path, 'uploads/')) {
        return url($path);
    }
    return asset('images/products/' . ltrim($path, '/'));
}

function shop_setting(string $key, string $default = ''): string
{
    static $settings = null;
    if (!is_array($settings)) {
        $settings = [];
        try {
            foreach (db()->query('SELECT setting_key, setting_value FROM settings')->fetchAll() as $row) {
                $settings[(string) $row['setting_key']] = (string) ($row['setting_value'] ?? '');
            }
        } catch (Throwable $e) {
            $settings = [];
        }
    }
    return array_key_exists($key, $settings) ? $settings[$key] : $default;
}

function shop_logo_url(): string
{
    $path = trim(shop_setting('logo_path', ''));
    if ($path === '') return asset('images/logo/logo.png');
    if (preg_match('#^https?://#i', $path)) return $path;
    if (str_starts_with($path, 'assets/') || str_starts_with($path, 'uploads/')) return url($path);
    return asset(ltrim($path, '/'));
}

function submit_contact_message(array $data): array
{
    $name = trim((string) ($data['name'] ?? ''));
    $email = strtolower(trim((string) ($data['email'] ?? '')));
    $phone = trim((string) ($data['phone'] ?? ''));
    $subject = trim((string) ($data['subject'] ?? ''));
    $message = trim((string) ($data['message'] ?? ''));
    $website = trim((string) ($data['website'] ?? ''));

    if ($website !== '') {
        return ['ok' => true];
    }

    $errors = [];
    if ($name === '' || mb_strlen($name) > 120) $errors[] = 'Enter your name.';
    if (!filter_var($email, FILTER_VALIDATE_EMAIL) || mb_strlen($email) > 190) $errors[] = 'Enter a valid email address.';
    if ($phone !== '' && mb_strlen($phone) > 30) $errors[] = 'Enter a valid phone number.';
    if ($subject === '' || mb_strlen($subject) > 160) $errors[] = 'Enter a subject.';
    if (mb_strlen($message) < 10 || mb_strlen($message) > 3000) $errors[] = 'Message must be between 10 and 3000 characters.';

    if ($errors) {
        return ['ok' => false, 'errors' => $errors];
    }

    $stmt = db()->prepare(
        'INSERT INTO contact_messages (name,email,phone,subject,message,status) '
        . 'VALUES (:name,:email,:phone,:subject,:message,\'NEW\')'
    );
    $stmt->execute([
        'name' => $name,
        'email' => $email,
        'phone' => $phone !== '' ? $phone : null,
        'subject' => $subject,
        'message' => $message,
    ]);

    $id = (int) db()->lastInsertId();
    $user = current_user();
    log_activity($user ? (int) $user['id'] : null, 'CONTACT_MESSAGE', 'contact_messages', $id);

    return ['ok' => true, 'id' => $id];
}
