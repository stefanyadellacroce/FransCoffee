<?php

declare(strict_types=1);

function pos_catalog(): array
{
    $products = storefront_products(['limit' => 100]);
    foreach ($products as &$product) {
        $productId = (int) $product['id'];
        $product['sizes'] = storefront_product_sizes($productId);
        $addons = storefront_product_addons($productId);
        $product['milk_addons'] = [];
        $product['addons'] = [];
        foreach ($addons as $addon) {
            if (stripos((string) $addon['name'], 'milk') !== false) {
                $product['milk_addons'][] = $addon;
            } else {
                $product['addons'][] = $addon;
            }
        }
        $product['default_temperature'] = commerce_default_temperature($product);
    }
    unset($product);
    return $products;
}

function pos_customers(): array
{
    $stmt = db()->query(
        "SELECT id, first_name, last_name, email, phone FROM users "
        . "WHERE role='CUSTOMER' AND status='ACTIVE' ORDER BY first_name, last_name LIMIT 250"
    );
    return $stmt->fetchAll();
}

function pos_payment_methods(): array
{
    $stmt = db()->query(
        "SELECT id, code, name, receiver_name, receiver_number FROM payment_methods "
        . "WHERE is_active=1 AND code IN ('CASH','GCASH','MAYA') ORDER BY FIELD(code,'CASH','GCASH','MAYA')"
    );
    return $stmt->fetchAll();
}

function pos_customer_by_id(?int $customerId): ?array
{
    if (!$customerId) return null;
    $stmt = db()->prepare(
        "SELECT id, first_name, last_name, email, phone FROM users "
        . "WHERE id=:id AND role='CUSTOMER' AND status='ACTIVE' LIMIT 1"
    );
    $stmt->execute(['id' => $customerId]);
    $row = $stmt->fetch();
    return $row ?: null;
}

function pos_reprice_items(array $items): array
{
    if (!$items) return ['ok' => false, 'errors' => ['Add at least one product to the order.']];
    if (count($items) > 60) return ['ok' => false, 'errors' => ['Too many line items in one POS order.']];

    $snapshot = [];
    $subtotal = 0.0;

    foreach ($items as $item) {
        if (!is_array($item)) return ['ok' => false, 'errors' => ['Invalid POS item data.']];
        $productId = (int) ($item['product_id'] ?? 0);
        $quantity = max(1, min(99, (int) ($item['quantity'] ?? 1)));
        $input = [
            'size_id' => (int) ($item['size_id'] ?? 0),
            'temperature' => (string) ($item['temperature'] ?? ''),
            'sugar_level' => (string) ($item['sugar_level'] ?? '100'),
            'milk_addon_id' => (int) ($item['milk_addon_id'] ?? 0),
            'addon_ids' => is_array($item['addon_ids'] ?? null) ? $item['addon_ids'] : [],
        ];
        $calc = commerce_calculate_configuration($productId, $input);
        if (empty($calc['ok'])) {
            return ['ok' => false, 'errors' => [(string) ($calc['error'] ?? 'A selected product is unavailable.')]];
        }
        $unit = round((float) $calc['unit_price'], 2);
        $line = round($unit * $quantity, 2);
        $subtotal += $line;
        $snapshot[] = [
            'product_id' => $productId,
            'product_name' => (string) $calc['product']['name'],
            'quantity' => $quantity,
            'unit_price' => $unit,
            'line_total' => $line,
            'options' => $calc['options'],
        ];
    }

    return ['ok' => true, 'items' => $snapshot, 'subtotal' => round($subtotal, 2)];
}

function pos_discount(float $subtotal, string $type, float $value): array
{
    $type = strtoupper(trim($type));
    $value = max(0.0, $value);
    if ($type === 'PERCENT') {
        $value = min(100.0, $value);
        $amount = round($subtotal * ($value / 100), 2);
        return ['type' => 'PERCENT', 'value' => $value, 'amount' => min($subtotal, $amount)];
    }
    if ($type === 'FIXED') {
        return ['type' => 'FIXED', 'value' => $value, 'amount' => min($subtotal, round($value, 2))];
    }
    return ['type' => 'NONE', 'value' => 0.0, 'amount' => 0.0];
}

function pos_order_number(int $orderId): string
{
    return 'FCS-POS-' . date('Ymd') . '-' . str_pad((string) $orderId, 4, '0', STR_PAD_LEFT);
}

function pos_place_order(int $cashierId, array $input): array
{
    $rawItems = (string) ($input['items_json'] ?? '');
    $decoded = json_decode($rawItems, true);
    if (!is_array($decoded)) return ['ok' => false, 'errors' => ['The POS cart could not be read. Please rebuild the order.']];

    $repriced = pos_reprice_items($decoded);
    if (empty($repriced['ok'])) return $repriced;

    $customerId = (int) ($input['customer_id'] ?? 0);
    $customer = $customerId > 0 ? pos_customer_by_id($customerId) : null;
    if ($customerId > 0 && !$customer) return ['ok' => false, 'errors' => ['The selected customer is no longer available.']];

    $discount = pos_discount(
        (float) $repriced['subtotal'],
        (string) ($input['discount_type'] ?? 'NONE'),
        (float) ($input['discount_value'] ?? 0)
    );
    $subtotal = (float) $repriced['subtotal'];
    $total = round(max(0, $subtotal - (float) $discount['amount']), 2);

    $paymentCode = strtoupper(trim((string) ($input['payment_method'] ?? 'CASH')));
    if (!in_array($paymentCode, ['CASH', 'GCASH', 'MAYA'], true)) {
        return ['ok' => false, 'errors' => ['Choose Cash, GCash, or Maya.']];
    }
    $method = checkout_payment_method($paymentCode);
    if (!$method) return ['ok' => false, 'errors' => ['That payment method is currently unavailable.']];

    $cashReceived = null;
    $changeDue = 0.0;
    $reference = trim((string) ($input['reference_number'] ?? ''));
    if ($paymentCode === 'CASH') {
        $cashReceived = round((float) ($input['cash_received'] ?? 0), 2);
        if ($cashReceived < $total) return ['ok' => false, 'errors' => ['Cash received must be at least the order total.']];
        $changeDue = round($cashReceived - $total, 2);
        $reference = '';
    } else {
        if ($reference === '' || mb_strlen($reference) > 120) {
            return ['ok' => false, 'errors' => ['Enter the GCash/Maya reference number before completing the sale.']];
        }
    }

    $notes = trim((string) ($input['order_notes'] ?? ''));
    if (mb_strlen($notes) > 500) return ['ok' => false, 'errors' => ['Order notes must be 500 characters or fewer.']];

    $contactName = $customer ? trim((string) ($customer['first_name'] . ' ' . $customer['last_name'])) : 'Walk-in Customer';
    $contactEmail = $customer['email'] ?? null;
    $contactPhone = $customer['phone'] ?? null;
    $pdo = db();
    $pdo->beginTransaction();

    try {
        $temp = 'TEMP-POS-' . bin2hex(random_bytes(10));
        $stmt = $pdo->prepare(
            "INSERT INTO orders (order_number,user_id,created_by,order_source,fulfillment_type,status,contact_name,contact_email,contact_phone,subtotal,discount_amount,delivery_fee,total_amount,notes) "
            . "VALUES (:number,:user_id,:created_by,'POS','PICKUP','CONFIRMED',:contact_name,:contact_email,:contact_phone,:subtotal,:discount,0,:total,:notes)"
        );
        $stmt->execute([
            'number' => $temp,
            'user_id' => $customer ? (int) $customer['id'] : null,
            'created_by' => $cashierId,
            'contact_name' => $contactName,
            'contact_email' => $contactEmail,
            'contact_phone' => $contactPhone,
            'subtotal' => $subtotal,
            'discount' => $discount['amount'],
            'total' => $total,
            'notes' => $notes !== '' ? $notes : null,
        ]);
        $orderId = (int) $pdo->lastInsertId();
        $orderNumber = pos_order_number($orderId);
        $pdo->prepare('UPDATE orders SET order_number=:number WHERE id=:id')->execute(['number' => $orderNumber, 'id' => $orderId]);

        $itemStmt = $pdo->prepare(
            'INSERT INTO order_items (order_id,product_id,product_name,unit_price,quantity,line_total) '
            . 'VALUES (:order_id,:product_id,:name,:unit_price,:quantity,:line_total)'
        );
        foreach ($repriced['items'] as $item) {
            $itemStmt->execute([
                'order_id' => $orderId,
                'product_id' => $item['product_id'],
                'name' => $item['product_name'],
                'unit_price' => $item['unit_price'],
                'quantity' => $item['quantity'],
                'line_total' => $item['line_total'],
            ]);
            checkout_insert_order_options($pdo, (int) $pdo->lastInsertId(), $item['options']);
        }

        $pdo->prepare(
            "INSERT INTO order_status_history (order_id,status,changed_by,note) VALUES (:order_id,'CONFIRMED',:staff,'Paid at POS; awaiting preparation')"
        )->execute(['order_id' => $orderId, 'staff' => $cashierId]);

        $paymentTemp = 'TEMP-PAY-' . bin2hex(random_bytes(10));
        $stmt = $pdo->prepare(
            "INSERT INTO payments (payment_number,order_id,payment_method_id,amount,status,reference_number,cash_received,change_due,verified_by,verified_at) "
            . "VALUES (:number,:order_id,:method_id,:amount,'PAID',:reference,:cash_received,:change_due,:verified_by,NOW())"
        );
        $stmt->execute([
            'number' => $paymentTemp,
            'order_id' => $orderId,
            'method_id' => $method['id'],
            'amount' => $total,
            'reference' => $reference !== '' ? $reference : null,
            'cash_received' => $cashReceived,
            'change_due' => $changeDue,
            'verified_by' => $cashierId,
        ]);
        $paymentId = (int) $pdo->lastInsertId();
        $paymentNumber = checkout_payment_number($paymentId);
        $pdo->prepare('UPDATE payments SET payment_number=:number WHERE id=:id')->execute(['number' => $paymentNumber, 'id' => $paymentId]);

        if ($customer) {
            $notifyStmt = $pdo->prepare('SELECT COALESCE(notifications_enabled,1) FROM customer_profiles WHERE user_id=:user_id LIMIT 1');
            $notifyStmt->execute(['user_id' => $customer['id']]);
            $enabled = $notifyStmt->fetchColumn();
            if ($enabled === false || (int) $enabled === 1) {
                $pdo->prepare(
                    "INSERT INTO notifications (user_id,title,message,target_url) VALUES (:user_id,'POS order received',:message,:target)"
                )->execute([
                    'user_id' => $customer['id'],
                    'message' => 'Your in-store order ' . $orderNumber . ' has been paid and confirmed.',
                    'target' => 'customer/order-details.php?order=' . rawurlencode($orderNumber),
                ]);
            }
        }

        $pdo->commit();
        log_activity($cashierId, 'POS_SALE', 'orders', $orderId, [
            'order_number' => $orderNumber,
            'payment_method' => $paymentCode,
            'subtotal' => $subtotal,
            'discount' => $discount['amount'],
            'discount_type' => $discount['type'],
            'discount_value' => $discount['value'],
            'total' => $total,
            'customer_id' => $customer['id'] ?? null,
        ]);
        return ['ok' => true, 'order_id' => $orderId, 'order_number' => $orderNumber];
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        if (app_is_debug()) return ['ok' => false, 'errors' => ['POS sale failed: ' . $e->getMessage()]];
        return ['ok' => false, 'errors' => ['The POS sale could not be completed. Please try again.']];
    }
}

function pos_receipt(string $orderNumber): ?array
{
    $stmt = db()->prepare(
        "SELECT o.*, p.payment_number,p.amount AS payment_amount,p.status AS payment_status,p.reference_number,p.cash_received,p.change_due, "
        . "pm.code AS payment_code,pm.name AS payment_method, "
        . "CONCAT(cu.first_name,' ',cu.last_name) AS customer_name, cu.email AS customer_email, cu.phone AS customer_phone, "
        . "CONCAT(ca.first_name,' ',ca.last_name) AS cashier_name "
        . "FROM orders o "
        . "JOIN payments p ON p.order_id=o.id "
        . "JOIN payment_methods pm ON pm.id=p.payment_method_id "
        . "LEFT JOIN users cu ON cu.id=o.user_id "
        . "LEFT JOIN users ca ON ca.id=o.created_by "
        . "WHERE o.order_number=:number AND o.order_source='POS' LIMIT 1"
    );
    $stmt->execute(['number' => $orderNumber]);
    $order = $stmt->fetch();
    if (!$order) return null;

    $itemStmt = db()->prepare('SELECT * FROM order_items WHERE order_id=:order_id ORDER BY id ASC');
    $itemStmt->execute(['order_id' => $order['id']]);
    $items = $itemStmt->fetchAll();
    $optionStmt = db()->prepare('SELECT option_name,option_value,price_adjustment FROM order_item_options WHERE order_item_id=:item_id ORDER BY id ASC');
    foreach ($items as &$item) {
        $optionStmt->execute(['item_id' => $item['id']]);
        $item['options'] = $optionStmt->fetchAll();
    }
    unset($item);
    $order['items'] = $items;
    return $order;
}
