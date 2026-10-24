<?php $footerMode = $footerMode ?? 'compact'; ?>
<?php if ($footerMode === 'public'): ?>
<footer class="fc-footer fc-footer-public mt-auto">
    <div class="container-fluid fc-container py-5">
        <div class="row g-4 align-items-start">
            <div class="col-lg-4">
                <a class="footer-brand" href="<?= url('index.php') ?>">
                    <span class="brand-mark-wrap"><img src="<?= e(shop_logo_url()) ?>" class="brand-mark" alt="Franscoffee Shop logo"></span>
                    <span><?= e(shop_setting('shop_name', 'Franscoffee Shop')) ?></span>
                </a>
                <p class="footer-copy mt-3 mb-0"><?= e(shop_setting('tagline', 'Good Coffee, Brighter Days.')) ?> Freshly brewed, simply served.</p>
            </div>
            <div class="col-6 col-lg-2">
                <h2 class="footer-title">Explore</h2>
                <a href="<?= url('menu.php') ?>">Menu</a>
                <a href="<?= url('about.php') ?>">About us</a>
                <a href="<?= url('contact.php') ?>">Contact</a>
            </div>
            <div class="col-6 col-lg-3">
                <h2 class="footer-title">Visit</h2>
                <p class="mb-1"><?= e(shop_setting('store_address', 'Cebu City, Cebu')) ?></p>
                <p class="mb-0 text-muted"><?= e(shop_setting('business_hours', 'Monday–Sunday · 7:00 AM–9:00 PM')) ?></p>
            </div>
            <div class="col-lg-3">
                <h2 class="footer-title">Contact</h2>
                <a href="mailto:<?= e(shop_setting('contact_email', 'francis@franscoffee.com')) ?>"><?= e(shop_setting('contact_email', 'francis@franscoffee.com')) ?></a>
                <span><?= e(shop_setting('contact_phone', '09XX XXX XXXX')) ?></span>
            </div>
        </div>
        <div class="footer-bottom mt-5 pt-4 d-flex flex-column flex-md-row gap-2 justify-content-between">
            <span>© <?= date('Y') ?> <?= e(shop_setting('shop_name', 'Franscoffee Shop')) ?>.</span>
            <span><?= e(shop_setting('tagline', 'Good Coffee, Brighter Days.')) ?></span>
        </div>
    </div>
</footer>
<?php else: ?>
<footer class="fc-footer mt-auto">
    <div class="container-fluid fc-container py-4 d-flex flex-column flex-md-row gap-2 justify-content-between align-items-md-center">
        <span>© <?= date('Y') ?> <?= e(shop_setting('shop_name', 'Franscoffee Shop')) ?>.</span>
        <span class="text-muted"><?= e(shop_setting('tagline', 'Good Coffee, Brighter Days.')) ?></span>
    </div>
</footer>
<?php endif; ?>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="<?= asset('js/app.js') ?>"></script>
</body>
</html>
