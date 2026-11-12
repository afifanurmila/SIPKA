<?php
declare(strict_types=1);

if (current_user()) {
    redirect(url('dashboard'));
}

$error = null;
if (is_post()) {
    csrf_check();
    $username = trim((string)($_POST['username'] ?? ''));
    $password = (string)($_POST['password'] ?? '');
    $user = q_one('SELECT * FROM users WHERE username = ? AND aktif = 1', [$username]);
    if ($user && password_verify($password, $user['password_hash'])) {
        session_regenerate_id(true);
        $_SESSION['uid'] = (int)$user['id'];
        q('UPDATE users SET last_login = ? WHERE id = ?', [date('Y-m-d H:i:s'), $user['id']]);
        current_user(true);
        log_act('login', 'Masuk ke sistem');
        redirect(url($user['role'] === 'entitas' ? 'rapor' : 'dashboard'));
    }
    $error = 'Username atau password salah.';
}
$demo = [
    ['admin', 'admin123', 'Super Admin — Biro Renkeu'],
    ['inspektorat', 'pengampu123', 'Pengampu Inspektorat'],
    ['biroumum', 'pengampu123', 'Pengampu BMN & Barjas'],
    ['birohos', 'pengampu123', 'Pengampu Biro HOS'],
    ['lanjakarta', 'satker123', 'Satker LAN Jakarta'],
    ['pusdatin', 'satker123', 'Unit Pusdatin'],
    ['pimpinan', 'pimpinan123', 'Pimpinan'],
];
?><!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Masuk · <?= APP_NAME ?></title>
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap">
    <link rel="stylesheet" href="https://unpkg.com/@idds/js@latest/dist/style.css">
    <link rel="stylesheet" href="assets/style.css">
</head>
<body class="login-page">
<div class="login-wrap">
    <section class="login-hero">
        <div style="display:flex; align-items:center; gap:10px; margin-bottom:16px;">
            <div class="idds-flag-bar" style="height:32px;"></div>
            <span class="idds-badge-gov" style="font-size:11px;">INA DIGITAL · SPBE</span>
        </div>
        <h1><?= APP_NAME ?></h1>
        <p><?= h(APP_FULLNAME) ?><br>Lembaga Administrasi Negara Republik Indonesia</p>
        <ul>
            <li>Penilaian kinerja anggaran Satker &amp; Unit Organisasi</li>
            <li>Standar Desain INA Digital Design System (IDDS)</li>
            <li>Bobot &amp; indikator dinamis per tahun anggaran</li>
            <li>Penetapan kategori Reward &amp; Punishment otomatis</li>
        </ul>
    </section>
            <li>Verifikasi, sanggahan, dan penguncian hasil</li>
        </ul>
    </section>
    <section class="login-card card">
        <h2>Masuk</h2>
        <?php if ($error): ?><div class="alert alert-error"><?= h($error) ?></div><?php endif; ?>
        <form method="post" class="stack">
            <?= csrf_field() ?>
            <label>Username <input name="username" required autofocus value="<?= h($_POST['username'] ?? '') ?>"></label>
            <label>Password <input type="password" name="password" required></label>
            <button class="btn btn-primary btn-block">Masuk</button>
        </form>
        <details class="demo">
            <summary>Akun demo (lingkungan lokal)</summary>
            <table class="table compact">
                <?php foreach ($demo as [$un, $pw, $ket]): ?>
                    <tr><td><code><?= h($un) ?></code></td><td><code><?= h($pw) ?></code></td><td class="muted"><?= h($ket) ?></td></tr>
                <?php endforeach; ?>
            </table>
        </details>
    </section>
</div>
</body>
</html>
