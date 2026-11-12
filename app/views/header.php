<?php
$u = current_user();
$title = $GLOBALS['__title'] ?? '';
$active = $GLOBALS['__active'] ?? '';
$nav = [];
if ($u) {
    if ($u['role'] === 'entitas') {
        $nav[] = ['rapor', 'Rapor Saya', '📄'];
    } else {
        $nav[] = ['dashboard', 'Dashboard & Peringkat', '📊'];
    }
    if (in_array($u['role'], ['admin', 'pengampu'], true)) {
        $nav[] = ['input', 'Input Nilai', '✏️'];
    }
    $nav[] = ['sanggahan', 'Sanggahan', '💬'];
    if ($u['role'] === 'admin') {
        $nav[] = ['konfig', 'Konfigurasi', '⚙️'];
        $nav[] = ['log', 'Log Aktivitas', '🕘'];
    }
    $nav[] = ['akun', 'Akun Saya', '👤'];
}
?><!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= h($title) ?> · <?= APP_NAME ?></title>
    <link rel="stylesheet" href="assets/style.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js" defer></script>
    <script src="assets/app.js" defer></script>
</head>
<body>
<div class="layout">
    <aside class="sidebar">
        <div class="brand">
            <div class="logo">S</div>
            <div>
                <strong><?= APP_NAME ?></strong>
                <small>Penilaian Kinerja Anggaran</small>
            </div>
        </div>
        <nav>
            <?php foreach ($nav as [$page, $label, $icon]): ?>
                <a href="<?= h(url($page)) ?>" class="<?= $active === $page ? 'active' : '' ?>"><span class="ico"><?= $icon ?></span><?= h($label) ?></a>
            <?php endforeach; ?>
        </nav>
        <?php if ($u): ?>
            <div class="sidebar-foot">
                <div class="who"><?= h($u['nama']) ?></div>
                <div class="role"><?= h(ROLES[$u['role']]) ?></div>
                <?php if ($u['bidang_singkatan']): ?><div class="role">Pengampu: <?= h($u['bidang_singkatan']) ?></div><?php endif; ?>
                <?php if ($u['entitas_nama']): ?><div class="role"><?= h($u['entitas_nama']) ?></div><?php endif; ?>
                <form method="post" action="<?= h(url('logout')) ?>"><?= csrf_field() ?><button class="btn btn-ghost btn-sm">Keluar</button></form>
            </div>
        <?php endif; ?>
    </aside>
    <main class="main">
        <header class="topbar">
            <h1><?= h($title) ?></h1>
            <span class="muted"><?= h(APP_FULLNAME) ?></span>
        </header>
        <div class="content">
            <?php foreach (take_flashes() as [$type, $msg]): ?>
                <div class="alert alert-<?= h($type) ?>"><?= nl2br(h($msg)) ?></div>
            <?php endforeach; ?>
