<?php

declare(strict_types=1);

function checkout_customer(int $userId): ?array
{
    $stmt = db()->prepare('SELECT id, first_name, last_name, email, phone FROM users WHERE id = :id AND role = \'CUSTOMER\' AND status = \'ACTIVE\' LIMIT 1');
    $stmt->execute(['id' => $userId]);
    $row = $stmt->fetch();
    return $row ?: null;
}

function customer_addresses(int $userId): array
{
    $stmt = db()->prepare('SELECT * FROM addresses WHERE user_id = :user_id ORDER BY is_default DESC, updated_at DESC, id DESC');
    $stmt->execute(['user_id' => $userId]);
    return $stmt->fetchAll();
}

function checkout_payment_methods(): array
{
    return db()->query("SELECT * FROM payment_methods WHERE is_active = 1 AND code <> 'CASH' ORDER BY is_online ASC, id ASC")->fetchAll();
}

function checkout_payment_method(string $code): ?array
{
    $stmt = db()->prepare('SELECT * FROM payment_methods WHERE code = :code AND is_active = 1 LIMIT 1');
    $stmt->execute(['code' => strtoupper(trim($code))]);
    $row = $stmt->fetch();
    return $row ?: null;
}

function checkout_method_allowed(string $code, string $fulfillment): bool
{
    $code = strtoupper($code);
    $fulfillment = strtoupper($fulfillment);
    if (in_array($code, ['GCASH', 'MAYA'], true)) return true;
    if ($fulfillment === 'PICKUP') return $code === 'COP';
    if ($fulfillment === 'DELIVERY') return $code === 'COD';
    return false;
}

function checkout_delivery_fee(string $fulfillment): float
{
    if (strtoupper($fulfillment) !== 'DELIVERY') return 0.0;
    return max(0.0, (float) shop_setting('delivery_fee', '50.00'));
}

function checkout_reprice_cart(array $items): array
{
    $snapshot = [];
    $subtotal = 0.0;

    foreach ($items as $item) {
        $stored = is_array($item['options'] ?? null) ? $item['options'] : [];
        $temperature = strtoupper((string) ($stored['temperature'] ?? 'HOT')) === 'ICED' ? 'ICED' : 'HOT';
        $sugar = rtrim((string) ($stored['sugar_level'] ?? '100%'), '%');
        $addonIds = [];
        foreach (($stored['addons'] ?? []) as $addon) {
            if (is_array($addon) && !empty($addon['id'])) $addonIds[] = (int) $addon['id'];
        }

        $calc = commerce_calculate_configuration((int) $item['product_id'], [
            'size_id' => (int) ($stored['size']['id'] ?? 0),
            'temperature' => $temperature,
            'sugar_level' => $sugar,
            'milk_addon_id' => (int) ($stored['milk']['id'] ?? 0),
            'addon_ids' => $addonIds,
        ]);
        if (!$calc['ok']) {
            return ['ok' => false, 'error' => 'One or more items in your cart are no longer available. Please review your cart.'];
        }

        $quantity = (int) $item['quantity'];
        if ($quantity < 1 || $quantity > 20) {
            return ['ok' => false, 'error' => 'An item in your cart has an invalid quantity. Please update it before checkout.'];
        }
        $unitPrice = round((float) $calc['unit_price'], 2);
        $lineTotal = round($unitPrice * $quantity, 2);
        $subtotal += $lineTotal;
        $snapshot[] = [
            'product_id' => (int) $item['product_id'],
            'product_name' => (string) $calc['product']['name'],
            'quantity' => $quantity,
            'unit_price' => $unitPrice,
            'line_total' => $lineTotal,
            'options' => $calc['options'],
        ];
    }

    return ['ok' => true, 'items' => $snapshot, 'subtotal' => round($subtotal, 2)];
}

function checkout_address_from_input(array $input): array
{
    return [
        'label' => trim((string) ($input['address_label'] ?? 'Home')),
        'street' => trim((string) ($input['street'] ?? '')),
        'barangay' => trim((string) ($input['barangay'] ?? '')),
        'city' => trim((string) ($input['city'] ?? '')),
        'province' => trim((string) ($input['province'] ?? '')),
        'postal_code' => trim((string) ($input['postal_code'] ?? '')),
        'landmark' => trim((string) ($input['landmark'] ?? '')),
        'notes' => trim((string) ($input['delivery_notes'] ?? '')),
    ];
}

function checkout_validate_address(array $address): array
{
    $errors = [];
    if ($address['label'] === '' || mb_strlen($address['label']) > 60) $errors[] = 'Enter a valid address label.';
    if ($address['street'] === '' || mb_strlen($address['street']) > 190) $errors[] = 'Enter the street or house/building details.';
    if ($address['barangay'] === '' || mb_strlen($address['barangay']) > 120) $errors[] = 'Enter the barangay.';
    if ($address['city'] === '' || mb_strlen($address['city']) > 120) $errors[] = 'Enter the city or municipality.';
    if ($address['province'] === '' || mb_strlen($address['province']) > 120) $errors[] = 'Enter the province.';
    if ($address['postal_code'] !== '' && mb_strlen($address['postal_code']) > 12) $errors[] = 'Enter a valid postal code.';
    if ($address['landmark'] !== '' && mb_strlen($address['landmark']) > 190) $errors[] = 'Landmark is too long.';
    if ($address['notes'] !== '' && mb_strlen($address['notes']) > 255) $errors[] = 'Delivery notes must be 255 characters or fewer.';
    return $errors;
}

function checkout_validate_payment_proof(?array $file, bool $required): array
{
    if (!$file || ($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
        return $required ? ['ok' => false, 'error' => 'Upload your payment proof for GCash or Maya.'] : ['ok' => true, 'file' => null];
    }
    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) return ['ok' => false, 'error' => 'The payment proof could not be uploaded.'];
    if ((int) ($file['size'] ?? 0) <= 0 || (int) $file['size'] > 5 * 1024 * 1024) return ['ok' => false, 'error' => 'Payment proof must be 5 MB or smaller.'];

    $tmp = (string) ($file['tmp_name'] ?? '');
    if ($tmp === '' || !is_uploaded_file($tmp)) return ['ok' => false, 'error' => 'Invalid payment proof upload.'];
    $mime = (new finfo(FILEINFO_MIME_TYPE))->file($tmp) ?: '';
    $allowed = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];
    if (!isset($allowed[$mime])) return ['ok' => false, 'error' => 'Payment proof must be a JPG, PNG, or WEBP image.'];
    return ['ok' => true, 'file' => ['tmp' => $tmp, 'extension' => $allowed[$mime]]];
}

function checkout_order_number(int $orderId): string
{
    return 'FCS-ORD-' . date('Ymd') . '-' . str_pad((string) $orderId, 4, '0', STR_PAD_LEFT);
}

function checkout_payment_number(int $paymentId): string
{
    return 'FCS-PAY-' . date('Ymd') . '-' . str_pad((string) $paymentId, 4, '0', STR_PAD_LEFT);
}

function checkout_insert_order_options(PDO $pdo, int $orderItemId, array $options): void
{
    $rows = [];
    if (!empty($options['size'])) $rows[] = ['Size', (string) $options['size']['name'], (float) $options['size']['price']];
    if (!empty($options['temperature'])) $rows[] = ['Temperature', (string) $options['temperature'], 0.0];
    if (!empty($options['sugar_level'])) $rows[] = ['Sugar Level', (string) $options['sugar_level'], 0.0];
    if (!empty($options['milk'])) $rows[] = ['Milk', (string) $options['milk']['name'], (float) $options['milk']['price']];
    foreach (($options['addons'] ?? []) as $addon) {
        $rows[] = ['Add-on', (string) $addon['name'], (float) $addon['price']];
    }
    if (!$rows) return;
    $stmt = $pdo->prepare('INSERT INTO order_item_options (order_item_id,option_name,option_value,price_adjustment) VALUES (:item,:name,:value,:price)');
    foreach ($rows as [$name, $value, $price]) {
        $stmt->execute(['item' => $orderItemId, 'name' => $name, 'value' => $value, 'price' => $price]);
    }
}

function checkout_place_order(int $userId, array $input, array $files): array
{
    $customer = checkout_customer($userId);
    if (!$customer) return ['ok' => false, 'errors' => ['Your customer account could not be verified.']];

    $cart = cart_items();
    if (!$cart) return ['ok' => false, 'errors' => ['Your cart is empty.']];
    $repriced = checkout_reprice_cart($cart);
    if (!$repriced['ok']) return ['ok' => false, 'errors' => [$repriced['error']]];

    $fulfillment = strtoupper(trim((string) ($input['fulfillment_type'] ?? 'PICKUP')));
    if (!in_array($fulfillment, ['PICKUP', 'DELIVERY'], true)) $fulfillment = 'PICKUP';
    if ($fulfillment === 'PICKUP' && shop_setting('pickup_enabled', '1') !== '1') return ['ok' => false, 'errors' => ['Store pickup is currently unavailable.']];
    if ($fulfillment === 'DELIVERY' && shop_setting('delivery_enabled', '1') !== '1') return ['ok' => false, 'errors' => ['Delivery is currently unavailable.']];

    $contactName = trim((string) ($input['contact_name'] ?? ''));
    $contactEmail = strtolower(trim((string) ($input['contact_email'] ?? '')));
    $contactPhone = trim((string) ($input['contact_phone'] ?? ''));
    $notes = trim((string) ($input['order_notes'] ?? ''));
    $errors = [];
    if ($contactName === '' || mb_strlen($contactName) > 160) $errors[] = 'Enter the customer name.';
    if (!filter_var($contactEmail, FILTER_VALIDATE_EMAIL) || mb_strlen($contactEmail) > 190) $errors[] = 'Enter a valid contact email.';
    if ($contactPhone === '' || mb_strlen($contactPhone) > 30) $errors[] = 'Enter a contact phone number.';
    if (mb_strlen($notes) > 1000) $errors[] = 'Order notes must be 1000 characters or fewer.';

    $address = null;
    $addressId = null;
    if ($fulfillment === 'DELIVERY') {
        $selectedAddressId = max(0, (int) ($input['saved_address_id'] ?? 0));
        if ($selectedAddressId > 0) {
            $stmt = db()->prepare('SELECT * FROM addresses WHERE id = :id AND user_id = :user_id LIMIT 1');
            $stmt->execute(['id' => $selectedAddressId, 'user_id' => $userId]);
            $saved = $stmt->fetch();
            if ($saved) {
                $addressId = (int) $saved['id'];
                $address = [
                    'label' => (string) $saved['label'], 'street' => (string) $saved['street'],
                    'barangay' => (string) $saved['barangay'], 'city' => (string) $saved['city'],
                    'province' => (string) $saved['province'], 'postal_code' => (string) ($saved['postal_code'] ?? ''),
                    'landmark' => (string) ($saved['landmark'] ?? ''), 'notes' => (string) ($saved['notes'] ?? ''),
                ];
            } else {
                $errors[] = 'The selected delivery address could not be found.';
            }
        } else {
            $address = checkout_address_from_input($input);
            $errors = array_merge($errors, checkout_validate_address($address));
        }
    }

    $methodCode = strtoupper(trim((string) ($input['payment_method'] ?? '')));
    $method = checkout_payment_method($methodCode);
    if (!$method || !checkout_method_allowed($methodCode, $fulfillment)) $errors[] = 'Choose an available payment method for this order.';
    $isOnline = $method && (int) $method['is_online'] === 1;
    $reference = trim((string) ($input['reference_number'] ?? ''));
    if ($isOnline && ($reference === '' || mb_strlen($reference) > 120)) $errors[] = 'Enter the GCash/Maya reference number.';
    $proof = checkout_validate_payment_proof($files['payment_proof'] ?? null, (bool) $isOnline);
    if (!$proof['ok']) $errors[] = $proof['error'];
    if ($errors) return ['ok' => false, 'errors' => $errors];

    $subtotal = (float) $repriced['subtotal'];
    $minimumOrder = max(0.0, (float) shop_setting('minimum_order', '0.00'));
    if ($minimumOrder > 0 && $subtotal < $minimumOrder) {
        return ['ok' => false, 'errors' => ['The minimum order is ' . money($minimumOrder) . '. Add more items to continue.']];
    }
    $deliveryFee = checkout_delivery_fee($fulfillment);
    $promoCode = strtoupper(trim((string) ($input['promo_code'] ?? '')));
    $promoResult = promotion_validate($promoCode, $subtotal, $userId);
    if (!$promoResult['ok']) return ['ok' => false, 'errors' => [$promoResult['message']]];
    $discount = (float) ($promoResult['discount'] ?? 0);
    $total = round(max(0, $subtotal - $discount) + $deliveryFee, 2);
    $pdo = db();
    $movedPath = null;

    $pdo->beginTransaction();
    try {
        $lockedPromo = promotion_validate($promoCode, $subtotal, $userId, true, $pdo);
        if (!$lockedPromo['ok']) throw new RuntimeException($lockedPromo['message']);
        $promoRow = $lockedPromo['promotion'] ?? null;
        $discount = (float) ($lockedPromo['discount'] ?? 0);
        $total = round(max(0, $subtotal - $discount) + $deliveryFee, 2);
        if ($fulfillment === 'DELIVERY' && $addressId === null && !empty($input['save_address'])) {
            if (!empty($input['set_default_address'])) {
                $pdo->prepare('UPDATE addresses SET is_default = 0 WHERE user_id = :user_id')->execute(['user_id' => $userId]);
            }
            $stmt = $pdo->prepare('INSERT INTO addresses (user_id,label,street,barangay,city,province,postal_code,landmark,notes,is_default) VALUES (:user_id,:label,:street,:barangay,:city,:province,:postal_code,:landmark,:notes,:is_default)');
            $stmt->execute([
                'user_id' => $userId, 'label' => $address['label'], 'street' => $address['street'],
                'barangay' => $address['barangay'], 'city' => $address['city'], 'province' => $address['province'],
                'postal_code' => $address['postal_code'] ?: null, 'landmark' => $address['landmark'] ?: null,
                'notes' => $address['notes'] ?: null, 'is_default' => !empty($input['set_default_address']) ? 1 : 0,
            ]);
            $addressId = (int) $pdo->lastInsertId();
        }

        $tempOrderNumber = 'TEMP-' . bin2hex(random_bytes(12));
        $stmt = $pdo->prepare('INSERT INTO orders (order_number,user_id,order_source,fulfillment_type,status,contact_name,contact_email,contact_phone,delivery_address_id,delivery_address_json,subtotal,discount_amount,delivery_fee,total_amount,notes) VALUES (:number,:user_id,\'ONLINE\',:fulfillment,\'PENDING\',:contact_name,:contact_email,:contact_phone,:address_id,:address_json,:subtotal,:discount,:delivery_fee,:total,:notes)');
        $stmt->execute([
            'number' => $tempOrderNumber, 'user_id' => $userId, 'fulfillment' => $fulfillment,
            'contact_name' => $contactName, 'contact_email' => $contactEmail, 'contact_phone' => $contactPhone,
            'address_id' => $addressId, 'address_json' => $address ? json_encode($address, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : null,
            'subtotal' => $subtotal, 'discount' => $discount, 'delivery_fee' => $deliveryFee, 'total' => $total,
            'notes' => $notes !== '' ? $notes : null,
        ]);
        $orderId = (int) $pdo->lastInsertId();
        $orderNumber = checkout_order_number($orderId);
        $pdo->prepare('UPDATE orders SET order_number = :number WHERE id = :id')->execute(['number' => $orderNumber, 'id' => $orderId]);

        $itemStmt = $pdo->prepare('INSERT INTO order_items (order_id,product_id,product_name,unit_price,quantity,line_total) VALUES (:order_id,:product_id,:name,:unit_price,:quantity,:line_total)');
        foreach ($repriced['items'] as $item) {
            $itemStmt->execute([
                'order_id' => $orderId, 'product_id' => $item['product_id'], 'name' => $item['product_name'],
                'unit_price' => $item['unit_price'], 'quantity' => $item['quantity'], 'line_total' => $item['line_total'],
            ]);
            checkout_insert_order_options($pdo, (int) $pdo->lastInsertId(), $item['options']);
        }

        $pdo->prepare('INSERT INTO order_status_history (order_id,status,changed_by,note) VALUES (:order_id,\'PENDING\',:user_id,\'Order placed online\')')
            ->execute(['order_id' => $orderId, 'user_id' => $userId]);

        if (!empty($promoRow['id']) && $discount > 0) {
            $pdo->prepare('INSERT INTO promotion_usages (promotion_id,user_id,order_id,discount_amount) VALUES (:promotion_id,:user_id,:order_id,:discount)')
                ->execute(['promotion_id' => (int) $promoRow['id'], 'user_id' => $userId, 'order_id' => $orderId, 'discount' => $discount]);
        }

        $tempPaymentNumber = 'TEMP-' . bin2hex(random_bytes(12));
        $paymentStatus = $isOnline ? 'PENDING' : 'UNPAID';
        $stmt = $pdo->prepare('INSERT INTO payments (payment_number,order_id,payment_method_id,amount,status,reference_number) VALUES (:number,:order_id,:method_id,:amount,:status,:reference)');
        $stmt->execute([
            'number' => $tempPaymentNumber, 'order_id' => $orderId, 'method_id' => $method['id'],
            'amount' => $total, 'status' => $paymentStatus, 'reference' => $isOnline ? $reference : null,
        ]);
        $paymentId = (int) $pdo->lastInsertId();
        $paymentNumber = checkout_payment_number($paymentId);
        $pdo->prepare('UPDATE payments SET payment_number = :number WHERE id = :id')->execute(['number' => $paymentNumber, 'id' => $paymentId]);

        if ($isOnline && !empty($proof['file'])) {
            $uploadDir = dirname(__DIR__) . '/uploads/payments';
            if (!is_dir($uploadDir) && !mkdir($uploadDir, 0775, true) && !is_dir($uploadDir)) throw new RuntimeException('Unable to create payment upload directory.');
            $filename = 'payment-' . $paymentId . '-' . bin2hex(random_bytes(8)) . '.' . $proof['file']['extension'];
            $absolute = $uploadDir . '/' . $filename;
            if (!move_uploaded_file($proof['file']['tmp'], $absolute)) throw new RuntimeException('Unable to store the payment proof.');
            $movedPath = $absolute;
            $relative = 'uploads/payments/' . $filename;
            $pdo->prepare('INSERT INTO payment_proofs (payment_id,file_path) VALUES (:payment_id,:path)')->execute(['payment_id' => $paymentId, 'path' => $relative]);
        }

        $cartId = cart_id(false);
        if ($cartId !== null) $pdo->prepare('DELETE FROM cart_items WHERE cart_id = :cart_id')->execute(['cart_id' => $cartId]);

        $notifyStmt = $pdo->prepare('SELECT COALESCE(notifications_enabled,1) FROM customer_profiles WHERE user_id=:user_id LIMIT 1');
        $notifyStmt->execute(['user_id' => $userId]);
        $notifyValue = $notifyStmt->fetchColumn();
        if ($notifyValue === false || (int) $notifyValue === 1) {
            $pdo->prepare("INSERT INTO notifications (user_id,title,message,target_url) VALUES (:user_id,'Order received',:message,:target_url)")
                ->execute(['user_id' => $userId, 'message' => 'We received ' . $orderNumber . '. You can track its progress from your account.', 'target_url' => 'customer/order-details.php?order=' . rawurlencode($orderNumber)]);
        }

        $pdo->commit();
        log_activity($userId, 'CREATE_ORDER', 'orders', $orderId, ['order_number' => $orderNumber, 'total' => $total, 'fulfillment' => $fulfillment, 'promo_code' => $promoCode ?: null, 'discount' => $discount]);
        return ['ok' => true, 'order_id' => $orderId, 'order_number' => $orderNumber, 'payment_id' => $paymentId];
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        if ($movedPath && is_file($movedPath)) @unlink($movedPath);
        if (app_is_debug()) return ['ok' => false, 'errors' => ['Order creation failed: ' . $e->getMessage()]];
        return ['ok' => false, 'errors' => ['We could not place your order. Please try again.']];
    }
}

function checkout_order_for_customer(string $orderNumber, int $userId): ?array
{
    $stmt = db()->prepare('SELECT o.*, p.payment_number,p.amount AS payment_amount,p.status AS payment_status,p.reference_number,pm.code AS payment_code,pm.name AS payment_method,pm.receiver_name,pm.receiver_number FROM orders o LEFT JOIN payments p ON p.order_id=o.id LEFT JOIN payment_methods pm ON pm.id=p.payment_method_id WHERE o.order_number=:order_number AND o.user_id=:user_id LIMIT 1');
    $stmt->execute(['order_number' => $orderNumber, 'user_id' => $userId]);
    $order = $stmt->fetch();
    if (!$order) return null;
    $stmt = db()->prepare('SELECT oi.* FROM order_items oi WHERE oi.order_id=:order_id ORDER BY oi.id');
    $stmt->execute(['order_id' => $order['id']]);
    $items = $stmt->fetchAll();
    $optionStmt = db()->prepare('SELECT option_name,option_value,price_adjustment FROM order_item_options WHERE order_item_id=:item_id ORDER BY id');
    foreach ($items as &$item) {
        $optionStmt->execute(['item_id' => $item['id']]);
        $item['options'] = $optionStmt->fetchAll();
    }
    unset($item);
    $order['items'] = $items;
    $order['delivery_address'] = $order['delivery_address_json'] ? (json_decode((string) $order['delivery_address_json'], true) ?: null) : null;
    return $order;
}
