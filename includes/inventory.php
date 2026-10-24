<?php

declare(strict_types=1);

function admin_suppliers(array $filters = []): array
{
    $where = ['1=1'];
    $params = [];
    $q = trim((string)($filters['q'] ?? ''));
    if ($q !== '') {
        sql_add_search_filter($where, $params, ['s.name', 's.contact_person', 's.email', 's.phone', 's.products_supplied'], $q);
    }
    $status = strtoupper((string)($filters['status'] ?? 'ALL'));
    if (in_array($status, ['ACTIVE', 'INACTIVE'], true)) {
        $where[] = 's.status = :status';
        $params['status'] = $status;
    }
    $stmt = db()->prepare(
        'SELECT s.*, COUNT(i.id) AS inventory_count '
        . 'FROM suppliers s LEFT JOIN inventory_items i ON i.supplier_id=s.id '
        . 'WHERE ' . implode(' AND ', $where) . ' GROUP BY s.id ORDER BY s.status ASC,s.name ASC'
    );
    $stmt->execute($params);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function admin_supplier_by_id(int $id): ?array
{
    $stmt = db()->prepare('SELECT * FROM suppliers WHERE id=:id LIMIT 1');
    $stmt->execute(['id' => $id]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    return $row ?: null;
}

function admin_supplier_counts(): array
{
    $row = db()->query("SELECT COUNT(*) total,SUM(status='ACTIVE') active_count,SUM(status='INACTIVE') inactive_count FROM suppliers")->fetch();
    return [
        'total' => (int)($row['total'] ?? 0),
        'active' => (int)($row['active_count'] ?? 0),
        'inactive' => (int)($row['inactive_count'] ?? 0),
    ];
}

function admin_save_supplier(array $data, int $id = 0): array
{
    $existing = $id > 0 ? admin_supplier_by_id($id) : null;
    if ($id > 0 && !$existing) return ['ok' => false, 'errors' => ['Supplier not found.']];

    $name = trim((string)($data['name'] ?? ''));
    $contact = trim((string)($data['contact_person'] ?? ''));
    $email = strtolower(trim((string)($data['email'] ?? '')));
    $phone = trim((string)($data['phone'] ?? ''));
    $address = trim((string)($data['address'] ?? ''));
    $products = trim((string)($data['products_supplied'] ?? ''));
    $status = strtoupper(trim((string)($data['status'] ?? 'ACTIVE')));
    $errors = [];

    if ($name === '' || mb_strlen($name) > 150) $errors[] = 'Supplier name is required and must be 150 characters or fewer.';
    if ($contact !== '' && mb_strlen($contact) > 120) $errors[] = 'Contact person must be 120 characters or fewer.';
    if ($email !== '' && (!filter_var($email, FILTER_VALIDATE_EMAIL) || mb_strlen($email) > 190)) $errors[] = 'Enter a valid supplier email address.';
    if ($phone !== '' && mb_strlen($phone) > 40) $errors[] = 'Phone number must be 40 characters or fewer.';
    if (mb_strlen($address) > 3000) $errors[] = 'Address is too long.';
    if (mb_strlen($products) > 2000) $errors[] = 'Products supplied is too long.';
    if (!in_array($status, ['ACTIVE', 'INACTIVE'], true)) $errors[] = 'Choose a valid supplier status.';

    $stmt = db()->prepare('SELECT id FROM suppliers WHERE LOWER(name)=LOWER(:name) AND id<>:id LIMIT 1');
    $stmt->execute(['name' => $name, 'id' => $id]);
    if ($name !== '' && $stmt->fetch()) $errors[] = 'A supplier with that name already exists.';
    if ($errors) return ['ok' => false, 'errors' => $errors];

    if ($id > 0) {
        $stmt = db()->prepare('UPDATE suppliers SET name=:name,contact_person=:contact_person,email=:email,phone=:phone,address=:address,products_supplied=:products,status=:status,updated_at=NOW() WHERE id=:id');
        $stmt->execute([
            'name' => $name, 'contact_person' => $contact !== '' ? $contact : null,
            'email' => $email !== '' ? $email : null, 'phone' => $phone !== '' ? $phone : null,
            'address' => $address !== '' ? $address : null, 'products' => $products !== '' ? $products : null,
            'status' => $status, 'id' => $id,
        ]);
        log_activity((int)current_user()['id'], 'UPDATE_SUPPLIER', 'suppliers', $id, ['status' => $status]);
        return ['ok' => true, 'id' => $id, 'message' => 'Supplier updated.'];
    }

    $stmt = db()->prepare('INSERT INTO suppliers (name,contact_person,email,phone,address,products_supplied,status) VALUES (:name,:contact_person,:email,:phone,:address,:products,:status)');
    $stmt->execute([
        'name' => $name, 'contact_person' => $contact !== '' ? $contact : null,
        'email' => $email !== '' ? $email : null, 'phone' => $phone !== '' ? $phone : null,
        'address' => $address !== '' ? $address : null, 'products' => $products !== '' ? $products : null,
        'status' => $status,
    ]);
    $newId = (int)db()->lastInsertId();
    log_activity((int)current_user()['id'], 'CREATE_SUPPLIER', 'suppliers', $newId);
    return ['ok' => true, 'id' => $newId, 'message' => 'Supplier created.'];
}

function admin_toggle_supplier(int $id): array
{
    $supplier = admin_supplier_by_id($id);
    if (!$supplier) return ['ok' => false, 'message' => 'Supplier not found.'];
    $next = $supplier['status'] === 'ACTIVE' ? 'INACTIVE' : 'ACTIVE';
    db()->prepare('UPDATE suppliers SET status=:status,updated_at=NOW() WHERE id=:id')->execute(['status' => $next, 'id' => $id]);
    log_activity((int)current_user()['id'], $next === 'ACTIVE' ? 'ENABLE_SUPPLIER' : 'DISABLE_SUPPLIER', 'suppliers', $id);
    return ['ok' => true, 'message' => $next === 'ACTIVE' ? 'Supplier activated.' : 'Supplier deactivated. Existing inventory links are preserved.'];
}

function inventory_categories(): array
{
    return db()->query("SELECT DISTINCT category FROM inventory_items WHERE category IS NOT NULL AND category<>'' ORDER BY category")->fetchAll(PDO::FETCH_COLUMN);
}

function inventory_generate_item_code(): string
{
    $next = (int)db()->query('SELECT COALESCE(MAX(id),0)+1 FROM inventory_items')->fetchColumn();
    do {
        $code = 'FCS-INV-' . str_pad((string)$next++, 4, '0', STR_PAD_LEFT);
        $stmt = db()->prepare('SELECT id FROM inventory_items WHERE item_code=:code LIMIT 1');
        $stmt->execute(['code' => $code]);
    } while ($stmt->fetch());
    return $code;
}

function admin_inventory_items(array $filters = []): array
{
    $where = ['1=1'];
    $params = [];
    $q = trim((string)($filters['q'] ?? ''));
    if ($q !== '') {
        sql_add_search_filter($where, $params, ['i.item_code', 'i.item_name', 'i.category'], $q);
    }
    $category = trim((string)($filters['category'] ?? ''));
    if ($category !== '') { $where[] = 'i.category=:category'; $params['category'] = $category; }
    $supplierId = (int)($filters['supplier_id'] ?? 0);
    if ($supplierId > 0) { $where[] = 'i.supplier_id=:supplier_id'; $params['supplier_id'] = $supplierId; }
    $status = strtoupper((string)($filters['status'] ?? 'ALL'));
    if (in_array($status, ['ACTIVE', 'INACTIVE'], true)) { $where[] = 'i.status=:status'; $params['status'] = $status; }
    $stock = strtoupper((string)($filters['stock'] ?? 'ALL'));
    if ($stock === 'LOW') $where[] = 'i.status=\'ACTIVE\' AND i.quantity<=i.reorder_level';
    if ($stock === 'OK') $where[] = 'i.status=\'ACTIVE\' AND i.quantity>i.reorder_level';

    $stmt = db()->prepare(
        'SELECT '
        . 'i.id,i.supplier_id,i.item_code,i.item_name,i.category,i.unit,i.quantity,i.reorder_level,i.unit_cost,i.status,i.created_at,i.updated_at, '
        . 's.name AS supplier_name, '
        . 'CASE WHEN i.status=\'ACTIVE\' AND i.quantity<=i.reorder_level THEN 1 ELSE 0 END AS is_low_stock '
        . 'FROM inventory_items i LEFT JOIN suppliers s ON s.id=i.supplier_id '
        . 'WHERE ' . implode(' AND ', $where) . ' ORDER BY is_low_stock DESC,i.item_name ASC'
    );
    $stmt->execute($params);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function admin_inventory_item_by_id(int $id): ?array
{
    $stmt = db()->prepare('SELECT i.*,s.name AS supplier_name FROM inventory_items i LEFT JOIN suppliers s ON s.id=i.supplier_id WHERE i.id=:id LIMIT 1');
    $stmt->execute(['id' => $id]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    return $row ?: null;
}

function admin_inventory_metrics(): array
{
    $row = db()->query(
        "SELECT COUNT(*) total,SUM(status='ACTIVE') active_count,SUM(status='INACTIVE') inactive_count,"
        . "SUM(status='ACTIVE' AND quantity<=reorder_level) low_count,"
        . "COALESCE(SUM(CASE WHEN status='ACTIVE' THEN quantity*unit_cost ELSE 0 END),0) stock_value FROM inventory_items"
    )->fetch();
    return [
        'total' => (int)($row['total'] ?? 0),
        'active' => (int)($row['active_count'] ?? 0),
        'inactive' => (int)($row['inactive_count'] ?? 0),
        'low' => (int)($row['low_count'] ?? 0),
        'value' => (float)($row['stock_value'] ?? 0),
    ];
}

function inventory_notify_low_stock(array $item, float $oldQty, float $newQty): void
{
    $threshold = (float)$item['reorder_level'];
    if ($newQty > $threshold || $oldQty <= $threshold) return;
    $admins = db()->query("SELECT id FROM users WHERE role='ADMIN' AND status='ACTIVE'")->fetchAll(PDO::FETCH_COLUMN);
    if (!$admins) return;
    $stmt = db()->prepare('INSERT INTO notifications (user_id,title,message,target_url) VALUES (:user_id,:title,:message,:target_url)');
    foreach ($admins as $adminId) {
        $stmt->execute([
            'user_id' => (int)$adminId,
            'title' => 'Low stock: ' . substr((string)$item['item_name'], 0, 110),
            'message' => sprintf('%s is at %s %s (reorder level: %s).', $item['item_name'], inventory_decimal($newQty), $item['unit'], inventory_decimal($threshold)),
            'target_url' => 'admin/inventory.php?stock=LOW',
        ]);
    }
}

function inventory_decimal(float $value): string
{
    $formatted = number_format($value, 3, '.', '');
    return rtrim(rtrim($formatted, '0'), '.');
}

function admin_save_inventory_item(array $data, int $id = 0): array
{
    $existing = $id > 0 ? admin_inventory_item_by_id($id) : null;
    if ($id > 0 && !$existing) return ['ok' => false, 'errors' => ['Inventory item not found.']];

    $code = strtoupper(trim((string)($data['item_code'] ?? '')));
    if ($code === '' && $id === 0) $code = inventory_generate_item_code();
    $name = trim((string)($data['item_name'] ?? ''));
    $category = trim((string)($data['category'] ?? ''));
    $unit = trim((string)($data['unit'] ?? ''));
    $reorder = filter_var($data['reorder_level'] ?? '', FILTER_VALIDATE_FLOAT);
    $cost = filter_var($data['unit_cost'] ?? '', FILTER_VALIDATE_FLOAT);
    $supplierId = (int)($data['supplier_id'] ?? 0);
    $status = strtoupper(trim((string)($data['status'] ?? 'ACTIVE')));
    $opening = $id === 0 ? filter_var($data['opening_quantity'] ?? 0, FILTER_VALIDATE_FLOAT) : 0.0;
    $errors = [];

    if ($code === '' || mb_strlen($code) > 40 || !preg_match('/^[A-Z0-9._-]+$/', $code)) $errors[] = 'Item code may use letters, numbers, dots, underscores, and hyphens only.';
    if ($name === '' || mb_strlen($name) > 140) $errors[] = 'Item name is required and must be 140 characters or fewer.';
    if ($category !== '' && mb_strlen($category) > 100) $errors[] = 'Category must be 100 characters or fewer.';
    if ($unit === '' || mb_strlen($unit) > 40) $errors[] = 'Unit is required and must be 40 characters or fewer.';
    if ($reorder === false || $reorder < 0 || $reorder > 999999999) $errors[] = 'Enter a valid reorder level.';
    if ($cost === false || $cost < 0 || $cost > 99999999) $errors[] = 'Enter a valid unit cost.';
    if ($id === 0 && ($opening === false || $opening < 0 || $opening > 999999999)) $errors[] = 'Enter a valid opening quantity.';
    if (!in_array($status, ['ACTIVE', 'INACTIVE'], true)) $errors[] = 'Choose a valid inventory status.';
    if ($supplierId > 0 && !admin_supplier_by_id($supplierId)) $errors[] = 'Choose a valid supplier.';

    $stmt = db()->prepare('SELECT id FROM inventory_items WHERE item_code=:code AND id<>:id LIMIT 1');
    $stmt->execute(['code' => $code, 'id' => $id]);
    if ($code !== '' && $stmt->fetch()) $errors[] = 'That item code is already in use.';
    if ($errors) return ['ok' => false, 'errors' => $errors];

    $pdo = db();
    $pdo->beginTransaction();
    try {
        if ($id > 0) {
            $stmt = $pdo->prepare('UPDATE inventory_items SET supplier_id=:supplier_id,item_code=:item_code,item_name=:item_name,category=:category,unit=:unit,reorder_level=:reorder_level,unit_cost=:unit_cost,status=:status,updated_at=NOW() WHERE id=:id');
            $stmt->execute([
                'supplier_id' => $supplierId ?: null, 'item_code' => $code, 'item_name' => $name,
                'category' => $category !== '' ? $category : null, 'unit' => $unit, 'reorder_level' => $reorder,
                'unit_cost' => $cost, 'status' => $status, 'id' => $id,
            ]);
            $itemId = $id;
        } else {
            $stmt = $pdo->prepare('INSERT INTO inventory_items (supplier_id,item_code,item_name,category,unit,quantity,reorder_level,unit_cost,status) VALUES (:supplier_id,:item_code,:item_name,:category,:unit,:quantity,:reorder_level,:unit_cost,:status)');
            $stmt->execute([
                'supplier_id' => $supplierId ?: null, 'item_code' => $code, 'item_name' => $name,
                'category' => $category !== '' ? $category : null, 'unit' => $unit, 'quantity' => $opening,
                'reorder_level' => $reorder, 'unit_cost' => $cost, 'status' => $status,
            ]);
            $itemId = (int)$pdo->lastInsertId();
            if ((float)$opening > 0) {
                $tx = $pdo->prepare("INSERT INTO inventory_transactions (inventory_item_id,type,quantity,unit_cost,balance_after,reference_type,notes,created_by) VALUES (:item_id,'STOCK_IN',:quantity,:unit_cost,:balance,'OPENING','Opening balance',:created_by)");
                $tx->execute(['item_id' => $itemId, 'quantity' => $opening, 'unit_cost' => $cost, 'balance' => $opening, 'created_by' => (int)current_user()['id']]);
            }
        }
        $pdo->commit();
        log_activity((int)current_user()['id'], $id > 0 ? 'UPDATE_INVENTORY_ITEM' : 'CREATE_INVENTORY_ITEM', 'inventory_items', $itemId, ['status' => $status]);
        return ['ok' => true, 'id' => $itemId, 'message' => $id > 0 ? 'Inventory item updated.' : 'Inventory item created. Opening stock was recorded in the transaction history.'];
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        throw $e;
    }
}

function admin_toggle_inventory_item(int $id): array
{
    $item = admin_inventory_item_by_id($id);
    if (!$item) return ['ok' => false, 'message' => 'Inventory item not found.'];
    $next = $item['status'] === 'ACTIVE' ? 'INACTIVE' : 'ACTIVE';
    db()->prepare('UPDATE inventory_items SET status=:status,updated_at=NOW() WHERE id=:id')->execute(['status' => $next, 'id' => $id]);
    log_activity((int)current_user()['id'], $next === 'ACTIVE' ? 'ENABLE_INVENTORY_ITEM' : 'DISABLE_INVENTORY_ITEM', 'inventory_items', $id);
    return ['ok' => true, 'message' => $next === 'ACTIVE' ? 'Inventory item activated.' : 'Inventory item archived. Its stock history is preserved.'];
}

function admin_record_inventory_transaction(int $itemId, array $data): array
{
    $type = strtoupper(trim((string)($data['type'] ?? '')));
    if (!in_array($type, ['STOCK_IN', 'STOCK_OUT', 'ADJUSTMENT', 'WASTE'], true)) return ['ok' => false, 'errors' => ['Choose a valid stock transaction type.']];
    $quantity = filter_var($data['quantity'] ?? '', FILTER_VALIDATE_FLOAT);
    $physicalCount = filter_var($data['physical_count'] ?? '', FILTER_VALIDATE_FLOAT);
    $unitCostRaw = trim((string)($data['unit_cost'] ?? ''));
    $unitCost = $unitCostRaw === '' ? null : filter_var($unitCostRaw, FILTER_VALIDATE_FLOAT);
    $notes = trim((string)($data['notes'] ?? ''));
    $errors = [];
    if ($type === 'ADJUSTMENT') {
        if ($physicalCount === false || $physicalCount < 0 || $physicalCount > 999999999) $errors[] = 'Enter a valid physical stock count.';
    } elseif ($quantity === false || $quantity <= 0 || $quantity > 999999999) {
        $errors[] = 'Transaction quantity must be greater than zero.';
    }
    if ($unitCost !== null && ($unitCost === false || $unitCost < 0 || $unitCost > 99999999)) $errors[] = 'Enter a valid unit cost.';
    if (mb_strlen($notes) > 255) $errors[] = 'Notes must be 255 characters or fewer.';
    if ($errors) return ['ok' => false, 'errors' => $errors];

    $pdo = db();
    $pdo->beginTransaction();
    try {
        $stmt = $pdo->prepare('SELECT * FROM inventory_items WHERE id=:id LIMIT 1 FOR UPDATE');
        $stmt->execute(['id' => $itemId]);
        $item = $stmt->fetch();
        if (!$item) throw new RuntimeException('Inventory item not found.');
        if ($item['status'] !== 'ACTIVE') throw new RuntimeException('Activate this inventory item before recording stock movement.');

        $oldQty = (float)$item['quantity'];
        $movementQty = (float)($quantity === false ? 0 : $quantity);
        $newQty = $oldQty;
        if ($type === 'STOCK_IN') $newQty = $oldQty + $movementQty;
        elseif (in_array($type, ['STOCK_OUT', 'WASTE'], true)) {
            if ($movementQty > $oldQty) throw new RuntimeException('This transaction would make stock negative. Available: ' . inventory_decimal($oldQty) . ' ' . $item['unit'] . '.');
            $newQty = $oldQty - $movementQty;
        } else {
            $newQty = (float)$physicalCount;
            $movementQty = abs($newQty - $oldQty);
        }

        $effectiveCost = $unitCost !== null && $unitCost !== false ? (float)$unitCost : (float)$item['unit_cost'];
        $stmt = $pdo->prepare('UPDATE inventory_items SET quantity=:quantity,unit_cost=:unit_cost,updated_at=NOW() WHERE id=:id');
        $stmt->execute(['quantity' => $newQty, 'unit_cost' => $type === 'STOCK_IN' && $unitCost !== null ? $effectiveCost : $item['unit_cost'], 'id' => $itemId]);

        $tx = $pdo->prepare('INSERT INTO inventory_transactions (inventory_item_id,type,quantity,unit_cost,balance_after,notes,created_by) VALUES (:item_id,:type,:quantity,:unit_cost,:balance_after,:notes,:created_by)');
        $tx->execute([
            'item_id' => $itemId, 'type' => $type, 'quantity' => $movementQty,
            'unit_cost' => $effectiveCost, 'balance_after' => $newQty,
            'notes' => $notes !== '' ? $notes : null, 'created_by' => (int)current_user()['id'],
        ]);
        $txId = (int)$pdo->lastInsertId();
        $pdo->commit();

        inventory_notify_low_stock($item, $oldQty, $newQty);
        log_activity((int)current_user()['id'], 'INVENTORY_' . $type, 'inventory_transactions', $txId, ['item_id' => $itemId, 'before' => $oldQty, 'after' => $newQty]);
        return ['ok' => true, 'message' => 'Stock transaction recorded. New balance: ' . inventory_decimal($newQty) . ' ' . $item['unit'] . '.'];
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        return ['ok' => false, 'errors' => [$e->getMessage()]];
    }
}

function admin_inventory_transactions(array $filters = [], int $limit = 250): array
{
    $where = ['1=1'];
    $params = [];
    $itemId = (int)($filters['item_id'] ?? 0);
    if ($itemId > 0) { $where[] = 't.inventory_item_id=:item_id'; $params['item_id'] = $itemId; }
    $type = strtoupper((string)($filters['type'] ?? 'ALL'));
    if (in_array($type, ['STOCK_IN', 'STOCK_OUT', 'ADJUSTMENT', 'WASTE'], true)) { $where[] = 't.type=:type'; $params['type'] = $type; }
    $q = trim((string)($filters['q'] ?? ''));
    if ($q !== '') sql_add_search_filter($where, $params, ['i.item_name', 'i.item_code', 't.notes'], $q);
    $limit = max(1, min(500, $limit));
    $stmt = db()->prepare(
        'SELECT t.*,i.item_code,i.item_name,i.unit,u.first_name,u.last_name '
        . 'FROM inventory_transactions t JOIN inventory_items i ON i.id=t.inventory_item_id '
        . 'LEFT JOIN users u ON u.id=t.created_by WHERE ' . implode(' AND ', $where)
        . ' ORDER BY t.created_at DESC,t.id DESC LIMIT ' . $limit
    );
    $stmt->execute($params);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}
