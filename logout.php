<?php

declare(strict_types=1);
require_once __DIR__ . '/includes/bootstrap.php';

if (!is_logged_in()) {
    redirect('login.php');
}

if (!is_post()) {
    $pageTitle = 'Sign out';
    require __DIR__ . '/includes/header.php';
    ?>
    <main class="auth-page"><section class="container py-5"><div class="auth-card auth-card-compact text-center"><div class="auth-icon"><i class="bi bi-box-arrow-right"></i></div><h1>Sign out?</h1><p class="text-muted">You’ll need to sign in again to return to your dashboard.</p><form method="post"><?= csrf_field() ?><button class="btn btn-coffee" type="submit">Sign out</button> <a class="btn btn-outline-coffee" href="<?= url(role_home((string) user_role())) ?>">Cancel</a></form></div></section></main>
    <?php
    require __DIR__ . '/includes/footer.php';
    exit;
}

verify_csrf_or_abort();
$user = current_user();
if ($user) log_activity((int) $user['id'], 'LOGOUT', 'users', (int) $user['id']);
logout_user();
header('Location: ' . url('login.php?signed_out=1'));
exit;
