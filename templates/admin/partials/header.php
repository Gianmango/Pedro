<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title><?= e(($title ?? 'Admin') . ' · Admin') ?></title>
    <link rel="stylesheet" href="<?= e(asset('css/style.css')) ?>">
    <link rel="stylesheet" href="<?= e(asset('css/orders.css')) ?>">
</head>
<body class="admin">
<header class="admin-bar">
    <a class="admin-bar__brand" href="/admin">Admin</a>
    <?php if (\App\Auth::check()): ?>
        <nav aria-label="Navigazione admin">
            <a href="/admin/tables" class="<?= ($active ?? '') === 'tables' ? 'is-active' : '' ?>">Tavoli</a>
            <a href="/admin/menu" class="<?= ($active ?? '') === 'menu' ? 'is-active' : '' ?>">Menù</a>
            <a href="/admin/account" class="<?= ($active ?? '') === 'account' ? 'is-active' : '' ?>">Account</a>
        </nav>
        <form method="post" action="/admin/logout">
            <?= csrf_field() ?>
            <button class="link-button">Esci</button>
        </form>
    <?php endif; ?>
</header>
<main class="admin-main">
<?php foreach (flash_pull() as [$flashType, $flashMessage]): ?>
    <p class="flash flash--<?= e($flashType) ?>" role="status"><?= e($flashMessage) ?></p>
<?php endforeach; ?>
