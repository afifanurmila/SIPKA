<?php
declare(strict_types=1);

$u = require_login();
$tahun = pilih_tahun();
if (!$tahun) {
    render_error('Belum ada tahun penilaian', 'Silakan hubungi Biro Perencanaan dan Keuangan.');
}

$eid = $u['role'] === 'entitas' ? (int)$u['entitas_id'] : (int)($_GET['e'] ?? 0);
$e = $eid ? q_one('SELECT e.*, p.nama AS induk_nama FROM entitas e LEFT JOIN entitas p ON p.id = e.induk_id WHERE e.id = ?', [$eid]) : null;
if (!$e) {
    render_error('Rapor tidak ditemukan', $u['role'] === 'entitas'
        ? 'Akun Anda belum terhubung ke Satker/Unit Kerja. Hubungi admin.'
        : 'Pilih Satker/Unit Kerja dari halaman Dashboard.');
}
$level = $e['level'];

render_header('Rapor Kinerja — ' . $e['nama'], $u['role'] === 'entitas' ? 'rapor' : 'dashboard');
?>
<div class="page-head no-print">
    <?= filter_bar('rapor', $tahun, null, $u['role'] === 'entitas' ? [] : ['e' => $eid]) ?>
    <div class="actions">
        <?= status_badge($tahun['status']) ?>
        <?php if ($u['role'] !== 'entitas'): ?><a class="btn btn-sm" href="<?= h(url('dashboard', ['lvl' => $level])) ?>">← Peringkat</a><?php endif; ?>
        <button class="btn btn-sm" onclick="window.print()">🖨 Cetak / PDF</button>
    </div>
</div>
<?php
if ($u['role'] === 'entitas' && $tahun['status'] === 'pengisian') {
    echo '<div class="card empty">Rapor tahun ' . (int)$tahun['tahun'] . ' belum diterbitkan karena masih dalam tahap <strong>pengisian nilai</strong> oleh unit pengampu. Silakan pilih tahun lain.</div>';
    render_footer();
    exit;
}

$s = hitung_skor((int)$tahun['id'], $level);
$row = null;
foreach ($s['hasil'] as $r) {
    if ((int)$r['entitas']['id'] === $eid) {
        $row = $r;
        break;
    }
}
if (!$row || !$s['indikator']) {
    echo '<div class="card empty">' . h($e['nama']) . ' tidak termasuk dalam penilaian ' . h(LEVELS[$level]) . ' tahun ' . (int)$tahun['tahun'] . '.</div>';
    render_footer();
    exit;
}
$jumlahDinilai = count(array_filter($s['hasil'], fn($r) => $r['total'] !== null));

// Tren lintas tahun
$tren = [];
foreach (array_reverse(semua_tahun()) as $t) {
    if ($u['role'] === 'entitas' && $t['status'] === 'pengisian') {
        continue;
    }
    foreach (hitung_skor((int)$t['id'], $level)['hasil'] as $r) {
        if ((int)$r['entitas']['id'] === $eid && $r['total'] !== null) {
            $tren[(int)$t['tahun']] = round($r['total'], 2);
        }
    }
}

// Prioritas perbaikan: potensi kenaikan nilai total jika indikator mencapai 100
$prioritas = [];
foreach ($s['indikator'] as $ind) {
    $d = $row['detail'][(int)$ind['id']];
    if ($d['nilai'] !== null && $row['bobot_dihitung'] > 0 && $d['nilai'] < 100) {
        $prioritas[] = ['ind' => $ind, 'd' => $d, 'potensi' => $d['bobot'] * (100 - $d['nilai']) / $row['bobot_dihitung']];
    }
}
usort($prioritas, fn($a, $b) => $b['potensi'] <=> $a['potensi']);
$prioritas = array_slice($prioritas, 0, 3);

$predikatList = $s['predikat'];
$batasPembinaan = count($predikatList) >= 2 ? (float)$predikatList[count($predikatList) - 2]['min_nilai'] : 0;

$sanggahan = q_all(
    'SELECT s.*, i.nama AS indikator_nama, u1.nama AS pembuat, u2.nama AS penanggap
       FROM sanggahan s
       LEFT JOIN indikator i ON i.id = s.indikator_id
       LEFT JOIN users u1 ON u1.id = s.dibuat_oleh
       LEFT JOIN users u2 ON u2.id = s.ditanggapi_oleh
      WHERE s.tahun_id = ? AND s.entitas_id = ? ORDER BY s.id DESC',
    [$tahun['id'], $eid]
);
$bolehSanggah = $tahun['status'] === 'verifikasi' && ($u['role'] === 'admin' || ($u['role'] === 'entitas' && (int)$u['entitas_id'] === $eid));
?>

<div class="rapor-head card">
    <div>
        <div class="muted small"><?= h(LEVELS[$level]) ?><?= $e['induk_nama'] ? ' · ' . h($e['induk_nama']) : '' ?> · <?= h(KATEGORI_ANGGARAN[$e['kategori_anggaran']] ?? '') ?></div>
        <h2><?= h($e['nama']) ?></h2>
        <div class="muted">Penilaian Kinerja Tahun <?= (int)$tahun['tahun'] ?><?= $tahun['status'] !== 'final' ? ' — <em>hasil sementara</em>' : '' ?></div>
        <?php if ($row['predikat']): ?>
            <p class="konsekuensi"><strong><?= h(JENIS_PREDIKAT[$row['predikat']['jenis']] ?? '') ?>:</strong> <?= h($row['predikat']['konsekuensi']) ?></p>
        <?php endif; ?>
    </div>
    <div class="rapor-score">
        <div class="big-score" style="color:<?= h($row['predikat']['warna'] ?? '#334155') ?>"><?= fmt($row['total']) ?></div>
        <?= predikat_badge($row['predikat']) ?>
        <div class="muted small">Peringkat <strong><?= $row['peringkat'] ?? '–' ?></strong> dari <?= $jumlahDinilai ?></div>
        <div class="muted small">Kelengkapan data <?= fmt($row['kelengkapan'], 0) ?>%</div>
    </div>
</div>

<div class="grid-2">
    <div class="card">
        <h3>Profil Capaian per Indikator</h3>
        <div class="chart-box">
            <canvas data-chart="<?= chart_attr([
                'type' => 'radar',
                'data' => [
                    'labels' => array_map(fn($i) => $i['kode'] ?: mb_strimwidth($i['nama'], 0, 24, '…'), $s['indikator']),
                    'datasets' => [
                        [
                            'label' => $e['nama'],
                            'data' => array_map(fn($i) => $row['detail'][(int)$i['id']]['nilai'] !== null ? round($row['detail'][(int)$i['id']]['nilai'], 2) : null, $s['indikator']),
                            'backgroundColor' => 'rgba(37,99,235,.18)', 'borderColor' => '#2563eb', 'pointBackgroundColor' => '#2563eb',
                        ],
                        [
                            'label' => 'Rata-rata ' . LEVELS[$level],
                            'data' => array_map(function ($i) use ($s) {
                                $v = array_filter(array_map(fn($r) => $r['detail'][(int)$i['id']]['nilai'], $s['hasil']), fn($x) => $x !== null);
                                return $v ? round(array_sum($v) / count($v), 2) : null;
                            }, $s['indikator']),
                            'backgroundColor' => 'rgba(100,116,139,.08)', 'borderColor' => '#94a3b8', 'borderDash' => [4, 4], 'pointRadius' => 0,
                        ],
                    ],
                ],
                'options' => ['maintainAspectRatio' => false, 'scales' => ['r' => ['min' => 0, 'max' => 100, 'ticks' => ['stepSize' => 20]]]],
            ]) ?>"></canvas>
        </div>
    </div>
    <div class="card">
        <h3>Tren Nilai Total</h3>
        <?php if (count($tren) > 1): ?>
            <div class="chart-box sm">
                <canvas data-chart="<?= chart_attr([
                    'type' => 'line',
                    'data' => ['labels' => array_map('strval', array_keys($tren)), 'datasets' => [[
                        'label' => 'Nilai Total', 'data' => array_values($tren), 'borderColor' => '#0f766e', 'backgroundColor' => '#0f766e', 'tension' => .3,
                    ]]],
                    'options' => ['maintainAspectRatio' => false, 'plugins' => ['legend' => ['display' => false]], 'scales' => ['y' => ['min' => 0, 'max' => 100]]],
                ]) ?>"></canvas>
            </div>
        <?php else: ?>
            <p class="muted">Data tren tersedia setelah ada penilaian lebih dari satu tahun<?= $tren ? ' (saat ini hanya ' . implode(', ', array_keys($tren)) . ')' : '' ?>.</p>
        <?php endif; ?>
        <h3>Prioritas Perbaikan</h3>
        <?php if ($prioritas): ?>
            <ol class="prioritas">
                <?php foreach ($prioritas as $pr): ?>
                    <li><strong><?= h($pr['ind']['nama']) ?></strong> — nilai <?= fmt($pr['d']['nilai']) ?>.
                        <span class="muted">Potensi menambah nilai total hingga <strong>+<?= fmt($pr['potensi']) ?></strong> poin.</span></li>
                <?php endforeach; ?>
            </ol>
        <?php else: ?>
            <p class="muted">Tidak ada.</p>
        <?php endif; ?>
    </div>
</div>

<div class="card">
    <h3>Rincian Nilai</h3>
    <div class="table-wrap">
        <table class="table">
            <thead>
            <tr><th>Indikator</th><th>Pengampu</th><th class="num">Bobot</th><th class="num">Nilai</th><th class="num">Kontribusi</th><th>Keterangan</th></tr>
            </thead>
            <tbody>
            <?php
            $pilarAktif = null;
            foreach ($s['indikator'] as $ind):
                $d = $row['detail'][(int)$ind['id']];
                $pid = (int)$ind['pilar_id'];
                if ($pilarAktif !== $pid):
                    $pilarAktif = $pid; ?>
                    <tr class="group-row"><td colspan="3"><?= h($ind['pilar_nama']) ?> <span class="muted">(<?= fmt($s['pilar'][$pid]['bobot'], 0) ?>%)</span></td>
                        <td class="num"><?= fmt($row['pilar'][$pid]['nilai']) ?></td><td colspan="2"></td></tr>
                <?php endif; ?>
                <tr class="<?= $d['nilai'] !== null && $d['nilai'] < $batasPembinaan ? 'row-low' : '' ?>">
                    <td>
                        <?= h($ind['nama']) ?>
                        <?php if ($ind['deskripsi']): ?><div class="small muted"><?= h($ind['deskripsi']) ?></div><?php endif; ?>
                        <?php if ($d['jumlah_sub'] > 1 && $d['per_sub']): ?>
                            <div class="subvals">
                                <?php foreach (sub_periode_labels($ind['frekuensi']) as $k => $lbl): ?>
                                    <span title="<?= h($lbl) ?>"><?= h($lbl) ?>: <b><?= isset($d['per_sub'][$k]) ? fmt($d['per_sub'][$k]) : '–' ?></b></span>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </td>
                    <td class="small"><?= h($ind['bidang_singkatan'] ?? '-') ?><br><span class="muted"><?= h(FREKUENSI_LABEL[$ind['frekuensi']]) ?></span></td>
                    <td class="num"><?= fmt((float)$ind['bobot']) ?>%</td>
                    <td class="num"><strong><?= $d['status'] === 'na' ? '<span class="muted">N/A</span>' : fmt($d['nilai']) ?></strong></td>
                    <td class="num"><?= fmt($d['kontribusi']) ?></td>
                    <td class="small">
                        <?php
                        echo match ($d['status']) {
                            'na' => '<span class="badge badge-muted">Tidak berlaku</span>',
                            'kosong' => '<span class="badge badge-warn">Belum diisi</span>',
                            'sebagian' => '<span class="badge badge-info">' . $d['terisi'] . '/' . $d['jumlah_sub'] . ' periode</span>',
                            default => '',
                        };
                        if ($d['catatan']) {
                            echo ' ' . h($d['catatan']);
                        }
                        if ($d['bukti_url']) {
                            echo ' <a href="' . h($d['bukti_url']) . '" target="_blank" rel="noopener">Bukti dukung ↗</a>';
                        }
                        ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            <tr class="total-row"><td colspan="2">Nilai Total</td><td class="num"><?= fmt($row['bobot_dihitung']) ?>%</td><td class="num"><?= fmt($row['total']) ?></td><td class="num"><?= fmt($row['total']) ?></td><td></td></tr>
            </tbody>
        </table>
    </div>
    <?php if ($row['bobot_dihitung'] > 0 && abs($row['bobot_dihitung'] - $s['total_bobot']) > 0.001): ?>
        <p class="small muted">Sebagian indikator tidak berlaku / belum terisi, sehingga nilai total dihitung dari bobot <?= fmt($row['bobot_dihitung']) ?>% lalu dinormalisasi ke skala 100.</p>
    <?php endif; ?>
</div>

<div class="card no-print">
    <h3>Sanggahan / Klarifikasi</h3>
    <?php if ($bolehSanggah): ?>
        <form method="post" action="<?= h(url('sanggahan')) ?>" class="stack">
            <?= csrf_field() ?>
            <input type="hidden" name="aksi" value="buat">
            <input type="hidden" name="entitas_id" value="<?= $eid ?>">
            <input type="hidden" name="tahun_id" value="<?= (int)$tahun['id'] ?>">
            <label>Indikator yang disanggah
                <select name="indikator_id">
                    <option value="">— Umum / tidak spesifik —</option>
                    <?php foreach ($s['indikator'] as $ind): ?><option value="<?= (int)$ind['id'] ?>"><?= h($ind['nama']) ?></option><?php endforeach; ?>
                </select>
            </label>
            <label>Uraian sanggahan (sertakan dasar/bukti, mis. tautan dokumen)
                <textarea name="isi" rows="3" required></textarea>
            </label>
            <div><button class="btn btn-primary">Kirim Sanggahan</button></div>
        </form>
    <?php elseif ($tahun['status'] === 'pengisian'): ?>
        <p class="muted">Sanggahan dapat diajukan saat tahap Verifikasi &amp; Sanggahan dibuka.</p>
    <?php elseif ($tahun['status'] === 'final'): ?>
        <p class="muted">Hasil tahun ini sudah final; sanggahan ditutup.</p>
    <?php endif; ?>

    <?php if ($sanggahan): ?>
        <table class="table compact">
            <thead><tr><th>Tanggal</th><th>Indikator</th><th>Uraian</th><th>Status</th><th>Tanggapan</th></tr></thead>
            <tbody>
            <?php foreach ($sanggahan as $sg): ?>
                <tr>
                    <td class="small"><?= h($sg['dibuat_pada']) ?><br><span class="muted"><?= h($sg['pembuat']) ?></span></td>
                    <td><?= h($sg['indikator_nama'] ?? 'Umum') ?></td>
                    <td><?= nl2br(h($sg['isi'])) ?></td>
                    <td><span class="badge badge-<?= ['diajukan' => 'warn', 'diterima' => 'ok', 'ditolak' => 'error'][$sg['status']] ?>"><?= h(STATUS_SANGGAHAN[$sg['status']]) ?></span></td>
                    <td class="small"><?= nl2br(h($sg['tanggapan'] ?? '')) ?><?= $sg['penanggap'] ? '<br><span class="muted">— ' . h($sg['penanggap']) . '</span>' : '' ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    <?php elseif (!$bolehSanggah): ?>
        <p class="muted">Belum ada sanggahan.</p>
    <?php endif; ?>
</div>
<?php render_footer();
