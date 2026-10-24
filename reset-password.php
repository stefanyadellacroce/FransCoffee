<?php

declare(strict_types=1);
require_once __DIR__ . '/includes/bootstrap.php';
require_guest();

$pageTitle = 'Reset password';
$bodyClass = 'auth-body';
$token = trim((string) ($_GET['token'] ?? $_POST['token'] ?? ''));
$reset = valid_password_reset($token);
$errors = [];

if (is_post()) {
    verify_csrf_or_abort();
    $result = reset_password_with_token($token, (string) ($_POST['password'] ?? ''), (string) ($_POST['confirm_password'] ?? ''));
    if ($result['ok']) {
        flash('success', 'Your password has been updated. Sign in with your new password.');
        redirect('login.php');
    }
    $errors = $result['errors'];
    $reset = valid_password_reset($token);
}

require __DIR__ . '/includes/header.php';
?>
<main class="auth-page auth-simple-page">
    <section class="auth-card auth-card-compact">
        <a class="auth-mobile-brand always-show" href="<?= url('index.php') ?>"><img src="<?= asset('images/logo/logo.png') ?>" alt="Franscoffee Shop logo"><span>Franscoffee</span></a>
        <div class="auth-icon"><i class="bi bi-shield-check"></i></div>
        <p class="auth-kicker">NEW PASSWORD</p>
        <h1>Reset your password</h1>
        <?php if (!$reset): ?>
            <div class="alert alert-danger">This reset link is invalid, already used, or expired.</div>
            <a class="btn btn-coffee w-100" href="<?= url('forgot-password.php') ?>">Request a new link</a>
        <?php else: ?>
            <p class="text-muted">Create a new password for <strong><?= e($reset['email']) ?></strong>.</p>
            <?php if ($errors): ?><div class="alert alert-danger"><ul class="mb-0"><?php foreach ($errors as $error): ?><li><?= e($error) ?></li><?php endforeach; ?></ul></div><?php endif; ?>
            <form method="post" data-disable-on-submit>
                <?= csrf_field() ?>
                <input type="hidden" name="token" value="<?= e($token) ?>">
                <div class="mb-3"><label class="form-label" for="password">New password</label><div class="password-field"><input class="form-control" id="password" name="password" type="password" autocomplete="new-password" required><button class="password-toggle" type="button" data-password-toggle="#password" aria-label="Show password"><i class="bi bi-eye"></i></button></div></div>
                <div class="mb-2"><label class="form-label" for="confirm_password">Confirm new password</label><div class="password-field"><input class="form-control" id="confirm_password" name="confirm_password" type="password" autocomplete="new-password" required><button class="password-toggle" type="button" data-password-toggle="#confirm_password" aria-label="Show password"><i class="bi bi-eye"></i></button></div></div>
                <p class="form-note">Use at least 8 characters with uppercase, lowercase, and a number.</p>
                <button class="btn btn-coffee w-100" type="submit">Update password</button>
            </form>
        <?php endif; ?>
        <a class="auth-back justify-content-center" href="<?= url('login.php') ?>"><i class="bi bi-arrow-left"></i> Back to sign in</a>
    </section>
</main>
<?php require __DIR__ . '/includes/footer.php'; ?>
