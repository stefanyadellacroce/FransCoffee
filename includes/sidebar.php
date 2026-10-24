<?php
$role = user_role() ?? 'GUEST';
$user = current_user();
$currentScript = str_replace('\\', '/', (string)($_SERVER['PHP_SELF'] ?? ''));
$unreadNotifications = ($role === 'CUSTOMER' && function_exists('customer_unread_notification_count')) ? customer_unread_notification_count((int)($user['id'] ?? 0)) : 0;
$sidebarProfile = ($role === 'CUSTOMER' && function_exists('customer_profile')) ? customer_profile((int)($user['id'] ?? 0)) : null;
$lowStockCount = ($role === 'ADMIN' && function_exists('admin_inventory_metrics')) ? (int)admin_inventory_metrics()['low'] : 0;
$items = match ($role) {
    'ADMIN' => [
        ['Dashboard', 'bi-grid', 'admin/dashboard.php', 0],
        ['Orders', 'bi-receipt', 'staff/orders.php', 0],
        ['POS', 'bi-shop', 'staff/pos.php', 0],
        ['Products', 'bi-cup-hot', 'admin/products.php', 0],
        ['Categories', 'bi-tags', 'admin/categories.php', 0],
        ['Inventory', 'bi-box-seam', 'admin/inventory.php', $lowStockCount],
        ['Suppliers', 'bi-truck', 'admin/suppliers.php', 0],
        ['Payments', 'bi-credit-card', 'admin/payments.php', 0],
        ['Expenses', 'bi-wallet2', 'admin/expenses.php', 0],
        ['Promotions', 'bi-ticket-perforated', 'admin/promotions.php', 0],
        ['Users', 'bi-people', 'admin/users.php', 0],
        ['Activity Logs', 'bi-clock-history', 'admin/activity-logs.php', 0],
        ['Reports', 'bi-bar-chart', 'admin/reports.php', 0],
        ['Settings', 'bi-gear', 'admin/settings.php', 0],
    ],
    'STAFF' => [
        ['Dashboard', 'bi-grid', 'staff/dashboard.php', 0],
        ['POS', 'bi-shop', 'staff/pos.php', 0],
        ['Orders', 'bi-receipt', 'staff/orders.php', 0],
        ['Customers', 'bi-people', 'staff/customers.php', 0],
    ],
    default => [
        ['Dashboard', 'bi-house', 'customer/dashboard.php', 0],
        ['My Orders', 'bi-receipt', 'customer/orders.php', 0],
        ['Notifications', 'bi-bell', 'customer/notifications.php', $unreadNotifications],
        ['Favorites', 'bi-heart', 'customer/favorites.php', 0],
        ['Cart', 'bi-bag', 'cart.php', 0],
        ['Addresses', 'bi-geo-alt', 'customer/addresses.php', 0],
        ['Profile', 'bi-person', 'customer/profile.php', 0],
    ],
};

$isSidebarActive = static function (string $path) use ($currentScript): bool {
    $active = str_ends_with($currentScript, '/' . $path);
    if ($path === 'customer/orders.php' && str_ends_with($currentScript, '/customer/order-details.php')) $active = true;
    if ($path === 'staff/orders.php' && str_ends_with($currentScript, '/staff/order-details.php')) $active = true;
    if ($path === 'admin/products.php' && str_ends_with($currentScript, '/admin/product-form.php')) $active = true;
    if ($path === 'admin/inventory.php' && (str_ends_with($currentScript, '/admin/inventory-form.php') || str_ends_with($currentScript, '/admin/inventory-transactions.php'))) $active = true;
    if ($path === 'admin/suppliers.php' && str_ends_with($currentScript, '/admin/supplier-form.php')) $active = true;
    if ($path === 'admin/expenses.php' && str_ends_with($currentScript, '/admin/expense-form.php')) $active = true;
    if ($path === 'admin/promotions.php' && str_ends_with($currentScript, '/admin/promotion-form.php')) $active = true;
    if ($path === 'admin/users.php' && str_ends_with($currentScript, '/admin/user-form.php')) $active = true;
    return $active;
};

$sidebarAvatar = !empty($sidebarProfile['avatar_path']) ? url((string)$sidebarProfile['avatar_path']) : null;
$sidebarName = trim((string)($user['first_name'] ?? '') . ' ' . (string)($user['last_name'] ?? ''));
?>

<div class="mobile-topbar d-lg-none">
    <a class="mobile-topbar-brand" href="<?= url(role_home((string)$role)) ?>">
        <img src="<?= e(shop_logo_url()) ?>" alt="<?= e(shop_setting('shop_name','Franscoffee Shop')) ?> logo">
        <span><?= e(shop_setting('shop_name','Franscoffee Shop')) ?></span>
    </a>
    <button class="mobile-menu-button" type="button" data-bs-toggle="offcanvas" data-bs-target="#portalSidebarMobile" aria-controls="portalSidebarMobile" aria-label="Open portal navigation">
        <i class="bi bi-list"></i>
    </button>
</div>

<aside class="fc-sidebar d-none d-lg-flex">
    <div>
        <a class="sidebar-brand text-decoration-none text-white" href="<?= url(role_home((string)$role)) ?>">
            <img src="<?= e(shop_logo_url()) ?>" alt="<?= e(shop_setting('shop_name','Franscoffee Shop')) ?> logo">
            <div><strong><?= e(shop_setting('shop_name','Franscoffee Shop')) ?></strong><small><?= e($role) ?></small></div>
        </a>
        <nav class="sidebar-nav" aria-label="Portal navigation">
            <?php foreach ($items as [$label, $icon, $path, $count]): $active = $isSidebarActive($path); ?>
                <a class="sidebar-link <?= $active ? 'active' : '' ?>" href="<?= url($path) ?>" <?= $active ? 'aria-current="page"' : '' ?>>
                    <i class="bi <?= e($icon) ?>"></i><span><?= e($label) ?></span><?php if ((int)$count > 0): ?><em class="sidebar-count"><?= (int)$count > 99 ? '99+' : (int)$count ?></em><?php endif; ?>
                </a>
            <?php endforeach; ?>
        </nav>
    </div>
    <div class="sidebar-account">
        <div class="sidebar-user">
            <?php if ($sidebarAvatar): ?><img class="sidebar-avatar sidebar-avatar-image" src="<?= e($sidebarAvatar) ?>" alt="<?= e(($user['first_name'] ?? 'Customer') . ' profile photo') ?>"><?php else: ?><span class="sidebar-avatar"><?= e(strtoupper(substr((string) ($user['first_name'] ?? 'F'), 0, 1))) ?></span><?php endif; ?>
            <div><strong><?= e($sidebarName) ?></strong><small><?= e($user['email'] ?? '') ?></small></div>
        </div>
        <form method="post" action="<?= url('logout.php') ?>">
            <?= csrf_field() ?>
            <button class="sidebar-logout" type="submit"><i class="bi bi-box-arrow-right"></i><span>Sign out</span></button>
        </form>
    </div>
</aside>

<div class="offcanvas offcanvas-start portal-offcanvas d-lg-none" tabindex="-1" id="portalSidebarMobile" aria-labelledby="portalSidebarMobileLabel">
    <div class="offcanvas-header">
        <a class="sidebar-brand text-decoration-none text-white" href="<?= url(role_home((string)$role)) ?>">
            <img src="<?= e(shop_logo_url()) ?>" alt="<?= e(shop_setting('shop_name','Franscoffee Shop')) ?> logo">
            <div><strong id="portalSidebarMobileLabel"><?= e(shop_setting('shop_name','Franscoffee Shop')) ?></strong><small><?= e($role) ?></small></div>
        </a>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="offcanvas" aria-label="Close navigation"></button>
    </div>
    <div class="offcanvas-body">
        <nav class="sidebar-nav" aria-label="Mobile portal navigation">
            <?php foreach ($items as [$label, $icon, $path, $count]): $active = $isSidebarActive($path); ?>
                <a class="sidebar-link <?= $active ? 'active' : '' ?>" href="<?= url($path) ?>" <?= $active ? 'aria-current="page"' : '' ?>>
                    <i class="bi <?= e($icon) ?>"></i><span><?= e($label) ?></span><?php if ((int)$count > 0): ?><em class="sidebar-count"><?= (int)$count > 99 ? '99+' : (int)$count ?></em><?php endif; ?>
                </a>
            <?php endforeach; ?>
        </nav>
        <div class="sidebar-account mt-auto">
            <div class="sidebar-user">
                <?php if ($sidebarAvatar): ?><img class="sidebar-avatar sidebar-avatar-image" src="<?= e($sidebarAvatar) ?>" alt="<?= e(($user['first_name'] ?? 'Customer') . ' profile photo') ?>"><?php else: ?><span class="sidebar-avatar"><?= e(strtoupper(substr((string) ($user['first_name'] ?? 'F'), 0, 1))) ?></span><?php endif; ?>
                <div><strong><?= e($sidebarName) ?></strong><small><?= e($user['email'] ?? '') ?></small></div>
            </div>
            <form method="post" action="<?= url('logout.php') ?>">
                <?= csrf_field() ?>
                <button class="sidebar-logout" type="submit"><i class="bi bi-box-arrow-right"></i><span>Sign out</span></button>
            </form>
        </div>
    </div>
</div>
