<?php

declare(strict_types=1);

function ops_status_label(string $status): string
{
    return match (strtoupper($status)) {
        'PENDING' => 'Pending',
        'CONFIRMED' => 'Confirmed',
        'PREPARING' => 'Preparing',
        'READY' => 'Ready',
        'OUT_FOR_DELIVERY' => 'Out for delivery',
        'COMPLETED' => 'Completed',
        'CANCELLED' => 'Cancelled',
        default => ucwords(strtolower(str_replace('_', ' ', $status))),
    };
}

function ops_order_counts(): array
{
    $counts = array_fill_keys(['ALL','PENDING','CONFIRMED','PREPARING','READY','OUT_FOR_DELIVERY','COMPLETED','CANCELLED'], 0);
    $rows = db()->query('SELECT status, COUNT(*) AS total FROM orders GROUP BY status')->fetchAll();
    foreach ($rows as $row) {
        $status = strtoupper((string)$row['status']);
        $counts[$status] = (int)$row['total'];
        $counts['ALL'] += (int)$row['total'];
    }
    return $counts;
}

function ops_orders(array $filters = []): array
{
    $status = strtoupper(trim((string)($filters['status'] ?? 'ALL')));
    $source = strtoupper(trim((string)($filters['source'] ?? 'ALL')));
    $payment = strtoupper(trim((string)($filters['payment'] ?? 'ALL')));
    $search = trim((string)($filters['q'] ?? ''));

    $where = [];
    $params = [];
    if ($status !== 'ALL' && in_array($status, ['PENDING','CONFIRMED','PREPARING','READY','OUT_FOR_DELIVERY','COMPLETED','CANCELLED'], true)) {
        $where[] = 'o.status = :status';
        $params['status'] = $status;
    }
    if ($source !== 'ALL' && in_array($source, ['ONLINE','POS'], true)) {
        $where[] = 'o.order_source = :source';
        $params['source'] = $source;
    }
    if ($payment !== 'ALL' && in_array($payment, ['UNPAID','PENDING','PAID','FAILED','REFUNDED'], true)) {
        $where[] = 'p.status = :payment';
        $params['payment'] = $payment;
    }
    if ($search !== '') {
        sql_add_search_filter($where, $params, ['o.order_number', 'o.contact_name', 'o.contact_email', 'o.contact_phone'], $search, 'search');
    }

    $sql = "SELECT o.*, p.id AS payment_id,p.status AS payment_status,p.reference_number,pm.code AS payment_code,pm.name AS payment_method,pm.is_online, "
         . "CONCAT(u.first_name,' ',u.last_name) AS account_name "
         . "FROM orders o "
         . "LEFT JOIN payments p ON p.order_id=o.id "
         . "LEFT JOIN payment_methods pm ON pm.id=p.payment_method_id "
         . "LEFT JOIN users u ON u.id=o.user_id ";
    if ($where) $sql .= 'WHERE ' . implode(' AND ', $where) . ' ';
    $sql .= 'ORDER BY FIELD(o.status,\'PENDING\',\'CONFIRMED\',\'PREPARING\',\'READY\',\'OUT_FOR_DELIVERY\',\'COMPLETED\',\'CANCELLED\'), o.placed_at DESC LIMIT 250';
    $stmt = db()->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll();
}

function ops_order_detail(string $orderNumber): ?array
{
    $stmt = db()->prepare(
        "SELECT o.*, p.id AS payment_id,p.payment_number,p.amount AS payment_amount,p.status AS payment_status,p.reference_number,p.cash_received,p.change_due,p.verified_by,p.verified_at,p.verification_note, "
        . "pm.code AS payment_code,pm.name AS payment_method,pm.is_online,pm.receiver_name,pm.receiver_number, "
        . "CONCAT(u.first_name,' ',u.last_name) AS account_name,u.email AS account_email, "
        . "CONCAT(v.first_name,' ',v.last_name) AS verifier_name, CONCAT(c.first_name,' ',c.last_name) AS creator_name "
        . "FROM orders o "
        . "LEFT JOIN payments p ON p.order_id=o.id "
        . "LEFT JOIN payment_methods pm ON pm.id=p.payment_method_id "
        . "LEFT JOIN users u ON u.id=o.user_id "
        . "LEFT JOIN users v ON v.id=p.verified_by "
        . "LEFT JOIN users c ON c.id=o.created_by "
        . "WHERE o.order_number=:number LIMIT 1"
    );
    $stmt->execute(['number' => $orderNumber]);
    $order = $stmt->fetch();
    if (!$order) return null;

    $itemStmt = db()->prepare('SELECT * FROM order_items WHERE order_id=:order_id ORDER BY id');
    $itemStmt->execute(['order_id' => $order['id']]);
    $items = $itemStmt->fetchAll();
    $optionStmt = db()->prepare('SELECT option_name,option_value,price_adjustment FROM order_item_options WHERE order_item_id=:item_id ORDER BY id');
    foreach ($items as &$item) {
        $optionStmt->execute(['item_id' => $item['id']]);
        $item['options'] = $optionStmt->fetchAll();
    }
    unset($item);
    $order['items'] = $items;

    $historyStmt = db()->prepare("SELECT h.*,CONCAT(u.first_name,' ',u.last_name) AS changed_by_name FROM order_status_history h LEFT JOIN users u ON u.id=h.changed_by WHERE h.order_id=:order_id ORDER BY h.created_at ASC,h.id ASC");
    $historyStmt->execute(['order_id' => $order['id']]);
    $order['history'] = $historyStmt->fetchAll();

    $proofStmt = db()->prepare('SELECT * FROM payment_proofs WHERE payment_id=:payment_id ORDER BY uploaded_at DESC,id DESC LIMIT 1');
    $proofStmt->execute(['payment_id' => (int)($order['payment_id'] ?? 0)]);
    $order['payment_proof'] = $proofStmt->fetch() ?: null;
    $order['delivery_address'] = !empty($order['delivery_address_json']) ? (json_decode((string)$order['delivery_address_json'], true) ?: null) : null;
    return $order;
}

function ops_allowed_transitions(array $order): array
{
    $status = strtoupper((string)($order['status'] ?? ''));
    $fulfillment = strtoupper((string)($order['fulfillment_type'] ?? 'PICKUP'));
    $paymentStatus = strtoupper((string)($order['payment_status'] ?? ''));
    $isOnline = (int)($order['is_online'] ?? 0) === 1;

    if ($paymentStatus === 'FAILED' && in_array($status, ['PENDING','CONFIRMED','PREPARING'], true)) {
        return ['CANCELLED'];
    }
    if ($status === 'PENDING' && $isOnline && $paymentStatus !== 'PAID') {
        return ['CANCELLED'];
    }

    return match ($status) {
        'PENDING' => ['CONFIRMED','CANCELLED'],
        'CONFIRMED' => ['PREPARING','CANCELLED'],
        'PREPARING' => ['READY','CANCELLED'],
        'READY' => $fulfillment === 'DELIVERY' ? ['OUT_FOR_DELIVERY'] : ['COMPLETED'],
        'OUT_FOR_DELIVERY' => ['COMPLETED'],
        default => [],
    };
}

function ops_notifications_enabled(int $userId): bool
{
    if ($userId <= 0) return false;
    $stmt = db()->prepare('SELECT COALESCE(notifications_enabled,1) FROM customer_profiles WHERE user_id=:user_id LIMIT 1');
    $stmt->execute(['user_id' => $userId]);
    $value = $stmt->fetchColumn();
    return $value === false || (int)$value === 1;
}

function ops_notify_customer(PDO $pdo, int $userId, string $title, string $message, string $targetUrl): void
{
    if ($userId <= 0 || !ops_notifications_enabled($userId)) return;
    $stmt = $pdo->prepare('INSERT INTO notifications (user_id,title,message,target_url) VALUES (:user_id,:title,:message,:target)');
    $stmt->execute(['user_id' => $userId, 'title' => substr($title,0,140), 'message' => substr($message,0,255), 'target' => $targetUrl]);
}

function ops_update_order_status(int $orderId, string $newStatus, int $staffId, string $note = ''): array
{
    $newStatus = strtoupper(trim($newStatus));
    $note = trim($note);
    if ($note !== '' && mb_strlen($note) > 255) return ['ok' => false, 'message' => 'Status note must be 255 characters or fewer.'];

    $pdo = db();
    $pdo->beginTransaction();
    try {
        $stmt = $pdo->prepare("SELECT o.*,p.status AS payment_status,pm.is_online FROM orders o LEFT JOIN payments p ON p.order_id=o.id LEFT JOIN payment_methods pm ON pm.id=p.payment_method_id WHERE o.id=:id FOR UPDATE");
        $stmt->execute(['id' => $orderId]);
        $order = $stmt->fetch();
        if (!$order) throw new RuntimeException('Order not found.');
        $allowed = ops_allowed_transitions($order);
        if (!in_array($newStatus, $allowed, true)) throw new RuntimeException('That status change is not allowed from the current order state.');

        if ($newStatus === 'CONFIRMED' && (int)($order['is_online'] ?? 0) === 1 && ($order['payment_status'] ?? '') !== 'PAID') {
            throw new RuntimeException('Approve the online payment before confirming this order.');
        }
        if (($order['payment_status'] ?? '') === 'FAILED' && $newStatus !== 'CANCELLED') {
            throw new RuntimeException('This payment was rejected. Resolve the payment before continuing the order.');
        }

        $pdo->prepare('UPDATE orders SET status=:status,updated_at=NOW() WHERE id=:id')->execute(['status' => $newStatus, 'id' => $orderId]);
        $pdo->prepare('INSERT INTO order_status_history (order_id,status,changed_by,note) VALUES (:order_id,:status,:changed_by,:note)')->execute([
            'order_id' => $orderId,
            'status' => $newStatus,
            'changed_by' => $staffId,
            'note' => $note !== '' ? $note : 'Order status updated by staff',
        ]);

        if (!empty($order['user_id'])) {
            $label = ops_status_label($newStatus);
            $message = match ($newStatus) {
                'CONFIRMED' => 'Your order ' . $order['order_number'] . ' has been confirmed.',
                'PREPARING' => 'We are now preparing ' . $order['order_number'] . '.',
                'READY' => $order['fulfillment_type'] === 'DELIVERY' ? 'Your order ' . $order['order_number'] . ' is ready for dispatch.' : 'Your order ' . $order['order_number'] . ' is ready for pickup.',
                'OUT_FOR_DELIVERY' => 'Your order ' . $order['order_number'] . ' is out for delivery.',
                'COMPLETED' => 'Your order ' . $order['order_number'] . ' has been completed. Thank you!',
                'CANCELLED' => 'Your order ' . $order['order_number'] . ' has been cancelled.',
                default => 'Your order ' . $order['order_number'] . ' is now ' . strtolower($label) . '.',
            };
            ops_notify_customer($pdo, (int)$order['user_id'], 'Order ' . $label, $message, 'customer/order-details.php?order=' . rawurlencode((string)$order['order_number']));
        }
        $pdo->commit();
        log_activity($staffId, 'UPDATE_ORDER_STATUS', 'orders', $orderId, ['from' => $order['status'], 'to' => $newStatus, 'order_number' => $order['order_number']]);
        return ['ok' => true, 'message' => 'Order moved to ' . ops_status_label($newStatus) . '.'];
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        return ['ok' => false, 'message' => app_is_debug() ? $e->getMessage() : 'The order status could not be updated.'];
    }
}

function ops_review_payment(int $paymentId, string $decision, int $staffId, string $note = ''): array
{
    $decision = strtoupper(trim($decision));
    $note = trim($note);
    if ($note !== '' && mb_strlen($note) > 255) return ['ok' => false, 'message' => 'Payment note must be 255 characters or fewer.'];

    $pdo = db();
    $pdo->beginTransaction();
    try {
        $stmt = $pdo->prepare("SELECT p.*,o.order_number,o.user_id,o.status AS order_status,pm.code AS payment_code,pm.is_online FROM payments p JOIN orders o ON o.id=p.order_id JOIN payment_methods pm ON pm.id=p.payment_method_id WHERE p.id=:id FOR UPDATE");
        $stmt->execute(['id' => $paymentId]);
        $payment = $stmt->fetch();
        if (!$payment) throw new RuntimeException('Payment record not found.');

        $current = strtoupper((string)$payment['status']);
        if ($decision === 'APPROVE') {
            if ($current !== 'PENDING') throw new RuntimeException('Only pending online payments can be approved.');
            $newStatus = 'PAID';
            $action = 'APPROVE_PAYMENT';
            $title = 'Payment approved';
            $message = 'Payment for ' . $payment['order_number'] . ' has been approved.';
        } elseif ($decision === 'REJECT') {
            if ($current !== 'PENDING') throw new RuntimeException('Only pending online payments can be rejected.');
            $newStatus = 'FAILED';
            $action = 'REJECT_PAYMENT';
            $title = 'Payment rejected';
            $message = 'Payment for ' . $payment['order_number'] . ' was rejected. Please contact Franscoffee Shop for assistance.';
        } elseif ($decision === 'MARK_PAID') {
            if ($current !== 'UNPAID' || (int)$payment['is_online'] === 1) throw new RuntimeException('Only unpaid cash/COP/COD payments can be marked paid here.');
            $newStatus = 'PAID';
            $action = 'MARK_PAYMENT_PAID';
            $title = 'Payment received';
            $message = 'Payment for ' . $payment['order_number'] . ' has been received.';
        } else {
            throw new RuntimeException('Choose a valid payment action.');
        }

        $pdo->prepare('UPDATE payments SET status=:status,verified_by=:verified_by,verified_at=NOW(),verification_note=:note,updated_at=NOW() WHERE id=:id')->execute([
            'status' => $newStatus,
            'verified_by' => $staffId,
            'note' => $note !== '' ? $note : null,
            'id' => $paymentId,
        ]);
        if (!empty($payment['user_id'])) {
            ops_notify_customer($pdo, (int)$payment['user_id'], $title, $message, 'customer/order-details.php?order=' . rawurlencode((string)$payment['order_number']));
        }
        $pdo->commit();
        log_activity($staffId, $action, 'payments', $paymentId, ['order_number' => $payment['order_number'], 'status' => $newStatus]);
        return ['ok' => true, 'message' => $title . '.'];
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        return ['ok' => false, 'message' => app_is_debug() ? $e->getMessage() : 'The payment could not be updated.'];
    }
}

function ops_customers(array $filters = []): array
{
    $where = ["u.role = 'CUSTOMER'"];
    $params = [];
    $q = trim((string) ($filters['q'] ?? ''));
    $status = strtoupper(trim((string) ($filters['status'] ?? 'ALL')));

    if ($q !== '') {
        sql_add_search_filter($where, $params, ["CONCAT(u.first_name,' ',u.last_name)", 'u.email', "COALESCE(u.phone,'')"], $q);
    }
    if (in_array($status, ['ACTIVE', 'INACTIVE'], true)) {
        $where[] = 'u.status = :status';
        $params['status'] = $status;
    }

    $sql = "SELECT u.id,u.first_name,u.last_name,u.email,u.phone,u.status,u.created_at,u.last_login_at,
        COUNT(DISTINCT o.id) AS order_count,
        MAX(o.placed_at) AS last_order_at,
        COALESCE(SUM(CASE WHEN p.status='PAID' THEN p.amount ELSE 0 END),0) AS paid_total
        FROM users u
        LEFT JOIN orders o ON o.user_id=u.id
        LEFT JOIN payments p ON p.order_id=o.id
        WHERE " . implode(' AND ', $where) . "
        GROUP BY u.id,u.first_name,u.last_name,u.email,u.phone,u.status,u.created_at,u.last_login_at
        ORDER BY u.created_at DESC,u.id DESC LIMIT 250";
    $stmt = db()->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll();
}

function ops_customer_counts(): array
{
    $row = db()->query("SELECT COUNT(*) total, COALESCE(SUM(status='ACTIVE'),0) active, COALESCE(SUM(status='INACTIVE'),0) inactive FROM users WHERE role='CUSTOMER'")->fetch() ?: [];
    return [
        'total' => (int) ($row['total'] ?? 0),
        'active' => (int) ($row['active'] ?? 0),
        'inactive' => (int) ($row['inactive'] ?? 0),
    ];
}
