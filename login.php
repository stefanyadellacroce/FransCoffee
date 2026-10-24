<?php

declare(strict_types=1);
require_once __DIR__ . '/includes/bootstrap.php';
require_guest();

$pageTitle = 'Sign in';
$bodyClass = 'auth-body';
$error = null;
$success = flash('success');
if (isset($_GET['signed_out'])) $success = 'You have been signed out.';
$flashError = flash('error');

if (is_post()) {
    verify_csrf_or_abort();
    $email = strtolower(trim((string) ($_POST['email'] ?? '')));
    $password = (string) ($_POST['password'] ?? '');
    set_old_input(['email' => $email]);

    if (!filter_var($email, FILTER_VALIDATE_EMAIL) || $password === '') {
        $error = 'Enter your email address and password.';
    } else {
        $result = attempt_login($email, $password);
        if ($result['ok']) {
            clear_old_input();
            flash('success', 'Welcome back to Franscoffee Shop.');
            $destination = role_home((string) $result['user']['role']);
            if (($result['user']['role'] ?? '') === 'CUSTOMER') {
                $intended = consume_intended_url();
                if ($intended) $destination = $intended;
            } else {
                consume_intended_url();
            }
            redirect($destination);
        }
        $error = $result['message'];
    }
}

require __DIR__ . '/includes/header.php';
?>
<main class="auth-page">
    <div class="auth-shell">
        <section class="auth-visual" aria-label="Franscoffee brand">
            <img src="<?= asset('images/logo/logo.png') ?>" alt="Franscoffee Shop">
            <div class="auth-visual-copy">
                <span class="eyebrow text-light">FRANSCOFFEE SHOP</span>
                <h1>Good coffee,<br>brighter days.</h1>
                <p>Sign in to continue ordering, managing orders, or running the shop.</p>
            </div>
        </section>
        <section class="auth-form-panel">
            <div class="auth-form-wrap">
                <a class="auth-mobile-brand" href="<?= url('index.php') ?>"><img src="<?= asset('images/logo/logo.png') ?>" alt="Franscoffee Shop logo"><span>Franscoffee</span></a>
                <p class="auth-kicker">WELCOME BACK</p>
                <h2>Sign in to your account</h2>
                <p class="text-muted mb-4">Use your Franscoffee email and password.</p>

                <?php if ($success): ?><div class="alert alert-success" role="status"><?= e($success) ?></div><?php endif; ?>
                <?php if ($flashError): ?><div class="alert alert-danger" role="alert"><?= e($flashError) ?></div><?php endif; ?>
                <?php if ($error): ?><div class="alert alert-danger" role="alert"><?= e($error) ?></div><?php endif; ?>

                <form method="post" data-disable-on-submit novalidate>
                    <?= csrf_field() ?>
                    <div class="mb-3">
                        <label class="form-label" for="email">Email address</label>
                        <input class="form-control" id="email" name="email" type="email" autocomplete="email" value="<?= old('email') ?>" required autofocus>
                    </div>
                    <div class="mb-2">
                        <div class="d-flex justify-content-between gap-3"><label class="form-label" for="password">Password</label><a class="form-help-link" href="<?= url('forgot-password.php') ?>">Forgot password?</a></div>
                        <div class="password-field">
                            <input class="form-control" id="password" name="password" type="password" autocomplete="current-password" required>
                            <button class="password-toggle" type="button" data-password-toggle="#password" aria-label="Show password"><i class="bi bi-eye"></i></button>
                        </div>
                    </div>
                    <button class="btn btn-coffee w-100 mt-3" type="submit">Sign in</button>
                </form>
                <p class="auth-switch">New to Franscoffee? <a href="<?= url('register.php') ?>">Create a customer account</a></p>
                <a class="auth-back" href="<?= url('index.php') ?>"><i class="bi bi-arrow-left"></i> Back to home</a>
            </div>
        </section>
    </div>
</main>
<?php require __DIR__ . '/includes/footer.php'; ?>
