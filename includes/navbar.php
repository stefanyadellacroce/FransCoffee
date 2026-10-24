<?php
$activeNav = $activeNav ?? '';
$shoppingEnabled = !is_logged_in() || user_role() === 'CUSTOMER';
$navCartCount = $shoppingEnabled ? cart_count() : 0;
$navFavoriteCount = (is_logged_in() && user_role() === 'CUSTOMER') ? favorite_count() : 0;
$navUnreadCount = (is_logged_in() && user_role() === 'CUSTOMER' && function_exists('customer_unread_notification_count')) ? customer_unread_notification_count() : 0;
?>
<nav class="navbar navbar-expand-lg fc-navbar sticky-top" aria-label="Main navigation">
    <div class="container-fluid fc-container">
        <a class="navbar-brand d-flex align-items-center gap-2" href="<?= url('index.php') ?>">
            <span class="brand-mark-wrap"><img src="<?= e(shop_logo_url()) ?>" class="brand-mark" alt="Franscoffee Shop logo"></span>
            <span class="brand-text"><?= e(shop_setting('shop_name', 'Franscoffee Shop')) ?></span>
        </a>
        <button class="navbar-toggler border-0" type="button" data-bs-toggle="collapse" data-bs-target="#mainNav" aria-controls="mainNav" aria-expanded="false" aria-label="Toggle navigation">
            <i class="bi bi-list fs-3"></i>
        </button>
        <div class="collapse navbar-collapse" id="mainNav">
            <ul class="navbar-nav ms-auto align-items-lg-center gap-lg-1">
                <li class="nav-item"><a class="nav-link <?= $activeNav === 'home' ? 'active' : '' ?>" href="<?= url('index.php') ?>">Home</a></li>
                <li class="nav-item"><a class="nav-link <?= $activeNav === 'menu' ? 'active' : '' ?>" href="<?= url('menu.php') ?>">Menu</a></li>
                <li class="nav-item"><a class="nav-link <?= $activeNav === 'about' ? 'active' : '' ?>" href="<?= url('about.php') ?>">About</a></li>
                <li class="nav-item"><a class="nav-link <?= $activeNav === 'contact' ? 'active' : '' ?>" href="<?= url('contact.php') ?>">Contact</a></li>
                <li class="nav-item d-none d-lg-block">
                    <a class="nav-icon-link" href="<?= url('menu.php#menu-search') ?>" aria-label="Search menu" title="Search menu"><i class="bi bi-search"></i></a>
                </li>
                <?php if (is_logged_in() && user_role() === 'CUSTOMER'): ?>
                    <li class="nav-item d-none d-lg-block">
                        <a class="nav-icon-link nav-icon-with-count" href="<?= url('customer/notifications.php') ?>" aria-label="Notifications" title="Notifications"><i class="bi bi-bell"></i><?php if ($navUnreadCount > 0): ?><span><?= $navUnreadCount > 99 ? '99+' : $navUnreadCount ?></span><?php endif; ?></a>
                    </li>
                    <li class="nav-item d-none d-lg-block">
                        <a class="nav-icon-link nav-icon-with-count" href="<?= url('customer/favorites.php') ?>" aria-label="Favorites" title="Favorites"><i class="bi bi-heart"></i><?php if ($navFavoriteCount > 0): ?><span><?= $navFavoriteCount > 99 ? '99+' : $navFavoriteCount ?></span><?php endif; ?></a>
                    </li>
                <?php endif; ?>
                <?php if ($shoppingEnabled): ?>
                <li class="nav-item d-none d-lg-block">
                    <a class="nav-icon-link nav-icon-with-count" href="<?= url('cart.php') ?>" aria-label="Cart" title="Cart"><i class="bi bi-bag"></i><?php if ($navCartCount > 0): ?><span><?= $navCartCount > 99 ? '99+' : $navCartCount ?></span><?php endif; ?></a>
                </li>
                <li class="nav-item d-lg-none"><a class="nav-link" href="<?= url('cart.php') ?>">Cart<?= $navCartCount ? ' (' . $navCartCount . ')' : '' ?></a></li>
                <?php endif; ?>
                <?php if (is_logged_in() && user_role() === 'CUSTOMER'): ?>
                    <li class="nav-item d-lg-none"><a class="nav-link" href="<?= url('customer/orders.php') ?>">My Orders</a></li>
                    <li class="nav-item d-lg-none"><a class="nav-link" href="<?= url('customer/notifications.php') ?>">Notifications<?= $navUnreadCount ? ' (' . $navUnreadCount . ')' : '' ?></a></li>
                    <li class="nav-item d-lg-none"><a class="nav-link" href="<?= url('customer/favorites.php') ?>">Favorites<?= $navFavoriteCount ? ' (' . $navFavoriteCount . ')' : '' ?></a></li>
                <?php endif; ?>
                <?php if (is_logged_in()): ?>
                    <li class="nav-item"><a class="btn btn-outline-coffee ms-lg-2" href="<?= url(role_home((string) user_role())) ?>">Dashboard</a></li>
                    <li class="nav-item">
                        <form method="post" action="<?= url('logout.php') ?>" class="ms-lg-1">
                            <?= csrf_field() ?>
                            <button class="btn btn-coffee" type="submit">Sign out</button>
                        </form>
                    </li>
                <?php else: ?>
                    <li class="nav-item"><a class="nav-link ms-lg-2" href="<?= url('register.php') ?>">Create account</a></li>
                    <li class="nav-item"><a class="btn btn-coffee ms-lg-1" href="<?= url('login.php') ?>">Sign in</a></li>
                <?php endif; ?>
            </ul>
        </div>
    </div>
</nav>
