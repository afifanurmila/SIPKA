<?php
$u = current_user();
$title = $GLOBALS['__title'] ?? '';
$active = $GLOBALS['__active'] ?? '';
$nav = [];
if ($u) {
    if ($u['role'] === 'entitas') {
        $nav[] = ['rapor', 'Rapor Saya', '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/><polyline points="10 9 9 9 8 9"/></svg>'];
    } else {
        $nav[] = ['dashboard', 'Dashboard & Peringkat', '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="7" height="9"/><rect x="14" y="3" width="7" height="5"/><rect x="14" y="12" width="7" height="9"/><rect x="3" y="16" width="7" height="5"/></svg>'];
    }
    if (in_array($u['role'], ['admin', 'pengampu'], true)) {
        $nav[] = ['input', 'Input Nilai', '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 20h9"/><path d="M16.5 3.5a2.121 2.121 0 0 1 3 3L7 19l-4 1 1-4L16.5 3.5z"/></svg>'];
    }
    $nav[] = ['sanggahan', 'Sanggahan', '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></svg>'];
    if ($u['role'] === 'admin') {
        $nav[] = ['konfig', 'Konfigurasi', '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1 0 2.83 2 2 0 0 1-2.83 0l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-2 2 2 2 0 0 1-2-2v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 0 1-2.83 0 2 2 0 0 1 0-2.83l.06-.06a1.65 1.65 0 0 0 .33-1.82 1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1-2-2 2 2 0 0 1 2-2h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 0 1 0-2.83 2 2 0 0 1 2.83 0l.06.06a1.65 1.65 0 0 0 1.82.33H9a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 2-2 2 2 0 0 1 2 2v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 0 1 2.83 0 2 2 0 0 1 0 2.83l-.06.06a1.65 1.65 0 0 0-.33 1.82V9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 2 2 2 2 0 0 1-2 2h-.09a1.65 1.65 0 0 0-1.51 1z"/></svg>'];
        $nav[] = ['log', 'Log Aktivitas', '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>'];
    }
    $nav[] = ['akun', 'Akun Saya', '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>'];
}

// Inisial untuk avatar IDDS
$inisial = '';
if ($u) {
    $parts = explode(' ', trim($u['nama']));
    $inisial = strtoupper(substr($parts[0], 0, 1) . (isset($parts[1]) ? substr($parts[1], 0, 1) : ''));
}
?><!doctype html>
<html lang="id" dir="ltr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= h($title) ?> · <?= APP_NAME ?></title>
    <!-- INA Digital Design System Stylesheet & Inter Font -->
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap">
    <link rel="stylesheet" href="https://unpkg.com/@idds/js@latest/dist/style.css">
    <link rel="stylesheet" href="assets/style.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js" defer></script>
    <script src="assets/app.js" defer></script>
</head>
<body class="idds-body">
<div class="idds-app-shell">
    <!-- Sidebar IDDS -->
    <aside class="idds-sidebar" aria-label="Navigasi Utama">
        <div class="idds-sidebar-brand">
            <div class="idds-brand-logo">
                <span class="idds-flag-bar"></span>
                <span class="idds-logo-text"><?= APP_NAME ?></span>
            </div>
            <div class="idds-brand-sub">Lembaga Administrasi Negara</div>
        </div>

        <nav class="idds-sidebar-nav" aria-label="Menu Utama">
            <ul>
                <?php foreach ($nav as [$page, $label, $iconSvg]): ?>
                    <li>
                        <a href="<?= h(url($page)) ?>" class="<?= $active === $page ? 'active' : '' ?>">
                            <span class="idds-nav-icon"><?= $iconSvg ?></span>
                            <span class="idds-nav-text"><?= h($label) ?></span>
                        </a>
                    </li>
                <?php endforeach; ?>
            </ul>
        </nav>

        <?php if ($u): ?>
            <div class="idds-sidebar-footer">
                <div class="idds-user-card">
                    <div class="idds-avatar"><?= h($inisial) ?></div>
                    <div class="idds-user-info">
                        <div class="idds-user-name" title="<?= h($u['nama']) ?>"><?= h($u['nama']) ?></div>
                        <div class="idds-user-role"><?= h(ROLES[$u['role']]) ?></div>
                    </div>
                </div>
                <form method="post" action="<?= h(url('logout')) ?>" style="margin-top: 12px;">
                    <?= csrf_field() ?>
                    <button class="idds-btn idds-btn-outline idds-btn-sm idds-btn-block">
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/></svg>
                        Keluar
                    </button>
                </form>
            </div>
        <?php endif; ?>
    </aside>

    <!-- Main Container -->
    <div class="idds-main-wrapper">
        <!-- TopBar IDDS -->
        <header class="idds-topbar">
            <div class="idds-topbar-breadcrumb">
                <span class="idds-badge-gov">INA DIGITAL</span>
                <span class="idds-slash">/</span>
                <span class="idds-agency">LAN RI</span>
                <span class="idds-slash">/</span>
                <span class="idds-current-page"><?= h($title) ?></span>
            </div>
            <?php if ($u): ?>
                <div class="idds-topbar-user">
                    <?php if ($u['bidang_singkatan']): ?>
                        <span class="idds-badge idds-badge-neutral"><?= h($u['bidang_singkatan']) ?></span>
                    <?php elseif ($u['entitas_nama']): ?>
                        <span class="idds-badge idds-badge-neutral"><?= h($u['entitas_nama']) ?></span>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </header>

        <!-- Main Content -->
        <main class="idds-content" id="konten-utama">
            <?php foreach (take_flashes() as [$type, $msg]): ?>
                <div class="idds-alert idds-alert-<?= h($type) ?>">
                    <div class="idds-alert-content"><?= nl2br(h($msg)) ?></div>
                </div>
            <?php endforeach; ?>
