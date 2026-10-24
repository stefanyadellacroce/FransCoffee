<?php

declare(strict_types=1);
require_once __DIR__ . '/includes/bootstrap.php';
require_guest();

$pageTitle = 'Create account';
$bodyClass = 'auth-body';
$errors = [];

if (is_post()) {
    verify_csrf_or_abort();
    set_old_input($_POST);
    $result = register_customer($_POST);
    if ($result['ok']) {
        clear_old_input();
        flash('success', 'Your customer account has been created. You can sign in now.');
        redirect('login.php');
    }
    $errors = $result['errors'];
}

require __DIR__ . '/includes/header.php';
?>
<main class="auth-page">
    <div class="auth-shell auth-shell-register">
        <section class="auth-visual" aria-label="Franscoffee brand">
            <img src="<?= asset('images/logo/logo.png') ?>" alt="Franscoffee Shop">
            <div class="auth-visual-copy">
                <span class="eyebrow text-light">JOIN FRANSCOFFEE</span>
                <h1>Your next cup<br>starts here.</h1>
                <p>Create an account for faster checkout, order tracking, favorites, and future rewards.</p>
            </div>
        </section>
        <section class="auth-form-panel">
            <div class="auth-form-wrap auth-form-wide">
                <a class="auth-mobile-brand" href="<?= url('index.php') ?>"><img src="<?= asset('images/logo/logo.png') ?>" alt="Franscoffee Shop logo"><span>Franscoffee</span></a>
                <p class="auth-kicker">CUSTOMER REGISTRATION</p>
                <h2>Create your account</h2>
                <p class="text-muted mb-4">Staff and admin accounts are not created from this public form.</p>

                <?php if ($errors): ?>
                    <div class="alert alert-danger" role="alert"><strong>Please fix the following:</strong><ul class="mb-0 mt-2"><?php foreach ($errors as $error): ?><li><?= e($error) ?></li><?php endforeach; ?></ul></div>
                <?php endif; ?>

                <form method="post" data-disable-on-submit novalidate>
                    <?= csrf_field() ?>
                    <div class="row g-3">
                        <div class="col-md-6"><label class="form-label" for="first_name">First name</label><input class="form-control" id="first_name" name="first_name" maxlength="80" value="<?= old('first_name') ?>" required></div>
                        <div class="col-md-6"><label class="form-label" for="last_name">Last name</label><input class="form-control" id="last_name" name="last_name" maxlength="80" value="<?= old('last_name') ?>" required></div>
                        <div class="col-12"><label class="form-label" for="email">Email address</label><input class="form-control" id="email" name="email" type="email" maxlength="190" autocomplete="email" value="<?= old('email') ?>" required></div>
                        <div class="col-12"><label class="form-label" for="phone">Phone number <span class="text-muted fw-normal">(optional)</span></label><input class="form-control" id="phone" name="phone" maxlength="30" autocomplete="tel" value="<?= old('phone') ?>" placeholder="09XX XXX XXXX"></div>
                        <div class="col-md-6"><label class="form-label" for="password">Password</label><div class="password-field"><input class="form-control" id="password" name="password" type="password" autocomplete="new-password" required><button class="password-toggle" type="button" data-password-toggle="#password" aria-label="Show password"><i class="bi bi-eye"></i></button></div></div>
                        <div class="col-md-6"><label class="form-label" for="confirm_password">Confirm password</label><div class="password-field"><input class="form-control" id="confirm_password" name="confirm_password" type="password" autocomplete="new-password" required><button class="password-toggle" type="button" data-password-toggle="#confirm_password" aria-label="Show password"><i class="bi bi-eye"></i></button></div></div>
                    </div>
                    <p class="form-note mt-2">Use at least 8 characters with uppercase, lowercase, and a number.</p>
                    <button class="btn btn-coffee w-100 mt-3" type="submit">Create customer account</button>
                </form>
                <p class="auth-switch">Already have an account? <a href="<?= url('login.php') ?>">Sign in</a></p>
                <a class="auth-back" href="<?= url('index.php') ?>"><i class="bi bi-arrow-left"></i> Back to home</a>
            </div>
        </section>
    </div>
</main>
<?php require __DIR__ . '/includes/footer.php'; ?>
