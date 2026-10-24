<?php

declare(strict_types=1);

function app_config(?string $section = null): array
{
    global $APP_CONFIG;
    if ($section === null) {
        return $APP_CONFIG;
    }
    return $APP_CONFIG[$section] ?? [];
}

function e(?string $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function url(string $path = ''): string
{
    $base = rtrim((string) (app_config('app')['base_url'] ?? ''), '/');
    return $base . '/' . ltrim($path, '/');
}

function asset(string $path): string
{
    return url('assets/' . ltrim($path, '/'));
}

function redirect(string $path): never
{
    if (preg_match('#^https?://#i', $path)) {
        header('Location: ' . $path);
    } else {
        header('Location: ' . url($path));
    }
    exit;
}

function is_post(): bool
{
    return ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST';
}

function flash(string $key, ?string $message = null): ?string
{
    if ($message !== null) {
        $_SESSION['_flash'][$key] = $message;
        return null;
    }

    $value = $_SESSION['_flash'][$key] ?? null;
    unset($_SESSION['_flash'][$key]);
    return $value;
}

function old(string $key, string $default = ''): string
{
    return e((string) ($_SESSION['_old'][$key] ?? $default));
}

function set_old_input(array $input, array $except = ['password', 'confirm_password']): void
{
    foreach ($except as $key) {
        unset($input[$key]);
    }
    $_SESSION['_old'] = $input;
}

function clear_old_input(): void
{
    unset($_SESSION['_old']);
}

function money(float $amount): string
{
    return '₱' . number_format($amount, 2);
}

function current_user(): ?array
{
    return $_SESSION['user'] ?? null;
}

function is_logged_in(): bool
{
    return current_user() !== null;
}

function user_role(): ?string
{
    return current_user()['role'] ?? null;
}

function role_home(string $role): string
{
    return match ($role) {
        'ADMIN' => 'admin/dashboard.php',
        'STAFF' => 'staff/dashboard.php',
        default => 'customer/dashboard.php',
    };
}

function client_ip(): ?string
{
    $ip = $_SERVER['REMOTE_ADDR'] ?? null;
    return is_string($ip) ? substr($ip, 0, 45) : null;
}

function status_badge_class(string $status): string
{
    return match (strtoupper($status)) {
        'ACTIVE', 'PAID', 'COMPLETED', 'READY' => 'text-bg-success',
        'PENDING', 'UNPAID', 'LOW_STOCK' => 'text-bg-warning',
        'CONFIRMED', 'PREPARING', 'OUT_FOR_DELIVERY' => 'text-bg-info',
        'FAILED', 'CANCELLED', 'INACTIVE' => 'text-bg-danger',
        default => 'text-bg-secondary',
    };
}

function password_is_strong(string $password): bool
{
    return strlen($password) >= 8
        && preg_match('/[A-Z]/', $password) === 1
        && preg_match('/[a-z]/', $password) === 1
        && preg_match('/\d/', $password) === 1;
}

function app_is_debug(): bool
{
    return (bool) (app_config('app')['debug'] ?? false);
}

function sql_add_search_filter(array &$where, array &$params, array $columns, string $query, string $prefix = 'q'): void
{
    $terms = [];
    foreach ($columns as $index => $column) {
        $key = $prefix . $index;
        $terms[] = $column . ' LIKE :' . $key;
        $params[$key] = '%' . $query . '%';
    }
    $where[] = '(' . implode(' OR ', $terms) . ')';
}
