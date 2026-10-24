<?php
/** @var string $pageTitle */
$pageTitle = $pageTitle ?? 'Franscoffee Shop';
$bodyClass = $bodyClass ?? '';
?><!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="description" content="<?= e(shop_setting('shop_name', 'Franscoffee Shop')) ?> ordering and management system">
    <title><?= e($pageTitle) ?> · <?= e(shop_setting('shop_name', 'Franscoffee Shop')) ?></title>
    <link rel="icon" type="image/png" href="<?= e(shop_logo_url()) ?>">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700&family=DM+Serif+Display&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <link rel="stylesheet" href="<?= asset('css/app.css') ?>">
</head>
<body class="<?= e($bodyClass) ?>">
