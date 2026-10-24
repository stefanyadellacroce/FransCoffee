<?php

declare(strict_types=1);
require_once __DIR__ . '/includes/bootstrap.php';

$pageTitle = 'Contact';
$activeNav = 'contact';
$footerMode = 'public';
$errors = [];

if (is_post()) {
    verify_csrf_or_abort();
    $result = submit_contact_message($_POST);
    if ($result['ok']) {
        clear_old_input();
        flash('success', 'Thanks for reaching out. Your message has been received.');
        redirect('contact.php?sent=1');
    }
    $errors = $result['errors'] ?? ['Unable to send your message.'];
    set_old_input($_POST, ['website']);
}

$success = flash('success');
require __DIR__ . '/includes/header.php';
require __DIR__ . '/includes/navbar.php';
?>
<main>
    <section class="page-hero contact-hero">
        <div class="container-fluid fc-container">
            <span class="eyebrow">CONTACT FRANSCOFFEE</span>
            <h1>Come by, call, or send us a note.</h1>
            <p>Questions about the menu, store hours, or an upcoming order? We’re happy to help.</p>
        </div>
    </section>

    <section class="store-section">
        <div class="container-fluid fc-container">
            <div class="row g-5">
                <div class="col-lg-5">
                    <div class="contact-info-card">
                        <span class="eyebrow">STORE DETAILS</span>
                        <h2>Franscoffee Shop</h2>
                        <div class="contact-detail"><i class="bi bi-geo-alt"></i><div><strong>Visit us</strong><span><?= e(shop_setting('store_address', 'Cebu City, Cebu')) ?></span></div></div>
                        <div class="contact-detail"><i class="bi bi-clock"></i><div><strong>Store hours</strong><span><?= e(shop_setting('business_hours', 'Monday–Sunday · 7:00 AM–9:00 PM')) ?></span></div></div>
                        <div class="contact-detail"><i class="bi bi-telephone"></i><div><strong>Call</strong><span><?= e(shop_setting('contact_phone', '09XX XXX XXXX')) ?></span></div></div>
                        <div class="contact-detail"><i class="bi bi-envelope"></i><div><strong>Email</strong><a href="mailto:<?= e(shop_setting('contact_email', 'francis@franscoffee.com')) ?>"><?= e(shop_setting('contact_email', 'francis@franscoffee.com')) ?></a></div></div>
                        <div class="contact-map-placeholder"><i class="bi bi-geo-alt-fill"></i><span>Map integration placeholder</span><small>Exact map embed can be configured when deployment/store address is finalized.</small></div>
                    </div>
                </div>
                <div class="col-lg-7">
                    <div class="contact-form-card">
                        <span class="eyebrow">SEND A MESSAGE</span>
                        <h2>How can we help?</h2>
                        <?php if ($success): ?><div class="alert alert-success" role="status"><?= e($success) ?></div><?php endif; ?>
                        <?php if ($errors): ?><div class="alert alert-danger"><strong>Please check the form.</strong><ul class="mb-0 mt-2"><?php foreach ($errors as $error): ?><li><?= e($error) ?></li><?php endforeach; ?></ul></div><?php endif; ?>
                        <form method="post" action="<?= url('contact.php') ?>" data-disable-on-submit novalidate>
                            <?= csrf_field() ?>
                            <div class="d-none" aria-hidden="true"><label>Website<input type="text" name="website" tabindex="-1" autocomplete="off"></label></div>
                            <div class="row g-3">
                                <div class="col-md-6"><label class="form-label" for="name">Name</label><input class="form-control" id="name" name="name" maxlength="120" required value="<?= old('name') ?>"></div>
                                <div class="col-md-6"><label class="form-label" for="email">Email</label><input class="form-control" id="email" type="email" name="email" maxlength="190" required value="<?= old('email') ?>"></div>
                                <div class="col-md-6"><label class="form-label" for="phone">Phone <span class="text-muted fw-normal">optional</span></label><input class="form-control" id="phone" name="phone" maxlength="30" value="<?= old('phone') ?>"></div>
                                <div class="col-md-6"><label class="form-label" for="subject">Subject</label><input class="form-control" id="subject" name="subject" maxlength="160" required value="<?= old('subject') ?>"></div>
                                <div class="col-12"><label class="form-label" for="message">Message</label><textarea class="form-control" id="message" name="message" rows="6" minlength="10" maxlength="3000" required><?= old('message') ?></textarea></div>
                            </div>
                            <button class="btn btn-coffee mt-4" type="submit">Send message</button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </section>
</main>
<?php clear_old_input(); require __DIR__ . '/includes/footer.php'; ?>
