<?php

declare(strict_types=1);
require_once __DIR__ . '/includes/bootstrap.php';
$pageTitle = 'About';
$activeNav = 'about';
$footerMode = 'public';
require __DIR__ . '/includes/header.php';
require __DIR__ . '/includes/navbar.php';
?>
<main>
    <section class="page-hero about-hero">
        <div class="container-fluid fc-container">
            <span class="eyebrow">ABOUT FRANSCOFFEE</span>
            <h1>Good coffee should feel easy.</h1>
            <p>Franscoffee Shop brings together familiar drinks, warm service, and a calm modern space designed around everyday coffee moments.</p>
        </div>
    </section>

    <section class="store-section">
        <div class="container-fluid fc-container">
            <div class="row g-5 align-items-center">
                <div class="col-lg-6"><div class="about-art"><img src="<?= asset('images/backgrounds/about-coffee.svg') ?>" alt="Warm illustrated coffee counter at Franscoffee Shop"></div></div>
                <div class="col-lg-6">
                    <span class="eyebrow">OUR STORY</span>
                    <h2 class="about-title">Built for the cup you look forward to.</h2>
                    <p class="about-copy">Franscoffee started with a simple idea: a neighborhood coffee shop can feel polished without feeling complicated. The menu stays familiar, the design stays calm, and every interaction is meant to help people get to the good part faster—their coffee.</p>
                    <p class="about-copy">The web app follows the same principle. Clear menu browsing, transparent pricing, thoughtful customizations, and role-based tools keep the experience useful for customers and practical for the team behind the counter.</p>
                </div>
            </div>
        </div>
    </section>

    <section class="store-section store-section-soft">
        <div class="container-fluid fc-container">
            <div class="section-heading section-heading-centered text-center mx-auto"><span class="eyebrow">WHAT WE VALUE</span><h2>Simple choices. Thoughtful details.</h2></div>
            <div class="row g-4 mt-2">
                <?php foreach ([
                    ['bi-cup-hot', 'Coffee first', 'We keep the menu focused on drinks and food people genuinely want to come back for.'],
                    ['bi-brightness-high', 'Clarity', 'Prices, options, status updates, and store information should always be easy to understand.'],
                    ['bi-people', 'Hospitality', 'Good systems should support warm service instead of making the customer or staff work around them.'],
                    ['bi-flower1', 'Consistency', 'From the visual identity to daily operations, the experience should feel recognizably Franscoffee.'],
                ] as [$icon, $title, $copy]): ?>
                    <div class="col-md-6 col-xl-3"><article class="value-card h-100"><i class="bi <?= e($icon) ?>"></i><h3><?= e($title) ?></h3><p><?= e($copy) ?></p></article></div>
                <?php endforeach; ?>
            </div>
        </div>
    </section>

    <section class="store-section">
        <div class="container-fluid fc-container">
            <div class="story-panel">
                <div class="story-copy"><span class="eyebrow">OUR PROMISE</span><h2>Warm, modern, dependable.</h2><p>Whether you are browsing from your phone or ordering at the counter, the experience should stay welcoming, quick to understand, and easy to trust.</p></div>
                <div class="story-stats"><div><strong>Fresh</strong><span>everyday menu</span></div><div><strong>Clear</strong><span>pricing & options</span></div><div><strong>Cozy</strong><span>brand experience</span></div></div>
            </div>
        </div>
    </section>
</main>
<?php require __DIR__ . '/includes/footer.php'; ?>
