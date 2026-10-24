<?php

declare(strict_types=1);

function customer_profile(int $userId): ?array
{
    $stmt = db()->prepare(
        'SELECT u.id,u.first_name,u.last_name,u.email,u.phone,u.created_at,u.last_login_at, '
        . 'cp.avatar_path,cp.preferred_category,cp.notifications_enabled '
        . 'FROM users u LEFT JOIN customer_profiles cp ON cp.user_id=u.id '
        . 'WHERE u.id=:user_id AND u.role=\'CUSTOMER\' LIMIT 1'
    );
    $stmt->execute(['user_id' => $userId]);
    $row = $stmt->fetch();
    return $row ?: null;
}

function customer_unread_notification_count(?int $userId = null): int
{
    $userId ??= (int) (current_user()['id'] ?? 0);
    if ($userId <= 0 || user_role() !== 'CUSTOMER') return 0;
    $stmt = db()->prepare('SELECT COUNT(*) FROM notifications WHERE user_id=:user_id AND read_at IS NULL');
    $stmt->execute(['user_id' => $userId]);
    return (int) $stmt->fetchColumn();
}

function customer_notifications(int $userId, int $limit = 100): array
{
    $limit = max(1, min(100, $limit));
    $stmt = db()->prepare('SELECT * FROM notifications WHERE user_id=:user_id ORDER BY created_at DESC,id DESC LIMIT ' . $limit);
    $stmt->execute(['user_id' => $userId]);
    return $stmt->fetchAll();
}


function customer_notification(int $userId, int $notificationId): ?array
{
    $stmt = db()->prepare('SELECT * FROM notifications WHERE id=:id AND user_id=:user_id LIMIT 1');
    $stmt->execute(['id' => $notificationId, 'user_id' => $userId]);
    $row = $stmt->fetch();
    return $row ?: null;
}

function customer_mark_notification_read(int $userId, int $notificationId): bool
{
    $stmt = db()->prepare('UPDATE notifications SET read_at=COALESCE(read_at,NOW()) WHERE id=:id AND user_id=:user_id');
    $stmt->execute(['id' => $notificationId, 'user_id' => $userId]);
    return $stmt->rowCount() > 0;
}

function customer_mark_all_notifications_read(int $userId): int
{
    $stmt = db()->prepare('UPDATE notifications SET read_at=NOW() WHERE user_id=:user_id AND read_at IS NULL');
    $stmt->execute(['user_id' => $userId]);
    return $stmt->rowCount();
}

function customer_order_counts(int $userId): array
{
    $counts = [
        'ALL' => 0, 'PENDING' => 0, 'CONFIRMED' => 0, 'PREPARING' => 0,
        'READY' => 0, 'OUT_FOR_DELIVERY' => 0, 'COMPLETED' => 0, 'CANCELLED' => 0,
    ];
    $stmt = db()->prepare('SELECT status,COUNT(*) AS total FROM orders WHERE user_id=:user_id GROUP BY status');
    $stmt->execute(['user_id' => $userId]);
    foreach ($stmt->fetchAll() as $row) {
        $status = (string) $row['status'];
        $total = (int) $row['total'];
        if (isset($counts[$status])) $counts[$status] = $total;
        $counts['ALL'] += $total;
    }
    return $counts;
}

function customer_orders(int $userId, string $status = 'ALL', int $limit = 100): array
{
    $allowed = ['ALL','PENDING','CONFIRMED','PREPARING','READY','OUT_FOR_DELIVERY','COMPLETED','CANCELLED'];
    $status = strtoupper(trim($status));
    if (!in_array($status, $allowed, true)) $status = 'ALL';
    $limit = max(1, min(100, $limit));

    $sql = 'SELECT o.*,p.status AS payment_status,p.payment_number,pm.name AS payment_method, '
        . '(SELECT COUNT(*) FROM order_items oi WHERE oi.order_id=o.id) AS item_lines, '
        . '(SELECT COALESCE(SUM(oi.quantity),0) FROM order_items oi WHERE oi.order_id=o.id) AS item_quantity '
        . 'FROM orders o LEFT JOIN payments p ON p.order_id=o.id LEFT JOIN payment_methods pm ON pm.id=p.payment_method_id '
        . 'WHERE o.user_id=:user_id';
    $params = ['user_id' => $userId];
    if ($status !== 'ALL') {
        $sql .= ' AND o.status=:status';
        $params['status'] = $status;
    }
    $sql .= ' ORDER BY o.placed_at DESC,o.id DESC LIMIT ' . $limit;
    $stmt = db()->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll();
}

function customer_order_detail(string $orderNumber, int $userId): ?array
{
    $order = checkout_order_for_customer($orderNumber, $userId);
    if (!$order) return null;

    $stmt = db()->prepare(
        'SELECT osh.*,u.first_name,u.last_name FROM order_status_history osh '
        . 'LEFT JOIN users u ON u.id=osh.changed_by WHERE osh.order_id=:order_id ORDER BY osh.created_at ASC,osh.id ASC'
    );
    $stmt->execute(['order_id' => $order['id']]);
    $order['history'] = $stmt->fetchAll();

    $stmt = db()->prepare('SELECT pp.file_path,pp.uploaded_at FROM payment_proofs pp JOIN payments p ON p.id=pp.payment_id WHERE p.order_id=:order_id ORDER BY pp.id DESC LIMIT 1');
    $stmt->execute(['order_id' => $order['id']]);
    $proof = $stmt->fetch();
    $order['payment_proof'] = $proof ?: null;
    return $order;
}

function customer_active_order(int $userId): ?array
{
    $stmt = db()->prepare(
        "SELECT o.*,p.status AS payment_status,pm.name AS payment_method FROM orders o "
        . "LEFT JOIN payments p ON p.order_id=o.id LEFT JOIN payment_methods pm ON pm.id=p.payment_method_id "
        . "WHERE o.user_id=:user_id AND o.status NOT IN ('COMPLETED','CANCELLED') ORDER BY o.placed_at DESC,o.id DESC LIMIT 1"
    );
    $stmt->execute(['user_id' => $userId]);
    $row = $stmt->fetch();
    return $row ?: null;
}

function customer_recent_orders(int $userId, int $limit = 4): array
{
    $limit = max(1, min(10, $limit));
    $stmt = db()->prepare(
        'SELECT o.*,p.status AS payment_status,pm.name AS payment_method FROM orders o '
        . 'LEFT JOIN payments p ON p.order_id=o.id LEFT JOIN payment_methods pm ON pm.id=p.payment_method_id '
        . 'WHERE o.user_id=:user_id ORDER BY o.placed_at DESC,o.id DESC LIMIT ' . $limit
    );
    $stmt->execute(['user_id' => $userId]);
    return $stmt->fetchAll();
}

function customer_recommended_products(int $userId, int $limit = 4): array
{
    $limit = max(1, min(8, $limit));
    $profile = customer_profile($userId);
    $preferred = trim((string) ($profile['preferred_category'] ?? ''));
    $params = [];
    $sql = 'SELECT p.*,c.name AS category_name,c.slug AS category_slug FROM products p JOIN categories c ON c.id=p.category_id WHERE p.is_active=1 AND c.is_active=1';
    if ($preferred !== '') {
        $sql .= ' AND c.slug=:category';
        $params['category'] = $preferred;
    }
    $sql .= ' ORDER BY p.is_featured DESC,p.id ASC LIMIT ' . $limit;
    $stmt = db()->prepare($sql);
    $stmt->execute($params);
    $rows = $stmt->fetchAll();
    if (!$rows && $preferred !== '') {
        $stmt = db()->query('SELECT p.*,c.name AS category_name,c.slug AS category_slug FROM products p JOIN categories c ON c.id=p.category_id WHERE p.is_active=1 AND c.is_active=1 ORDER BY p.is_featured DESC,p.id ASC LIMIT ' . $limit);
        $rows = $stmt->fetchAll();
    }
    return $rows;
}

function customer_update_profile(int $userId, array $input, ?array $avatarFile = null): array
{
    $firstName = trim((string) ($input['first_name'] ?? ''));
    $lastName = trim((string) ($input['last_name'] ?? ''));
    $phone = trim((string) ($input['phone'] ?? ''));
    $preferred = trim((string) ($input['preferred_category'] ?? ''));
    $notifications = isset($input['notifications_enabled']) ? 1 : 0;
    $errors = [];

    if ($firstName === '' || mb_strlen($firstName) > 80) $errors[] = 'Enter a valid first name.';
    if ($lastName === '' || mb_strlen($lastName) > 80) $errors[] = 'Enter a valid last name.';
    if ($phone !== '' && mb_strlen($phone) > 30) $errors[] = 'Enter a valid phone number.';

    if ($preferred !== '') {
        $stmt = db()->prepare('SELECT COUNT(*) FROM categories WHERE slug=:slug AND is_active=1');
        $stmt->execute(['slug' => $preferred]);
        if ((int) $stmt->fetchColumn() === 0) $errors[] = 'Choose a valid preferred category.';
    }

    $newAvatarPath = null;
    $oldAvatarPath = null;
    if ($avatarFile && ($avatarFile['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
        if (($avatarFile['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            $errors[] = 'Profile photo upload failed.';
        } elseif ((int) ($avatarFile['size'] ?? 0) <= 0 || (int) $avatarFile['size'] > 3 * 1024 * 1024) {
            $errors[] = 'Profile photo must be 3 MB or smaller.';
        } else {
            $tmp = (string) ($avatarFile['tmp_name'] ?? '');
            if ($tmp === '' || !is_uploaded_file($tmp)) {
                $errors[] = 'Invalid profile photo upload.';
            } else {
                $mime = (new finfo(FILEINFO_MIME_TYPE))->file($tmp) ?: '';
                $allowed = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];
                if (!isset($allowed[$mime])) {
                    $errors[] = 'Profile photo must be JPG, PNG, or WEBP.';
                } else {
                    $dir = dirname(__DIR__) . '/uploads/profiles';
                    if (!is_dir($dir) && !mkdir($dir, 0775, true) && !is_dir($dir)) {
                        $errors[] = 'Profile upload folder could not be created.';
                    } else {
                        $filename = 'customer-' . $userId . '-' . bin2hex(random_bytes(8)) . '.' . $allowed[$mime];
                        $absolute = $dir . '/' . $filename;
                        if (!move_uploaded_file($tmp, $absolute)) {
                            $errors[] = 'Profile photo could not be saved.';
                        } else {
                            $newAvatarPath = 'uploads/profiles/' . $filename;
                        }
                    }
                }
            }
        }
    }

    if ($errors) {
        if ($newAvatarPath) @unlink(dirname(__DIR__) . '/' . $newAvatarPath);
        return ['ok' => false, 'errors' => $errors];
    }

    $pdo = db();
    $pdo->beginTransaction();
    try {
        $stmt = $pdo->prepare('SELECT avatar_path FROM customer_profiles WHERE user_id=:user_id LIMIT 1');
        $stmt->execute(['user_id' => $userId]);
        $oldAvatarPath = (string) ($stmt->fetchColumn() ?: '');

        $pdo->prepare('UPDATE users SET first_name=:first_name,last_name=:last_name,phone=:phone,updated_at=NOW() WHERE id=:user_id AND role=\'CUSTOMER\'')
            ->execute(['first_name' => $firstName, 'last_name' => $lastName, 'phone' => $phone !== '' ? $phone : null, 'user_id' => $userId]);

        $pdo->prepare('INSERT INTO customer_profiles (user_id,avatar_path,preferred_category,notifications_enabled) VALUES (:user_id,:avatar_path,:preferred_category,:notifications_enabled) '
            . 'ON DUPLICATE KEY UPDATE avatar_path=COALESCE(VALUES(avatar_path),avatar_path),preferred_category=VALUES(preferred_category),notifications_enabled=VALUES(notifications_enabled),updated_at=NOW()')
            ->execute([
                'user_id' => $userId,
                'avatar_path' => $newAvatarPath,
                'preferred_category' => $preferred !== '' ? $preferred : null,
                'notifications_enabled' => $notifications,
            ]);
        $pdo->commit();

        if ($newAvatarPath && $oldAvatarPath && str_starts_with($oldAvatarPath, 'uploads/profiles/')) {
            $oldAbsolute = dirname(__DIR__) . '/' . $oldAvatarPath;
            if (is_file($oldAbsolute)) @unlink($oldAbsolute);
        }

        $_SESSION['user']['first_name'] = $firstName;
        $_SESSION['user']['last_name'] = $lastName;
        log_activity($userId, 'UPDATE_PROFILE', 'users', $userId);
        return ['ok' => true];
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        if ($newAvatarPath) @unlink(dirname(__DIR__) . '/' . $newAvatarPath);
        if (app_is_debug()) return ['ok' => false, 'errors' => ['Profile update failed: ' . $e->getMessage()]];
        return ['ok' => false, 'errors' => ['Profile update failed. Please try again.']];
    }
}

function customer_change_password(int $userId, string $currentPassword, string $newPassword, string $confirmPassword): array
{
    $errors = [];
    $stmt = db()->prepare('SELECT password_hash FROM users WHERE id=:id AND role=\'CUSTOMER\' LIMIT 1');
    $stmt->execute(['id' => $userId]);
    $hash = $stmt->fetchColumn();
    if (!$hash || !password_verify($currentPassword, (string) $hash)) $errors[] = 'Current password is incorrect.';
    if (!password_is_strong($newPassword)) $errors[] = 'New password must be at least 8 characters and include uppercase, lowercase, and a number.';
    if ($newPassword !== $confirmPassword) $errors[] = 'New password confirmation does not match.';
    if ($errors) return ['ok' => false, 'errors' => $errors];

    db()->prepare('UPDATE users SET password_hash=:hash,updated_at=NOW() WHERE id=:id')->execute([
        'hash' => password_hash($newPassword, PASSWORD_DEFAULT),
        'id' => $userId,
    ]);
    log_activity($userId, 'CHANGE_PASSWORD', 'users', $userId);
    return ['ok' => true];
}

function customer_address_validate(array $input): array
{
    $address = checkout_address_from_input($input);
    return ['address' => $address, 'errors' => checkout_validate_address($address)];
}

function customer_address_save(int $userId, array $input, ?int $addressId = null): array
{
    $validated = customer_address_validate($input);
    if ($validated['errors']) return ['ok' => false, 'errors' => $validated['errors']];
    $a = $validated['address'];
    $isDefault = isset($input['is_default']) ? 1 : 0;
    $pdo = db();
    $pdo->beginTransaction();
    try {
        if ($isDefault) $pdo->prepare('UPDATE addresses SET is_default=0 WHERE user_id=:user_id')->execute(['user_id' => $userId]);
        if ($addressId) {
            $stmt = $pdo->prepare('UPDATE addresses SET label=:label,street=:street,barangay=:barangay,city=:city,province=:province,postal_code=:postal_code,landmark=:landmark,notes=:notes,is_default=:is_default WHERE id=:id AND user_id=:user_id');
            $stmt->execute([
                'label' => $a['label'], 'street' => $a['street'], 'barangay' => $a['barangay'], 'city' => $a['city'], 'province' => $a['province'],
                'postal_code' => $a['postal_code'] !== '' ? $a['postal_code'] : null, 'landmark' => $a['landmark'] !== '' ? $a['landmark'] : null,
                'notes' => $a['notes'] !== '' ? $a['notes'] : null, 'is_default' => $isDefault, 'id' => $addressId, 'user_id' => $userId,
            ]);
            if ($stmt->rowCount() === 0) {
                $check = $pdo->prepare('SELECT COUNT(*) FROM addresses WHERE id=:id AND user_id=:user_id');
                $check->execute(['id' => $addressId, 'user_id' => $userId]);
                if ((int) $check->fetchColumn() === 0) throw new RuntimeException('Address not found.');
            }
            $savedId = $addressId;
        } else {
            $stmt = $pdo->prepare('INSERT INTO addresses (user_id,label,street,barangay,city,province,postal_code,landmark,notes,is_default) VALUES (:user_id,:label,:street,:barangay,:city,:province,:postal_code,:landmark,:notes,:is_default)');
            $stmt->execute([
                'user_id' => $userId, 'label' => $a['label'], 'street' => $a['street'], 'barangay' => $a['barangay'], 'city' => $a['city'], 'province' => $a['province'],
                'postal_code' => $a['postal_code'] !== '' ? $a['postal_code'] : null, 'landmark' => $a['landmark'] !== '' ? $a['landmark'] : null,
                'notes' => $a['notes'] !== '' ? $a['notes'] : null, 'is_default' => $isDefault,
            ]);
            $savedId = (int) $pdo->lastInsertId();
        }
        $countStmt = $pdo->prepare('SELECT COUNT(*) FROM addresses WHERE user_id=:user_id AND is_default=1');
        $countStmt->execute(['user_id' => $userId]);
        if ((int) $countStmt->fetchColumn() === 0) {
            $pdo->prepare('UPDATE addresses SET is_default=1 WHERE id=:id AND user_id=:user_id')->execute(['id' => $savedId, 'user_id' => $userId]);
        }
        $pdo->commit();
        log_activity($userId, $addressId ? 'UPDATE_ADDRESS' : 'CREATE_ADDRESS', 'addresses', $savedId);
        return ['ok' => true, 'id' => $savedId];
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        return ['ok' => false, 'errors' => [app_is_debug() ? $e->getMessage() : 'Address could not be saved.']];
    }
}

function customer_address_delete(int $userId, int $addressId): bool
{
    $pdo = db();
    $pdo->beginTransaction();
    try {
        $stmt = $pdo->prepare('SELECT is_default FROM addresses WHERE id=:id AND user_id=:user_id FOR UPDATE');
        $stmt->execute(['id' => $addressId, 'user_id' => $userId]);
        $row = $stmt->fetch();
        if (!$row) {
            $pdo->rollBack();
            return false;
        }
        $wasDefault = (int) $row['is_default'] === 1;
        $pdo->prepare('DELETE FROM addresses WHERE id=:id AND user_id=:user_id')->execute(['id' => $addressId, 'user_id' => $userId]);
        if ($wasDefault) {
            $stmt = $pdo->prepare('SELECT id FROM addresses WHERE user_id=:user_id ORDER BY updated_at DESC,id DESC LIMIT 1');
            $stmt->execute(['user_id' => $userId]);
            $next = (int) ($stmt->fetchColumn() ?: 0);
            if ($next > 0) $pdo->prepare('UPDATE addresses SET is_default=1 WHERE id=:id')->execute(['id' => $next]);
        }
        $pdo->commit();
        log_activity($userId, 'DELETE_ADDRESS', 'addresses', $addressId);
        return true;
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        throw $e;
    }
}

function customer_address_set_default(int $userId, int $addressId): bool
{
    $pdo = db();
    $pdo->beginTransaction();
    try {
        $stmt = $pdo->prepare('SELECT COUNT(*) FROM addresses WHERE id=:id AND user_id=:user_id');
        $stmt->execute(['id' => $addressId, 'user_id' => $userId]);
        if ((int) $stmt->fetchColumn() === 0) {
            $pdo->rollBack();
            return false;
        }
        $pdo->prepare('UPDATE addresses SET is_default=0 WHERE user_id=:user_id')->execute(['user_id' => $userId]);
        $pdo->prepare('UPDATE addresses SET is_default=1 WHERE id=:id AND user_id=:user_id')->execute(['id' => $addressId, 'user_id' => $userId]);
        $pdo->commit();
        return true;
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        throw $e;
    }
}

function customer_status_label(string $status): string
{
    return match (strtoupper($status)) {
        'OUT_FOR_DELIVERY' => 'Out for delivery',
        default => ucfirst(strtolower(str_replace('_', ' ', $status))),
    };
}

function customer_datetime(?string $value, string $format = 'M j, Y · g:i A'): string
{
    if (!$value) return '—';
    $time = strtotime($value);
    return $time ? date($format, $time) : '—';
}
