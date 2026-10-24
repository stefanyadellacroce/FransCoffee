<?php

declare(strict_types=1);

function commerce_return_path(?string $value, string $fallback = 'menu.php'): string
{
    $value = trim((string) $value);
    if ($value === '' || str_contains($value, "\n") || str_contains($value, "\r") || str_contains($value, '..')) {
        return $fallback;
    }
    if (preg_match('#^[a-z][a-z0-9+.-]*://#i', $value) || str_starts_with($value, '//')) {
        return $fallback;
    }
    return ltrim($value, '/');
}

function remember_intended_url(string $path): void
{
    $_SESSION['_intended_url'] = commerce_return_path($path, 'customer/dashboard.php');
}

function consume_intended_url(): ?string
{
    $path = $_SESSION['_intended_url'] ?? null;
    unset($_SESSION['_intended_url']);
    return is_string($path) && $path !== '' ? commerce_return_path($path) : null;
}

function cart_session_key(bool $create = true): ?string
{
    $key = $_SESSION['_cart_key'] ?? null;
    if (is_string($key) && $key !== '') {
        return $key;
    }
    if (!$create) {
        return null;
    }
    $key = bin2hex(random_bytes(24));
    $_SESSION['_cart_key'] = $key;
    return $key;
}

function cart_user_id(): ?int
{
    $user = current_user();
    if (!$user || ($user['role'] ?? '') !== 'CUSTOMER') {
        return null;
    }
    return (int) $user['id'];
}

function cart_id(bool $create = true): ?int
{
    $pdo = db();
    $userId = cart_user_id();

    if ($userId !== null) {
        $stmt = $pdo->prepare('SELECT id FROM carts WHERE user_id = :user_id ORDER BY id DESC LIMIT 1');
        $stmt->execute(['user_id' => $userId]);
        $id = $stmt->fetchColumn();
        if ($id !== false) return (int) $id;
        if (!$create) return null;
        $stmt = $pdo->prepare('INSERT INTO carts (user_id,session_key) VALUES (:user_id,NULL)');
        $stmt->execute(['user_id' => $userId]);
        return (int) $pdo->lastInsertId();
    }

    $sessionKey = cart_session_key($create);
    if ($sessionKey === null) return null;
    $stmt = $pdo->prepare('SELECT id FROM carts WHERE user_id IS NULL AND session_key = :session_key ORDER BY id DESC LIMIT 1');
    $stmt->execute(['session_key' => $sessionKey]);
    $id = $stmt->fetchColumn();
    if ($id !== false) return (int) $id;
    if (!$create) return null;
    $stmt = $pdo->prepare('INSERT INTO carts (user_id,session_key) VALUES (NULL,:session_key)');
    $stmt->execute(['session_key' => $sessionKey]);
    return (int) $pdo->lastInsertId();
}

function commerce_product_by_id(int $productId): ?array
{
    $stmt = db()->prepare(
        'SELECT p.*, c.name AS category_name, c.slug AS category_slug '
        . 'FROM products p JOIN categories c ON c.id = p.category_id '
        . 'WHERE p.id = :id AND p.is_active = 1 AND c.is_active = 1 LIMIT 1'
    );
    $stmt->execute(['id' => $productId]);
    $product = $stmt->fetch();
    return $product ?: null;
}

function commerce_default_temperature(array $product): string
{
    return (($product['category_slug'] ?? '') === 'iced-coffee' || ($product['slug'] ?? '') === 'cold-brew' || ($product['slug'] ?? '') === 'strawberry-milk')
        ? 'ICED'
        : 'HOT';
}

function commerce_calculate_configuration(int $productId, array $input): array
{
    $product = commerce_product_by_id($productId);
    if (!$product) {
        return ['ok' => false, 'error' => 'This product is currently unavailable.'];
    }

    $sizes = storefront_product_sizes($productId);
    $allAddons = storefront_product_addons($productId);
    $milkAddons = [];
    $regularAddons = [];
    foreach ($allAddons as $addon) {
        if (stripos((string) $addon['name'], 'milk') !== false) {
            $milkAddons[(int) $addon['id']] = $addon;
        } else {
            $regularAddons[(int) $addon['id']] = $addon;
        }
    }

    $unitPrice = (float) $product['base_price'];
    $options = [
        'size' => null,
        'temperature' => null,
        'sugar_level' => null,
        'milk' => null,
        'addons' => [],
    ];

    if ($sizes) {
        $requestedSize = (int) ($input['size_id'] ?? 0);
        $selectedSize = null;
        foreach ($sizes as $size) {
            if ((int) $size['id'] === $requestedSize) {
                $selectedSize = $size;
                break;
            }
        }
        if ($selectedSize === null) {
            $selectedSize = $sizes[0];
        }
        $adjustment = (float) $selectedSize['price_adjustment'];
        $unitPrice += $adjustment;
        $options['size'] = [
            'id' => (int) $selectedSize['id'],
            'name' => (string) $selectedSize['name'],
            'price' => $adjustment,
        ];

        $allowedTemperatures = ['HOT' => 'Hot', 'ICED' => 'Iced'];
        $temperature = strtoupper(trim((string) ($input['temperature'] ?? commerce_default_temperature($product))));
        if (!isset($allowedTemperatures[$temperature])) {
            $temperature = commerce_default_temperature($product);
        }
        $options['temperature'] = $allowedTemperatures[$temperature];

        $allowedSugar = ['0', '25', '50', '75', '100'];
        $sugar = (string) ($input['sugar_level'] ?? '100');
        if (!in_array($sugar, $allowedSugar, true)) $sugar = '100';
        $options['sugar_level'] = $sugar . '%';

        if ($milkAddons) {
            $milkId = (int) ($input['milk_addon_id'] ?? 0);
            if ($milkId > 0 && isset($milkAddons[$milkId])) {
                $milk = $milkAddons[$milkId];
                $milkPrice = (float) $milk['price'];
                $unitPrice += $milkPrice;
                $options['milk'] = ['id' => $milkId, 'name' => (string) $milk['name'], 'price' => $milkPrice];
            } else {
                $options['milk'] = ['id' => 0, 'name' => 'Regular Milk', 'price' => 0.0];
            }
        }
    }

    $addonIds = $input['addon_ids'] ?? [];
    if (!is_array($addonIds)) $addonIds = [$addonIds];
    $addonIds = array_values(array_unique(array_map('intval', $addonIds)));
    sort($addonIds);
    foreach ($addonIds as $addonId) {
        if ($addonId > 0 && isset($regularAddons[$addonId])) {
            $addon = $regularAddons[$addonId];
            $price = (float) $addon['price'];
            $unitPrice += $price;
            $options['addons'][] = ['id' => $addonId, 'name' => (string) $addon['name'], 'price' => $price];
        }
    }

    return [
        'ok' => true,
        'product' => $product,
        'unit_price' => round($unitPrice, 2),
        'options' => $options,
    ];
}

function cart_add(int $productId, array $input): array
{
    $quantity = max(1, min(20, (int) ($input['quantity'] ?? 1)));
    $calculated = commerce_calculate_configuration($productId, $input);
    if (!$calculated['ok']) return $calculated;

    $cartId = cart_id(true);
    if ($cartId === null) return ['ok' => false, 'error' => 'Unable to create your cart.'];

    $optionsJson = json_encode($calculated['options'], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    $stmt = db()->prepare('SELECT id,quantity,options_json FROM cart_items WHERE cart_id = :cart_id AND product_id = :product_id ORDER BY id ASC');
    $stmt->execute(['cart_id' => $cartId, 'product_id' => $productId]);
    foreach ($stmt->fetchAll() as $existing) {
        $existingOptions = json_decode((string) ($existing['options_json'] ?? ''), true);
        if (is_array($existingOptions) && $existingOptions == $calculated['options']) {
            $newQty = min(20, (int) $existing['quantity'] + $quantity);
            db()->prepare('UPDATE cart_items SET quantity = :quantity, unit_price = :unit_price, updated_at = NOW() WHERE id = :id')
                ->execute(['quantity' => $newQty, 'unit_price' => $calculated['unit_price'], 'id' => $existing['id']]);
            return ['ok' => true, 'item_id' => (int) $existing['id'], 'merged' => true];
        }
    }

    $stmt = db()->prepare(
        'INSERT INTO cart_items (cart_id,product_id,quantity,options_json,unit_price) '
        . 'VALUES (:cart_id,:product_id,:quantity,:options_json,:unit_price)'
    );
    $stmt->execute([
        'cart_id' => $cartId,
        'product_id' => $productId,
        'quantity' => $quantity,
        'options_json' => $optionsJson,
        'unit_price' => $calculated['unit_price'],
    ]);
    return ['ok' => true, 'item_id' => (int) db()->lastInsertId(), 'merged' => false];
}

function cart_items(): array
{
    $cartId = cart_id(false);
    if ($cartId === null) return [];
    $stmt = db()->prepare(
        'SELECT ci.*, p.name, p.slug, p.image_path, p.is_active, c.name AS category_name '
        . 'FROM cart_items ci JOIN products p ON p.id = ci.product_id '
        . 'JOIN categories c ON c.id = p.category_id '
        . 'WHERE ci.cart_id = :cart_id ORDER BY ci.created_at DESC, ci.id DESC'
    );
    $stmt->execute(['cart_id' => $cartId]);
    $items = $stmt->fetchAll();
    foreach ($items as &$item) {
        $item['options'] = json_decode((string) ($item['options_json'] ?? ''), true) ?: [];
        $item['line_total'] = round((float) $item['unit_price'] * (int) $item['quantity'], 2);
    }
    unset($item);
    return $items;
}

function cart_count(): int
{
    $cartId = cart_id(false);
    if ($cartId === null) return 0;
    $stmt = db()->prepare('SELECT COALESCE(SUM(quantity),0) FROM cart_items WHERE cart_id = :cart_id');
    $stmt->execute(['cart_id' => $cartId]);
    return (int) $stmt->fetchColumn();
}

function cart_subtotal(array $items = []): float
{
    if (!$items) $items = cart_items();
    $total = 0.0;
    foreach ($items as $item) $total += (float) ($item['line_total'] ?? ((float) $item['unit_price'] * (int) $item['quantity']));
    return round($total, 2);
}

function cart_update_quantity(int $itemId, int $quantity): bool
{
    $cartId = cart_id(false);
    if ($cartId === null) return false;
    if ($quantity <= 0) return cart_remove($itemId);
    $quantity = min(20, $quantity);
    $stmt = db()->prepare('UPDATE cart_items SET quantity = :quantity, updated_at = NOW() WHERE id = :id AND cart_id = :cart_id');
    $stmt->execute(['quantity' => $quantity, 'id' => $itemId, 'cart_id' => $cartId]);
    return $stmt->rowCount() > 0;
}

function cart_remove(int $itemId): bool
{
    $cartId = cart_id(false);
    if ($cartId === null) return false;
    $stmt = db()->prepare('DELETE FROM cart_items WHERE id = :id AND cart_id = :cart_id');
    $stmt->execute(['id' => $itemId, 'cart_id' => $cartId]);
    return $stmt->rowCount() > 0;
}

function cart_clear(): void
{
    $cartId = cart_id(false);
    if ($cartId === null) return;
    db()->prepare('DELETE FROM cart_items WHERE cart_id = :cart_id')->execute(['cart_id' => $cartId]);
}

function cart_option_lines(array $item): array
{
    $options = $item['options'] ?? (json_decode((string) ($item['options_json'] ?? ''), true) ?: []);
    $lines = [];
    if (!empty($options['size']['name'])) $lines[] = (string) $options['size']['name'];
    if (!empty($options['temperature'])) $lines[] = (string) $options['temperature'];
    if (!empty($options['sugar_level'])) $lines[] = 'Sugar ' . $options['sugar_level'];
    if (!empty($options['milk']['name'])) $lines[] = (string) $options['milk']['name'];
    if (!empty($options['addons']) && is_array($options['addons'])) {
        foreach ($options['addons'] as $addon) {
            if (!empty($addon['name'])) $lines[] = (string) $addon['name'];
        }
    }
    return $lines;
}

function cart_merge_guest_to_user(int $userId): void
{
    $sessionKey = cart_session_key(false);
    if ($sessionKey === null) return;

    $pdo = db();
    $stmt = $pdo->prepare('SELECT id FROM carts WHERE user_id IS NULL AND session_key = :key ORDER BY id DESC LIMIT 1');
    $stmt->execute(['key' => $sessionKey]);
    $guestId = $stmt->fetchColumn();
    if ($guestId === false) return;
    $guestId = (int) $guestId;

    $stmt = $pdo->prepare('SELECT id FROM carts WHERE user_id = :uid ORDER BY id DESC LIMIT 1');
    $stmt->execute(['uid' => $userId]);
    $userCartId = $stmt->fetchColumn();

    $pdo->beginTransaction();
    try {
        if ($userCartId === false) {
            $pdo->prepare('UPDATE carts SET user_id = :uid, session_key = NULL, updated_at = NOW() WHERE id = :id')
                ->execute(['uid' => $userId, 'id' => $guestId]);
        } else {
            $userCartId = (int) $userCartId;
            $guestItems = $pdo->prepare('SELECT * FROM cart_items WHERE cart_id = :id');
            $guestItems->execute(['id' => $guestId]);
            $targetStmt = $pdo->prepare('SELECT * FROM cart_items WHERE cart_id = :id');
            $targetStmt->execute(['id' => $userCartId]);
            $targetItems = $targetStmt->fetchAll();

            foreach ($guestItems->fetchAll() as $guestItem) {
                $guestOptions = json_decode((string) ($guestItem['options_json'] ?? ''), true) ?: [];
                $match = null;
                foreach ($targetItems as $target) {
                    $targetOptions = json_decode((string) ($target['options_json'] ?? ''), true) ?: [];
                    if ((int) $target['product_id'] === (int) $guestItem['product_id'] && $targetOptions == $guestOptions) {
                        $match = $target;
                        break;
                    }
                }
                if ($match) {
                    $newQty = min(20, (int) $match['quantity'] + (int) $guestItem['quantity']);
                    $pdo->prepare('UPDATE cart_items SET quantity = :qty, updated_at = NOW() WHERE id = :id')
                        ->execute(['qty' => $newQty, 'id' => $match['id']]);
                } else {
                    $pdo->prepare('UPDATE cart_items SET cart_id = :target, quantity = :quantity WHERE id = :id')
                        ->execute(['target' => $userCartId, 'quantity' => min(20, (int) $guestItem['quantity']), 'id' => $guestItem['id']]);
                }
            }
            $pdo->prepare('DELETE FROM carts WHERE id = :id')->execute(['id' => $guestId]);
        }
        $pdo->commit();
        unset($_SESSION['_cart_key']);
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        throw $e;
    }
}

function favorite_is_saved(int $productId, ?int $userId = null): bool
{
    $userId ??= cart_user_id();
    if (!$userId) return false;
    $stmt = db()->prepare('SELECT 1 FROM favorites WHERE user_id = :uid AND product_id = :pid LIMIT 1');
    $stmt->execute(['uid' => $userId, 'pid' => $productId]);
    return $stmt->fetchColumn() !== false;
}

function favorite_count(?int $userId = null): int
{
    $userId ??= cart_user_id();
    if (!$userId) return 0;
    $stmt = db()->prepare('SELECT COUNT(*) FROM favorites WHERE user_id = :uid');
    $stmt->execute(['uid' => $userId]);
    return (int) $stmt->fetchColumn();
}

function favorite_toggle(int $productId, int $userId): array
{
    if (!commerce_product_by_id($productId)) return ['ok' => false, 'saved' => false];
    if (favorite_is_saved($productId, $userId)) {
        db()->prepare('DELETE FROM favorites WHERE user_id = :uid AND product_id = :pid')
            ->execute(['uid' => $userId, 'pid' => $productId]);
        return ['ok' => true, 'saved' => false];
    }
    db()->prepare('INSERT IGNORE INTO favorites (user_id,product_id) VALUES (:uid,:pid)')
        ->execute(['uid' => $userId, 'pid' => $productId]);
    return ['ok' => true, 'saved' => true];
}

function favorite_products(int $userId): array
{
    $stmt = db()->prepare(
        'SELECT p.*, c.name AS category_name, c.slug AS category_slug, f.created_at AS favorited_at '
        . 'FROM favorites f JOIN products p ON p.id = f.product_id JOIN categories c ON c.id = p.category_id '
        . 'WHERE f.user_id = :uid AND p.is_active = 1 AND c.is_active = 1 ORDER BY f.created_at DESC'
    );
    $stmt->execute(['uid' => $userId]);
    return $stmt->fetchAll();
}
