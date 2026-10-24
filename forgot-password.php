<?php

declare(strict_types=1);
require_once __DIR__ . '/includes/bootstrap.php';
require_guest();

$pageTitle = 'Forgot password';
$bodyClass = 'auth-body';
$message = null;
$debugResetUrl = null;
$error = null;
$resetAvailable = app_is_debug();

if (is_post() && $resetAvailable) {
    verify_csrf_or_abort();
    $email = strtolower(trim((string) ($_POST['email'] ?? '')));
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Enter a valid email address.';
    } else {
        $token = create_password_reset($email);
        $message = 'If that email belongs to an active account, a password reset request has been created.';
        if ($token && app_is_debug()) {
            $debugResetUrl = url('reset-password.php?token=' . urlencode($token));
        }
    }
}

require __DIR__ . '/includes/header.php';
?>
<main class="auth-page auth-simple-page">
    <section class="auth-card auth-card-compact">
        <a class="auth-mobile-brand always-show" href="<?= url('index.php') ?>"><img src="<?= asset('images/logo/logo.png') ?>" alt="Franscoffee Shop logo"><span>Franscoffee</span></a>
        <div class="auth-icon"><i class="bi bi-key"></i></div>
        <p class="auth-kicker">ACCOUNT RECOVERY</p>
        <h1>Forgot your password?</h1>
        <?php if ($resetAvailable): ?><p class="text-muted">Enter the email associated with your account.</p><?php endif; ?>
        <?php if ($error): ?><div class="alert alert-danger"><?= e($error) ?></div><?php endif; ?>
        <?php if ($message): ?><div class="alert alert-success"><?= e($message) ?></div><?php endif; ?>
        <?php if (!$resetAvailable): ?><div class="alert alert-info">Online password reset is not available yet. Please contact the shop for help with your account.</div><?php endif; ?>
        <?php if ($debugResetUrl): ?>
            <div class="local-reset-box"><strong>Local testing link</strong><p class="mb-2">Email delivery is not configured in this local demo. Because debug mode is enabled, use this temporary reset link:</p><a href="<?= e($debugResetUrl) ?>">Reset password now</a></div>
        <?php endif; ?>
        <?php if ($resetAvailable): ?><form method="post" data-disable-on-submit>
            <?= csrf_field() ?>
            <div class="mb-3"><label class="form-label" for="email">Email address</label><input class="form-control" id="email" name="email" type="email" autocomplete="email" required></div>
            <button class="btn btn-coffee w-100" type="submit">Create reset request</button>
        </form>
        <?php endif; ?>
        <a class="auth-back justify-content-center" href="<?= url('login.php') ?>"><i class="bi bi-arrow-left"></i> Back to sign in</a>
    </section>
</main>
<?php require __DIR__ . '/includes/footer.php'; ?>
