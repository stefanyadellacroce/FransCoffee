<?php

declare(strict_types=1);

function login_user(array $user): void
{
    session_regenerate_id(true);
    $_SESSION['_csrf'] = bin2hex(random_bytes(32));
    $_SESSION['user'] = [
        'id' => (int) $user['id'],
        'first_name' => (string) $user['first_name'],
        'last_name' => (string) $user['last_name'],
        'email' => (string) $user['email'],
        'role' => (string) $user['role'],
    ];
}

function logout_user(): void
{
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(
            session_name(),
            '',
            time() - 42000,
            $params['path'],
            $params['domain'],
            $params['secure'],
            $params['httponly']
        );
    }
    session_destroy();
}

function require_login(): void
{
    if (!is_logged_in()) {
        flash('error', 'Please sign in to continue.');
        redirect('login.php');
    }
}

function require_guest(): void
{
    if (is_logged_in()) {
        redirect(role_home((string) user_role()));
    }
}

function require_role(array|string $roles): void
{
    require_login();
    $roles = (array) $roles;
    if (!in_array((string) user_role(), $roles, true)) {
        http_response_code(403);
        $pageTitle = 'Access denied';
        require __DIR__ . '/header.php';
        echo '<main class="auth-page"><section class="container py-5"><div class="auth-card auth-card-compact text-center"><div class="auth-icon danger"><i class="bi bi-shield-lock"></i></div><h1>Access denied</h1><p class="text-muted">Your account does not have permission to open this page.</p><a class="btn btn-coffee" href="' . e(url(role_home((string) user_role()))) . '">Return to dashboard</a></div></section></main>';
        require __DIR__ . '/footer.php';
        exit;
    }
}

function find_user_by_email(string $email): ?array
{
    $stmt = db()->prepare('SELECT * FROM users WHERE email = :email LIMIT 1');
    $stmt->execute(['email' => strtolower(trim($email))]);
    $user = $stmt->fetch();
    return $user ?: null;
}

function attempt_login(string $email, string $password): array
{
    if (login_is_temporarily_locked()) {
        return ['ok' => false, 'message' => 'Too many sign-in attempts. Please wait a minute and try again.'];
    }

    $user = find_user_by_email($email);
    if (!$user || !password_verify($password, (string) $user['password_hash'])) {
        record_failed_login();
        return ['ok' => false, 'message' => 'The email or password is incorrect.'];
    }

    if (($user['status'] ?? '') !== 'ACTIVE') {
        record_failed_login();
        return ['ok' => false, 'message' => 'This account is inactive. Please contact Franscoffee Shop.'];
    }

    clear_failed_logins();
    login_user($user);
    if (($user['role'] ?? '') === 'CUSTOMER' && function_exists('cart_merge_guest_to_user')) {
        cart_merge_guest_to_user((int) $user['id']);
    }

    $stmt = db()->prepare('UPDATE users SET last_login_at = NOW() WHERE id = :id');
    $stmt->execute(['id' => $user['id']]);
    log_activity((int) $user['id'], 'LOGIN', 'users', (int) $user['id']);

    return ['ok' => true, 'user' => $user];
}

function register_customer(array $data): array
{
    $firstName = trim((string) ($data['first_name'] ?? ''));
    $lastName = trim((string) ($data['last_name'] ?? ''));
    $email = strtolower(trim((string) ($data['email'] ?? '')));
    $phone = trim((string) ($data['phone'] ?? ''));
    $password = (string) ($data['password'] ?? '');
    $confirm = (string) ($data['confirm_password'] ?? '');

    $errors = [];
    if ($firstName === '' || strlen($firstName) > 80) $errors[] = 'Enter a valid first name.';
    if ($lastName === '' || strlen($lastName) > 80) $errors[] = 'Enter a valid last name.';
    if (!filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 190) $errors[] = 'Enter a valid email address.';
    if ($phone !== '' && strlen($phone) > 30) $errors[] = 'Enter a valid phone number.';
    if (!password_is_strong($password)) $errors[] = 'Password must be at least 8 characters and include uppercase, lowercase, and a number.';
    if ($password !== $confirm) $errors[] = 'Password confirmation does not match.';
    if ($email !== '' && find_user_by_email($email)) $errors[] = 'An account with that email already exists.';

    if ($errors) {
        return ['ok' => false, 'errors' => $errors];
    }

    $pdo = db();
    $pdo->beginTransaction();
    try {
        $stmt = $pdo->prepare(
            'INSERT INTO users (first_name,last_name,email,phone,password_hash,role,status) '
            . 'VALUES (:first_name,:last_name,:email,:phone,:password_hash,\'CUSTOMER\',\'ACTIVE\')'
        );
        $stmt->execute([
            'first_name' => $firstName,
            'last_name' => $lastName,
            'email' => $email,
            'phone' => $phone !== '' ? $phone : null,
            'password_hash' => password_hash($password, PASSWORD_DEFAULT),
        ]);
        $userId = (int) $pdo->lastInsertId();
        $stmt = $pdo->prepare('INSERT INTO customer_profiles (user_id) VALUES (:user_id)');
        $stmt->execute(['user_id' => $userId]);
        $pdo->commit();
        log_activity($userId, 'REGISTER', 'users', $userId);
        return ['ok' => true, 'user_id' => $userId];
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        throw $e;
    }
}

function create_password_reset(string $email): ?string
{
    $user = find_user_by_email($email);
    if (!$user || ($user['status'] ?? '') !== 'ACTIVE') {
        return null;
    }

    $rawToken = bin2hex(random_bytes(32));
    $tokenHash = hash('sha256', $rawToken);
    $minutes = max(10, (int) (app_config('auth')['reset_token_minutes'] ?? 60));

    $pdo = db();
    $pdo->prepare('DELETE FROM password_reset_tokens WHERE user_id = :user_id AND used_at IS NULL')
        ->execute(['user_id' => $user['id']]);

    $stmt = $pdo->prepare(
        'INSERT INTO password_reset_tokens (user_id,token_hash,expires_at) '
        . 'VALUES (:user_id,:token_hash,DATE_ADD(NOW(), INTERVAL ' . $minutes . ' MINUTE))'
    );
    $stmt->execute(['user_id' => $user['id'], 'token_hash' => $tokenHash]);
    log_activity((int) $user['id'], 'PASSWORD_RESET_REQUEST', 'users', (int) $user['id']);

    return $rawToken;
}

function valid_password_reset(string $rawToken): ?array
{
    if ($rawToken === '' || strlen($rawToken) !== 64 || !ctype_xdigit($rawToken)) {
        return null;
    }
    $stmt = db()->prepare(
        'SELECT prt.*, u.email, u.first_name FROM password_reset_tokens prt '
        . 'JOIN users u ON u.id = prt.user_id '
        . 'WHERE prt.token_hash = :token_hash AND prt.used_at IS NULL '
        . 'AND prt.expires_at > NOW() AND u.status = \'ACTIVE\' LIMIT 1'
    );
    $stmt->execute(['token_hash' => hash('sha256', $rawToken)]);
    $row = $stmt->fetch();
    return $row ?: null;
}

function reset_password_with_token(string $rawToken, string $password, string $confirm): array
{
    $reset = valid_password_reset($rawToken);
    if (!$reset) {
        return ['ok' => false, 'errors' => ['This reset link is invalid or has expired.']];
    }

    $errors = [];
    if (!password_is_strong($password)) $errors[] = 'Password must be at least 8 characters and include uppercase, lowercase, and a number.';
    if ($password !== $confirm) $errors[] = 'Password confirmation does not match.';
    if ($errors) return ['ok' => false, 'errors' => $errors];

    $pdo = db();
    $pdo->beginTransaction();
    try {
        $pdo->prepare('UPDATE users SET password_hash = :hash, updated_at = NOW() WHERE id = :id')
            ->execute(['hash' => password_hash($password, PASSWORD_DEFAULT), 'id' => $reset['user_id']]);
        $pdo->prepare('UPDATE password_reset_tokens SET used_at = NOW() WHERE id = :id')
            ->execute(['id' => $reset['id']]);
        $pdo->prepare('UPDATE password_reset_tokens SET used_at = NOW() WHERE user_id = :user_id AND used_at IS NULL')
            ->execute(['user_id' => $reset['user_id']]);
        $pdo->commit();
        log_activity((int) $reset['user_id'], 'PASSWORD_RESET_COMPLETE', 'users', (int) $reset['user_id']);
        return ['ok' => true];
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        throw $e;
    }
}

function log_activity(?int $userId, string $action, ?string $entityType = null, ?int $entityId = null, array $details = []): void
{
    try {
        $stmt = db()->prepare(
            'INSERT INTO activity_logs (user_id,action,entity_type,entity_id,ip_address,details_json) '
            . 'VALUES (:user_id,:action,:entity_type,:entity_id,:ip_address,:details_json)'
        );
        $stmt->execute([
            'user_id' => $userId,
            'action' => substr($action, 0, 80),
            'entity_type' => $entityType,
            'entity_id' => $entityId,
            'ip_address' => client_ip(),
            'details_json' => $details ? json_encode($details, JSON_UNESCAPED_SLASHES) : null,
        ]);
    } catch (Throwable $e) {
        // Authentication must continue even if logging is temporarily unavailable.
    }
}

function login_is_temporarily_locked(): bool
{
    $limit = max(3, (int) (app_config('auth')['max_login_attempts'] ?? 5));
    $lockSeconds = max(30, (int) (app_config('auth')['login_lock_seconds'] ?? 60));
    $attempts = (int) ($_SESSION['_login_attempts']['count'] ?? 0);
    $last = (int) ($_SESSION['_login_attempts']['last'] ?? 0);
    if ($attempts < $limit) return false;
    if (time() - $last >= $lockSeconds) {
        clear_failed_logins();
        return false;
    }
    return true;
}

function record_failed_login(): void
{
    $current = (int) ($_SESSION['_login_attempts']['count'] ?? 0);
    $_SESSION['_login_attempts'] = ['count' => $current + 1, 'last' => time()];
}

function clear_failed_logins(): void
{
    unset($_SESSION['_login_attempts']);
}

function validate_authenticated_session(): void
{
    $sessionUser = current_user();
    if (!$sessionUser || empty($sessionUser['id'])) return;

    $stmt = db()->prepare('SELECT id, first_name, last_name, email, role, status FROM users WHERE id = :id LIMIT 1');
    $stmt->execute(['id' => (int) $sessionUser['id']]);
    $fresh = $stmt->fetch();

    if (!$fresh || ($fresh['status'] ?? '') !== 'ACTIVE') {
        $_SESSION = [];
        session_regenerate_id(true);
        $_SESSION['_csrf'] = bin2hex(random_bytes(32));
        flash('error', 'Your session ended because this account is no longer active.');
        redirect('login.php');
    }

    $_SESSION['user'] = [
        'id' => (int) $fresh['id'],
        'first_name' => (string) $fresh['first_name'],
        'last_name' => (string) $fresh['last_name'],
        'email' => (string) $fresh['email'],
        'role' => (string) $fresh['role'],
    ];
}
