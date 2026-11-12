<?php
declare(strict_types=1);

$u = require_login();

if (is_post()) {
    csrf_check();
    $lama = (string)($_POST['password_lama'] ?? '');
    $baru = (string)($_POST['password_baru'] ?? '');
    $konfirmasi = (string)($_POST['password_konfirmasi'] ?? '');

    $userDb = q_one('SELECT password_hash FROM users WHERE id = ?', [$u['id']]);

    if (!password_verify($lama, $userDb['password_hash'])) {
        flash('error', 'Password lama tidak sesuai.');
    } elseif (strlen($baru) < 6) {
        flash('error', 'Password baru minimal harus 6 karakter.');
    } elseif ($baru !== $konfirmasi) {
        flash('error', 'Konfirmasi password baru tidak cocok.');
    } else {
        q('UPDATE users SET password_hash = ? WHERE id = ?', [password_hash($baru, PASSWORD_DEFAULT), $u['id']]);
        log_act('ubah_password', 'Pengguna mengubah password akun pribadi');
        flash('success', 'Password Anda berhasil diperbarui.');
        redirect(url('akun'));
    }
}

render_header('Profil & Akun Saya', 'akun');
?>
<div class="page-head">
    <h2>Pengaturan Akun</h2>
</div>

<div class="grid-2">
    <div class="card">
        <h3>Informasi Akun</h3>
        <table class="table compact">
            <tr><th style="width:160px">Nama Lengkap</th><td><strong><?= h($u['nama']) ?></strong></td></tr>
            <tr><th>Username</th><td><code><?= h($u['username']) ?></code></td></tr>
            <tr><th>Hak Akses / Peran</th><td><span class="badge badge-info"><?= h(ROLES[$u['role']]) ?></span></td></tr>
            <?php if ($u['bidang_nama']): ?>
                <tr><th>Unit Pengampu</th><td><?= h($u['bidang_nama']) ?> (<?= h($u['bidang_singkatan']) ?>)</td></tr>
            <?php endif; ?>
            <?php if ($u['entitas_nama']): ?>
                <tr><th>Satker / Unit Kerja</th><td><?= h($u['entitas_nama']) ?> (<?= h(LEVELS[$u['entitas_level']]) ?>)</td></tr>
            <?php endif; ?>
            <tr><th>Login Terakhir</th><td class="muted small"><?= h($u['last_login'] ?? '–') ?></td></tr>
        </table>
    </div>

    <div class="card">
        <h3>Ganti Password</h3>
        <form method="post" class="stack">
            <?= csrf_field() ?>
            <label>Password Lama
                <input type="password" name="password_lama" required>
            </label>
            <label>Password Baru (min. 6 karakter)
                <input type="password" name="password_baru" required minlength="6">
            </label>
            <label>Ulangi Password Baru
                <input type="password" name="password_konfirmasi" required minlength="6">
            </label>
            <button class="btn btn-primary">Simpan Password Baru</button>
        </form>
    </div>
</div>
<?php render_footer();
