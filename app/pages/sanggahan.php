<?php
declare(strict_types=1);

$u = require_login();
$tahun = pilih_tahun();
if (!$tahun) {
    render_error('Belum ada tahun penilaian', 'Silakan hubungi admin.');
}

// Handler POST: Tanggapi sanggahan (Admin/Pengampu) atau Buat sanggahan (Entitas/Admin)
if (is_post()) {
    csrf_check();
    $aksi = $_POST['aksi'] ?? '';

    if ($aksi === 'buat') {
        $tahunId = (int)($_POST['tahun_id'] ?? $tahun['id']);
        $t = q_one('SELECT * FROM tahun WHERE id = ?', [$tahunId]);
        if (!$t || $t['status'] === 'final') {
            flash('error', 'Sanggahan tidak dapat diajukan untuk tahun yang sudah berstatus final.');
            redirect(url('sanggahan'));
        }

        $entitasId = (int)($_POST['entitas_id'] ?? 0);
        if ($u['role'] === 'entitas') {
            $entitasId = (int)$u['entitas_id'];
        }

        $indikatorId = !empty($_POST['indikator_id']) ? (int)$_POST['indikator_id'] : null;
        $isi = trim((string)($_POST['isi'] ?? ''));

        if ($isi === '') {
            flash('error', 'Uraian sanggahan tidak boleh kosong.');
        } else {
            q(
                'INSERT INTO sanggahan (tahun_id, entitas_id, indikator_id, isi, status, dibuat_oleh, dibuat_pada)
                 VALUES (?, ?, ?, ?, ?, ?, ?)',
                [$tahunId, $entitasId, $indikatorId, $isi, 'diajukan', $u['id'], date('Y-m-d H:i:s')]
            );
            log_act('buat_sanggahan', "Sanggahan diajukan untuk entitas ID $entitasId");
            flash('success', 'Sanggahan berhasil diajukan dan menunggu verifikasi.');
        }
        redirect(url('sanggahan'));
    }

    if ($aksi === 'tanggapi') {
        if (!in_array($u['role'], ['admin', 'pengampu'], true)) {
            flash('error', 'Hanya Admin atau Pengampu yang dapat menanggapi sanggahan.');
            redirect(url('sanggahan'));
        }

        $id = (int)($_POST['id'] ?? 0);
        $status = $_POST['status'] ?? 'diajukan';
        $tanggapan = trim((string)($_POST['tanggapan'] ?? ''));

        if (!in_array($status, ['diajukan', 'diterima', 'ditolak'], true)) {
            $status = 'diajukan';
        }

        q(
            'UPDATE sanggahan SET status = ?, tanggapan = ?, ditanggapi_oleh = ?, ditanggapi_pada = ? WHERE id = ?',
            [$status, $tanggapan ?: null, $u['id'], date('Y-m-d H:i:s'), $id]
        );
        log_act('tanggapi_sanggahan', "Sanggahan ID $id diperbarui status: $status");
        flash('success', "Status sanggahan berhasil diperbarui menjadi: " . STATUS_SANGGAHAN[$status]);
        redirect(url('sanggahan'));
    }
}

// Ambil daftar sanggahan sesuai hak akses
$sql = 'SELECT s.*, e.nama AS entitas_nama, e.level AS entitas_level, i.nama AS indikator_nama,
               u1.nama AS pembuat, u2.nama AS penanggap, t.tahun
          FROM sanggahan s
          JOIN entitas e ON e.id = s.entitas_id
          JOIN tahun t ON t.id = s.tahun_id
          LEFT JOIN indikator i ON i.id = s.indikator_id
          LEFT JOIN users u1 ON u1.id = s.dibuat_oleh
          LEFT JOIN users u2 ON u2.id = s.ditanggapi_oleh
         WHERE 1=1';
$params = [];

if ($u['role'] === 'entitas') {
    $sql .= ' AND s.entitas_id = ?';
    $params[] = $u['entitas_id'];
} elseif ($u['role'] === 'pengampu') {
    // Pengampu melihat sanggahan terkait indikator yang diampunya atau umum
    $sql .= ' AND (i.bidang_id = ? OR s.indikator_id IS NULL)';
    $params[] = $u['bidang_id'];
}

$sql .= ' ORDER BY s.id DESC';
$list = q_all($sql, $params);

render_header('Sanggahan & Klarifikasi', 'sanggahan');
?>
<div class="page-head">
    <h2>Daftar Sanggahan &amp; Klarifikasi</h2>
    <div class="actions">
        <?= status_badge($tahun['status']) ?>
    </div>
</div>

<div class="card">
    <div class="card-head">
        <h3>Histori Sanggahan</h3>
    </div>
    <?php if (!$list): ?>
        <p class="muted">Belum ada sanggahan yang diajukan.</p>
    <?php else: ?>
        <div class="table-wrap">
            <table class="table">
                <thead>
                    <tr>
                        <th>Tahun</th>
                        <th>Satker / Unit</th>
                        <th>Indikator</th>
                        <th>Uraian Sanggahan</th>
                        <th>Diajukan Oleh</th>
                        <th>Status</th>
                        <th>Tanggapan</th>
                        <?php if (in_array($u['role'], ['admin', 'pengampu'], true)): ?>
                            <th>Aksi</th>
                        <?php endif; ?>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($list as $r): ?>
                    <tr>
                        <td><strong><?= (int)$r['tahun'] ?></strong></td>
                        <td>
                            <strong><?= h($r['entitas_nama']) ?></strong>
                            <div class="small muted"><?= h(LEVELS[$r['entitas_level']] ?? '') ?></div>
                        </td>
                        <td><?= h($r['indikator_nama'] ?? 'Umum / Menyeluruh') ?></td>
                        <td><?= nl2br(h($r['isi'])) ?></td>
                        <td class="small">
                            <?= h($r['pembuat'] ?? '–') ?><br>
                            <span class="muted"><?= h($r['dibuat_pada'] ?? '') ?></span>
                        </td>
                        <td>
                            <span class="badge badge-<?= ['diajukan' => 'warn', 'diterima' => 'ok', 'ditolak' => 'error'][$r['status']] ?? 'muted' ?>">
                                <?= h(STATUS_SANGGAHAN[$r['status']] ?? $r['status']) ?>
                            </span>
                        </td>
                        <td class="small">
                            <?php if ($r['tanggapan']): ?>
                                <?= nl2br(h($r['tanggapan'])) ?><br>
                                <span class="muted">— <?= h($r['penanggap'] ?? '') ?> (<?= h($r['ditanggapi_pada'] ?? '') ?>)</span>
                            <?php else: ?>
                                <span class="muted">Belum ada</span>
                            <?php endif; ?>
                        </td>
                        <?php if (in_array($u['role'], ['admin', 'pengampu'], true)): ?>
                            <td>
                                <details class="inline-details">
                                    <summary class="btn btn-sm">Tanggapi</summary>
                                    <form method="post" class="stack sm-pad" style="margin-top:8px; min-width: 240px;">
                                        <?= csrf_field() ?>
                                        <input type="hidden" name="aksi" value="tanggapi">
                                        <input type="hidden" name="id" value="<?= $r['id'] ?>">
                                        <label>Status
                                            <select name="status">
                                                <option value="diajukan" <?= $r['status'] === 'diajukan' ? 'selected' : '' ?>>Diajukan</option>
                                                <option value="diterima" <?= $r['status'] === 'diterima' ? 'selected' : '' ?>>Diterima</option>
                                                <option value="ditolak" <?= $r['status'] === 'ditolak' ? 'selected' : '' ?>>Ditolak</option>
                                            </select>
                                        </label>
                                        <label>Tanggapan
                                            <textarea name="tanggapan" rows="2"><?= h($r['tanggapan'] ?? '') ?></textarea>
                                        </label>
                                        <button class="btn btn-primary btn-sm">Kirim Tanggapan</button>
                                    </form>
                                </details>
                            </td>
                        <?php endif; ?>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>
<?php render_footer();
