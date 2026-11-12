<?php
declare(strict_types=1);

$u = require_login();
$tahun = pilih_tahun();
if (!$tahun) {
    redirect(url('dashboard'));
}
$level = pilih_level();
$s = hitung_skor((int)$tahun['id'], $level);

$filename = 'rekap_penilaian_' . $level . '_' . $tahun['tahun'] . '.csv';

header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');

// Output UTF-8 BOM for Microsoft Excel
echo "\xEF\xBB\xBF";
$out = fopen('php://output', 'w');

// Header Baris 1: Judul
fputcsv($out, [APP_FULLNAME], ';');
fputcsv($out, ['Level: ' . LEVELS[$level] . ' | Tahun Anggaran: ' . $tahun['tahun'] . ' | Status: ' . STATUS_TAHUN[$tahun['status']]], ';');
fputcsv($out, ['Tanggal Unduh: ' . date('d-m-Y H:i:s')], ';');
fputcsv($out, [], ';');

// Header Kolom Tabel
$header = ['Peringkat', LEVELS[$level], 'Kategori Anggaran'];
foreach ($s['pilar'] as $p) {
    $header[] = 'Pilar: ' . $p['nama'] . ' (' . number_format($p['bobot'], 0) . '%)';
}
foreach ($s['indikator'] as $ind) {
    $header[] = $ind['nama'] . ' (' . number_format($ind['bobot'], 1) . '%)';
}
$header[] = 'Nilai Total';
$header[] = 'Predikat';
$header[] = 'Jenis Rekomendasi';
$header[] = 'Konsekuensi';
$header[] = 'Kelengkapan Data (%)';

fputcsv($out, $header, ';');

// Data per Entitas
foreach ($s['hasil'] as $r) {
    $row = [
        $r['peringkat'] ?? '–',
        $r['entitas']['nama'],
        KATEGORI_ANGGARAN[$r['entitas']['kategori_anggaran']] ?? $r['entitas']['kategori_anggaran'],
    ];

    // Pilar
    foreach ($s['pilar'] as $pid => $p) {
        $v = $r['pilar'][$pid]['nilai'] ?? null;
        $row[] = $v !== null ? number_format($v, 2, ',', '') : '–';
    }

    // Detail Indikator
    foreach ($s['indikator'] as $ind) {
        $d = $r['detail'][(int)$ind['id']];
        if ($d['status'] === 'na') {
            $row[] = 'N/A';
        } elseif ($d['nilai'] !== null) {
            $row[] = number_format($d['nilai'], 2, ',', '');
        } else {
            $row[] = '–';
        }
    }

    // Total & Predikat
    $row[] = $r['total'] !== null ? number_format($r['total'], 2, ',', '') : '–';
    $row[] = $r['predikat']['nama'] ?? 'Belum dinilai';
    $row[] = $r['predikat'] ? JENIS_PREDIKAT[$r['predikat']['jenis']] ?? '' : '';
    $row[] = $r['predikat']['konsekuensi'] ?? '';
    $row[] = number_format($r['kelengkapan'], 0) . '%';

    fputcsv($out, $row, ';');
}

fclose($out);
exit;
