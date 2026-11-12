<?php
declare(strict_types=1);

$u = require_role('admin', 'pengampu');
$tahun = pilih_tahun();
if (!$tahun) {
    render_error('Belum ada tahun penilaian', 'Buat tahun penilaian di menu Konfigurasi.');
}
$level = pilih_level();

$sqlInd = 'SELECT i.*, p.nama AS pilar_nama, b.singkatan AS bidang_singkatan
             FROM indikator i JOIN pilar p ON p.id = i.pilar_id LEFT JOIN bidang b ON b.id = i.bidang_id
            WHERE i.tahun_id = ? AND i.level = ?';
$params = [$tahun['id'], $level];
if ($u['role'] === 'pengampu') {
    $sqlInd .= ' AND i.bidang_id = ?';
    $params[] = $u['bidang_id'];
}
$indList = q_all($sqlInd . ' ORDER BY p.urutan, p.id, i.urutan, i.id', $params);
$peserta = entitas_peserta((int)$tahun['id'], $level);

$ind = null;
$indId = (int)($_GET['ind'] ?? 0);
if ($indId) {
    foreach ($indList as $i) {
        if ((int)$i['id'] === $indId) {
            $ind = $i;
        }
    }
    if (!$ind) {
        // indikator dari tahun/level lain atau bukan milik pengampu → kembali ke daftar
        redirect(url('input'));
    }
}

/** Ambil nilai & isian tersimpan untuk satu indikator. */
$muatData = function (int $indId): array {
    $nilai = [];
    foreach (q_all('SELECT * FROM nilai WHERE indikator_id = ?', [$indId]) as $r) {
        $nilai[(int)$r['entitas_id']][(int)$r['sub_periode']] = (float)$r['nilai'];
    }
    $isian = [];
    foreach (q_all('SELECT * FROM isian WHERE indikator_id = ?', [$indId]) as $r) {
        $isian[(int)$r['entitas_id']] = $r;
    }
    return [$nilai, $isian];
};
$fmtInput = fn(?float $v): string => $v === null ? '' : rtrim(rtrim(number_format($v, 4, ',', ''), '0'), ',');

// ---------------------------------------------------------------------------
// Unduh template CSV
// ---------------------------------------------------------------------------
if ($ind && ($_GET['aksi'] ?? '') === 'template') {
    [$nilai, $isian] = $muatData((int)$ind['id']);
    $labels = sub_periode_labels($ind['frekuensi']);
    $fname = 'template_' . preg_replace('/[^A-Za-z0-9]+/', '_', ($ind['kode'] ?: 'indikator') . '_' . $tahun['tahun'] . '_' . $level) . '.csv';
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $fname . '"');
    echo "\xEF\xBB\xBF";
    $out = fopen('php://output', 'w');
    fputcsv($out, array_merge(['entitas_id', 'nama'], array_values($labels), ['tidak_berlaku', 'catatan', 'bukti_url']), ';');
    foreach ($peserta as $e) {
        $eid = (int)$e['id'];
        $row = [$eid, $e['nama']];
        foreach (array_keys($labels) as $k) {
            $row[] = $fmtInput($nilai[$eid][$k] ?? null);
        }
        $row[] = !empty($isian[$eid]['na']) ? 'ya' : '';
        $row[] = $isian[$eid]['catatan'] ?? '';
        $row[] = $isian[$eid]['bukti_url'] ?? '';
        fputcsv($out, $row, ';');
    }
    exit;
}

// ---------------------------------------------------------------------------
// Simpan / impor
// ---------------------------------------------------------------------------
$old = null;
if (is_post() && $ind) {
    csrf_check();
    $back = url('input', ['ind' => $ind['id']]);
    if (!can_edit_nilai($u, $tahun, $ind)) {
        flash('error', 'Nilai indikator ini tidak dapat diubah pada tahap ' . STATUS_TAHUN[$tahun['status']] . '.');
        redirect($back);
    }
    $labels = sub_periode_labels($ind['frekuensi']);
    $byId = [];
    foreach ($peserta as $e) {
        $byId[(int)$e['id']] = $e;
    }
    $rows = [];
    $errors = [];
    $aksi = $_POST['aksi'] ?? '';

    if ($aksi === 'simpan') {
        foreach ($byId as $eid => $e) {
            $rows[$eid] = [
                'v'       => $_POST['v'][$eid] ?? [],
                'na'      => !empty($_POST['na'][$eid]),
                'catatan' => trim((string)($_POST['catatan'][$eid] ?? '')),
                'bukti'   => trim((string)($_POST['bukti'][$eid] ?? '')),
            ];
        }
    } elseif ($aksi === 'import') {
        $file = $_FILES['berkas'] ?? null;
        if (!$file || $file['error'] !== UPLOAD_ERR_OK || !is_uploaded_file($file['tmp_name'])) {
            flash('error', 'Berkas CSV belum dipilih atau gagal diunggah.');
            redirect($back);
        }
        $isi = (string)file_get_contents($file['tmp_name']);
        $isi = preg_replace('/^\xEF\xBB\xBF/', '', $isi);
        $barisPertama = strtok($isi, "\n") ?: '';
        $delim = substr_count($barisPertama, ';') >= substr_count($barisPertama, ',') ? ';' : ',';
        $fh = fopen('php://temp', 'r+');
        fwrite($fh, $isi);
        rewind($fh);
        $n = count($labels);
        $lineNo = 0;
        while (($cols = fgetcsv($fh, 0, $delim)) !== false) {
            $lineNo++;
            if ($lineNo === 1 || $cols === [null] || trim(implode('', $cols)) === '') {
                continue; // header / baris kosong
            }
            $eid = (int)($cols[0] ?? 0);
            if (!isset($byId[$eid])) {
                $nama = trim((string)($cols[1] ?? ''));
                foreach ($byId as $id => $e) {
                    if ($nama !== '' && mb_strtolower($e['nama']) === mb_strtolower($nama)) {
                        $eid = $id;
                    }
                }
            }
            if (!isset($byId[$eid])) {
                $errors[] = "Baris $lineNo: Satker/Unit '" . ($cols[1] ?? $cols[0] ?? '') . "' tidak dikenali.";
                continue;
            }
            $v = [];
            $i = 2;
            foreach (array_keys($labels) as $k) {
                $v[$k] = $cols[$i++] ?? '';
            }
            $naRaw = mb_strtolower(trim((string)($cols[2 + $n] ?? '')));
            $rows[$eid] = [
                'v'       => $v,
                'na'      => in_array($naRaw, ['1', 'ya', 'y', 'x', 'v', 'true', 'na', 'n/a'], true),
                'catatan' => trim((string)($cols[3 + $n] ?? '')),
                'bukti'   => trim((string)($cols[4 + $n] ?? '')),
            ];
        }
        fclose($fh);
        if (!$rows && !$errors) {
            $errors[] = 'Tidak ada baris data yang ditemukan di berkas.';
        }
    } else {
        redirect($back);
    }

    // Validasi
    $bersih = [];
    foreach ($rows as $eid => $r) {
        $vals = [];
        foreach ($labels as $k => $lbl) {
            try {
                $val = parse_nilai($r['v'][$k] ?? '');
                if ($val !== null) {
                    $vals[$k] = $val;
                }
            } catch (InvalidArgumentException $ex) {
                $errors[] = $byId[$eid]['nama'] . ' (' . $lbl . '): ' . $ex->getMessage() . '.';
            }
        }
        if ($r['bukti'] !== '' && !preg_match('~^https?://~i', $r['bukti'])) {
            $errors[] = $byId[$eid]['nama'] . ': tautan bukti dukung harus diawali http:// atau https://.';
        }
        $bersih[$eid] = ['vals' => $vals] + $r;
    }

    if ($errors) {
        flash('error', "Data belum disimpan. Perbaiki kesalahan berikut:\n• " . implode("\n• ", $errors));
        if ($aksi === 'import') {
            redirect($back);
        }
        $old = $_POST;
    } else {
        transaksi(function () use ($bersih, $ind, $u) {
            $now = date('Y-m-d H:i:s');
            foreach ($bersih as $eid => $r) {
                q('DELETE FROM nilai WHERE indikator_id = ? AND entitas_id = ?', [$ind['id'], $eid]);
                foreach ($r['vals'] as $k => $v) {
                    q('INSERT INTO nilai (indikator_id, entitas_id, sub_periode, nilai) VALUES (?, ?, ?, ?)', [$ind['id'], $eid, $k, $v]);
                }
                if ($r['na'] || $r['catatan'] !== '' || $r['bukti'] !== '') {
                    q('INSERT INTO isian (indikator_id, entitas_id, na, catatan, bukti_url, updated_by, updated_at)
                       VALUES (?, ?, ?, ?, ?, ?, ?)
                       ON CONFLICT (indikator_id, entitas_id) DO UPDATE SET
                         na = excluded.na, catatan = excluded.catatan, bukti_url = excluded.bukti_url,
                         updated_by = excluded.updated_by, updated_at = excluded.updated_at',
                        [$ind['id'], $eid, $r['na'] ? 1 : 0, $r['catatan'] ?: null, $r['bukti'] ?: null, $u['id'], $now]);
                } else {
                    q('DELETE FROM isian WHERE indikator_id = ? AND entitas_id = ?', [$ind['id'], $eid]);
                }
            }
        });
        log_act($aksi === 'import' ? 'impor_nilai' : 'simpan_nilai',
            sprintf('%s (%s %d) — %d entitas', $ind['nama'], LEVELS[$ind['level']], $tahun['tahun'], count($bersih)));
        flash('success', sprintf('Nilai "%s" berhasil %s untuk %d %s.', $ind['nama'], $aksi === 'import' ? 'diimpor' : 'disimpan', count($bersih), LEVELS[$level]));
        redirect($back);
    }
}

// ---------------------------------------------------------------------------
// Tampilan
// ---------------------------------------------------------------------------
render_header($ind ? 'Input Nilai — ' . $ind['nama'] : 'Input Nilai', 'input');
?>
<div class="page-head">
    <?= filter_bar('input', $tahun, $level) ?>
    <div class="actions"><?= status_badge($tahun['status']) ?></div>
</div>

<?php if (!$ind): ?>
    <?php if ($tahun['status'] === 'final'): ?>
        <div class="alert alert-info">Tahun <?= (int)$tahun['tahun'] ?> sudah final — nilai hanya dapat dilihat.</div>
    <?php elseif ($tahun['status'] === 'verifikasi' && $u['role'] === 'pengampu'): ?>
        <div class="alert alert-info">Tahap verifikasi: perubahan nilai hanya dapat dilakukan oleh Biro Perencanaan dan Keuangan.</div>
    <?php endif; ?>
    <div class="card">
        <h3>Monitoring Pengisian — <?= h(LEVELS[$level]) ?> <?= (int)$tahun['tahun'] ?></h3>
        <?php if (!$indList): ?>
            <p class="muted">Tidak ada indikator<?= $u['role'] === 'pengampu' ? ' yang menjadi tanggung jawab ' . h($u['bidang_singkatan']) : '' ?> pada tahun & level ini.</p>
        <?php else: ?>
            <div class="table-wrap">
                <table class="table">
                    <thead><tr><th>Indikator</th><th>Pilar</th><th>Pengampu</th><th>Frekuensi</th><th class="num">Bobot</th><th>Progres Pengisian</th><th></th></tr></thead>
                    <tbody>
                    <?php foreach ($indList as $i):
                        [$nilai, $isian] = $muatData((int)$i['id']);
                        $na = 0;
                        $terisi = 0;
                        foreach ($peserta as $e) {
                            $eid = (int)$e['id'];
                            if (!empty($isian[$eid]['na'])) {
                                $na++;
                            } elseif (!empty($nilai[$eid])) {
                                $terisi++;
                            }
                        }
                        $target = max(count($peserta) - $na, 0);
                        $pct = $target ? $terisi / $target * 100 : 100;
                        $editable = can_edit_nilai($u, $tahun, $i);
                        ?>
                        <tr>
                            <td><strong><?= h($i['nama']) ?></strong><?= $i['kode'] ? ' <span class="muted small">' . h($i['kode']) . '</span>' : '' ?></td>
                            <td class="small"><?= h($i['pilar_nama']) ?></td>
                            <td class="small"><?= h($i['bidang_singkatan'] ?? '-') ?></td>
                            <td class="small"><?= h(FREKUENSI_LABEL[$i['frekuensi']]) ?></td>
                            <td class="num"><?= fmt((float)$i['bobot']) ?>%</td>
                            <td style="min-width:160px">
                                <div class="progress"><div style="width:<?= $pct ?>%" class="<?= $pct >= 100 ? 'done' : '' ?>"></div></div>
                                <small><?= $terisi ?>/<?= $target ?> terisi<?= $na ? " · $na tidak berlaku" : '' ?></small>
                            </td>
                            <td><a class="btn btn-sm <?= $editable ? 'btn-primary' : '' ?>" href="<?= h(url('input', ['ind' => $i['id']])) ?>"><?= $editable ? 'Isi Nilai' : 'Lihat' ?></a></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
<?php else:
    [$nilai, $isian] = $muatData((int)$ind['id']);
    $labels = sub_periode_labels($ind['frekuensi']);
    $editable = can_edit_nilai($u, $tahun, $ind);
    $dis = $editable ? '' : ' disabled';
    ?>
    <p><a href="<?= h(url('input')) ?>">← Kembali ke daftar indikator</a></p>
    <div class="card">
        <div class="ind-head">
            <div>
                <h3><?= h($ind['nama']) ?></h3>
                <div class="muted small"><?= h($ind['pilar_nama']) ?> · Bobot <?= fmt((float)$ind['bobot']) ?>% · Pengampu <?= h($ind['bidang_singkatan'] ?? '-') ?> · <?= h(FREKUENSI_LABEL[$ind['frekuensi']]) ?></div>
                <?php if ($ind['deskripsi']): ?><p class="small"><?= h($ind['deskripsi']) ?></p><?php endif; ?>
            </div>
            <div class="actions">
                <a class="btn btn-sm" href="<?= h(url('input', ['ind' => $ind['id'], 'aksi' => 'template'])) ?>">⬇ Unduh Template CSV</a>
            </div>
        </div>
        <?php if (!$editable): ?>
            <div class="alert alert-info">Mode lihat saja — <?= $tahun['status'] === 'final' ? 'tahun ini sudah final.' : 'pada tahap verifikasi hanya Biro Perencanaan dan Keuangan yang dapat mengubah nilai.' ?></div>
        <?php endif; ?>

        <form method="post" class="grid-form">
            <?= csrf_field() ?>
            <input type="hidden" name="aksi" value="simpan">
            <div class="table-wrap">
                <table class="table grid-input">
                    <thead>
                    <tr>
                        <th>#</th><th><?= h(LEVELS[$level]) ?></th>
                        <?php foreach ($labels as $lbl): ?><th class="num"><?= h($lbl) ?></th><?php endforeach; ?>
                        <?php if (count($labels) > 1): ?><th class="num">Rata-rata</th><?php endif; ?>
                        <th title="Tidak berlaku — tidak dihitung, bobot dibagi ulang">Tidak berlaku</th>
                        <th>Catatan / alasan</th><th>Tautan bukti dukung</th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($peserta as $no => $e):
                        $eid = (int)$e['id'];
                        $naChecked = $old ? !empty($old['na'][$eid]) : !empty($isian[$eid]['na']);
                        ?>
                        <tr data-row>
                            <td><?= $no + 1 ?></td>
                            <td class="nowrap"><?= h($e['nama']) ?><?= !$e['aktif'] ? ' <span class="badge badge-muted">nonaktif</span>' : '' ?></td>
                            <?php foreach ($labels as $k => $lbl):
                                $val = $old ? (string)($old['v'][$eid][$k] ?? '') : $fmtInput($nilai[$eid][$k] ?? null); ?>
                                <td><input class="in-num" name="v[<?= $eid ?>][<?= $k ?>]" value="<?= h($val) ?>" inputmode="decimal" placeholder="0–100" aria-label="<?= h($e['nama'] . ' ' . $lbl) ?>"<?= $dis ?>></td>
                            <?php endforeach; ?>
                            <?php if (count($labels) > 1): ?><td class="num"><strong data-avg>–</strong></td><?php endif; ?>
                            <td class="center"><input type="checkbox" name="na[<?= $eid ?>]" value="1"<?= $naChecked ? ' checked' : '' ?><?= $dis ?>></td>
                            <td><input name="catatan[<?= $eid ?>]" value="<?= h($old ? ($old['catatan'][$eid] ?? '') : ($isian[$eid]['catatan'] ?? '')) ?>"<?= $dis ?>></td>
                            <td><input name="bukti[<?= $eid ?>]" value="<?= h($old ? ($old['bukti'][$eid] ?? '') : ($isian[$eid]['bukti_url'] ?? '')) ?>" placeholder="https://"<?= $dis ?>></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php if ($editable): ?>
                <div class="form-foot">
                    <span class="small muted">Gunakan koma atau titik sebagai desimal. Kosongkan jika belum ada nilai.</span>
                    <button class="btn btn-primary">💾 Simpan Nilai</button>
                </div>
            <?php endif; ?>
        </form>
    </div>

    <?php if ($editable): ?>
        <div class="card">
            <h3>Impor dari CSV / Excel</h3>
            <p class="small muted">Unduh template, isi di Excel, lalu simpan sebagai <em>CSV (pemisah titik koma / koma)</em>. Hanya baris yang ada di berkas yang akan diperbarui.</p>
            <form method="post" enctype="multipart/form-data" class="inline-form">
                <?= csrf_field() ?>
                <input type="hidden" name="aksi" value="import">
                <input type="file" name="berkas" accept=".csv,text/csv" required>
                <button class="btn">⬆ Impor</button>
            </form>
        </div>
    <?php endif; ?>
<?php endif; ?>
<?php render_footer();
