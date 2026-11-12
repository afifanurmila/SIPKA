<?php
declare(strict_types=1);

$u = require_login();
if ($u['role'] === 'entitas') {
    redirect(url('rapor'));
}
$tahun = pilih_tahun();
if (!$tahun) {
    render_error('Belum ada tahun penilaian', 'Silakan buat tahun penilaian di menu Konfigurasi.');
}
$level = pilih_level();
$s = hitung_skor((int)$tahun['id'], $level);
$hasil = $s['hasil'];
$dinilai = array_values(array_filter($hasil, fn($r) => $r['total'] !== null));
$totals = array_map(fn($r) => $r['total'], $dinilai);

$distribusi = [];
foreach ($s['predikat'] as $p) {
    $distribusi[$p['id']] = ['p' => $p, 'n' => 0];
}
foreach ($dinilai as $r) {
    if ($r['predikat']) {
        $distribusi[$r['predikat']['id']]['n']++;
    }
}
$avgKelengkapan = $hasil ? array_sum(array_map(fn($r) => $r['kelengkapan'], $hasil)) / count($hasil) : 0;

render_header('Dashboard & Peringkat', 'dashboard');
?>
<div class="page-head">
    <?= filter_bar('dashboard', $tahun, $level) ?>
    <div class="actions">
        <?= status_badge($tahun['status']) ?>
        <a class="btn btn-sm" href="<?= h(url('ekspor', ['t' => $tahun['id'], 'lvl' => $level])) ?>">⬇ Ekspor CSV</a>
    </div>
</div>

<?php if ($tahun['status'] !== 'final'): ?>
    <div class="alert alert-warn">Hasil tahun <?= (int)$tahun['tahun'] ?> masih <strong>sementara</strong> (tahap <?= h(STATUS_TAHUN[$tahun['status']]) ?>). Peringkat dapat berubah sampai Biro Perencanaan dan Keuangan mengunci hasil.</div>
<?php endif; ?>

<?php if (!$s['indikator']): ?>
    <div class="card empty">Belum ada indikator untuk <?= h(LEVELS[$level]) ?> tahun <?= (int)$tahun['tahun'] ?>.
        <?php if ($u['role'] === 'admin'): ?><a href="<?= h(url('konfig', ['tab' => 'indikator'])) ?>">Atur indikator →</a><?php endif; ?></div>
<?php else: ?>

    <?php if ($u['role'] === 'admin' && abs($s['total_bobot'] - 100) > 0.001): ?>
        <div class="alert alert-error">Total bobot indikator <?= h(LEVELS[$level]) ?> = <?= fmt($s['total_bobot']) ?>%, seharusnya 100%. <a href="<?= h(url('konfig', ['tab' => 'indikator'])) ?>">Perbaiki di Konfigurasi</a>.</div>
    <?php endif; ?>

    <div class="stats">
        <div class="stat"><span>Jumlah dinilai</span><strong><?= count($dinilai) ?><small> / <?= count($hasil) ?></small></strong></div>
        <div class="stat"><span>Rata-rata nilai</span><strong><?= $totals ? fmt(array_sum($totals) / count($totals)) : '–' ?></strong></div>
        <div class="stat"><span>Tertinggi</span><strong><?= $totals ? fmt(max($totals)) : '–' ?></strong><small><?= h($dinilai[0]['entitas']['nama'] ?? '') ?></small></div>
        <div class="stat"><span>Terendah</span><strong><?= $totals ? fmt(min($totals)) : '–' ?></strong><small><?= h($dinilai ? end($dinilai)['entitas']['nama'] : '') ?></small></div>
        <div class="stat"><span>Kelengkapan data</span><strong><?= fmt($avgKelengkapan, 0) ?>%</strong>
            <div class="progress"><div style="width:<?= min(100, $avgKelengkapan) ?>%"></div></div></div>
    </div>

    <div class="grid-2">
        <div class="card">
            <h3>Nilai Total per <?= h(LEVELS[$level]) ?></h3>
            <div class="chart-box tall">
                <canvas data-chart="<?= chart_attr([
                    'type' => 'bar',
                    'data' => [
                        'labels' => array_map(fn($r) => $r['entitas']['nama'], $dinilai),
                        'datasets' => [[
                            'label' => 'Nilai Total',
                            'data' => array_map(fn($r) => round($r['total'], 2), $dinilai),
                            'backgroundColor' => array_map(fn($r) => ($r['predikat']['warna'] ?? '#64748b') . 'cc', $dinilai),
                            'borderRadius' => 4,
                        ]],
                    ],
                    'options' => [
                        'indexAxis' => 'y', 'maintainAspectRatio' => false,
                        'plugins' => ['legend' => ['display' => false]],
                        'scales' => ['x' => ['min' => 0, 'max' => 100]],
                    ],
                ]) ?>"></canvas>
            </div>
        </div>
        <div class="card">
            <h3>Sebaran Predikat</h3>
            <div class="chart-box">
                <canvas data-chart="<?= chart_attr([
                    'type' => 'doughnut',
                    'data' => [
                        'labels' => array_map(fn($d) => $d['p']['nama'], array_values($distribusi)),
                        'datasets' => [[
                            'data' => array_map(fn($d) => $d['n'], array_values($distribusi)),
                            'backgroundColor' => array_map(fn($d) => $d['p']['warna'], array_values($distribusi)),
                        ]],
                    ],
                    'options' => ['maintainAspectRatio' => false, 'plugins' => ['legend' => ['position' => 'bottom']]],
                ]) ?>"></canvas>
            </div>
            <table class="table compact">
                <thead><tr><th>Predikat</th><th>Ambang</th><th>Jumlah</th><th>Konsekuensi</th></tr></thead>
                <tbody>
                <?php foreach ($distribusi as $d): ?>
                    <tr>
                        <td><?= predikat_badge($d['p']) ?></td>
                        <td>≥ <?= fmt((float)$d['p']['min_nilai'], 0) ?></td>
                        <td><strong><?= $d['n'] ?></strong></td>
                        <td class="small"><?= h($d['p']['konsekuensi']) ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <div class="card">
        <h3>Peringkat <?= h(LEVELS[$level]) ?> — Tahun <?= (int)$tahun['tahun'] ?></h3>
        <div class="table-wrap">
            <table class="table">
                <thead>
                <tr>
                    <th>#</th>
                    <th><?= h(LEVELS[$level]) ?></th>
                    <?php foreach ($s['pilar'] as $p): ?>
                        <th class="num" title="Bobot pilar <?= fmt($p['bobot']) ?>%"><?= h($p['nama']) ?><br><small class="muted"><?= fmt($p['bobot'], 0) ?>%</small></th>
                    <?php endforeach; ?>
                    <th class="num">Nilai Total</th>
                    <th>Predikat</th>
                    <th>Rekomendasi</th>
                    <th class="num">Kelengkapan</th>
                    <th></th>
                </tr>
                </thead>
                <tbody>
                <?php foreach ($hasil as $r): ?>
                    <tr>
                        <td><?= $r['peringkat'] ? '<span class="rank r' . min($r['peringkat'], 4) . '">' . $r['peringkat'] . '</span>' : '–' ?></td>
                        <td>
                            <strong><?= h($r['entitas']['nama']) ?></strong>
                            <?php if (!$r['entitas']['aktif']): ?><span class="badge badge-muted">nonaktif</span><?php endif; ?>
                            <div class="small muted"><?= h(KATEGORI_ANGGARAN[$r['entitas']['kategori_anggaran']] ?? '') ?></div>
                        </td>
                        <?php foreach ($s['pilar'] as $pid => $p): ?>
                            <td class="num"><?= fmt($r['pilar'][$pid]['nilai'] ?? null) ?></td>
                        <?php endforeach; ?>
                        <td class="num"><strong class="score"><?= fmt($r['total']) ?></strong></td>
                        <td><?= predikat_badge($r['predikat']) ?></td>
                        <td class="small"><?= h($r['predikat'] ? JENIS_PREDIKAT[$r['predikat']['jenis']] ?? '' : '') ?></td>
                        <td class="num">
                            <div class="progress sm" title="<?= fmt($r['kelengkapan'], 0) ?>%"><div style="width:<?= $r['kelengkapan'] ?>%"></div></div>
                            <small><?= fmt($r['kelengkapan'], 0) ?>%</small>
                        </td>
                        <td><a class="btn btn-sm" href="<?= h(url('rapor', ['e' => $r['entitas']['id'], 't' => $tahun['id']])) ?>">Rapor</a></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <p class="small muted">Nilai Total = Σ(bobot × nilai) ÷ Σ bobot indikator yang dihitung. Indikator bertanda <em>tidak berlaku</em> atau belum terisi tidak ikut dihitung dan bobotnya dibagi ulang secara proporsional.</p>
    </div>
<?php endif; ?>
<?php render_footer();
