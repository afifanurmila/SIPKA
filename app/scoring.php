<?php
declare(strict_types=1);

/**
 * Mesin penilaian.
 *
 * Aturan:
 *  - Nilai indikator = rata-rata sub-periode yang sudah terisi (mis. IKPA bulanan).
 *  - Indikator bertanda "Tidak Berlaku" (N/A) dan indikator yang belum terisi tidak ikut dihitung;
 *    bobotnya dibagi ulang secara proporsional:  Total = Σ(bobot × nilai) / Σ(bobot indikator yang dihitung).
 *    (Sama dengan rumus Excel "×100/85" ketika Survei Kepuasan tidak berlaku.)
 *  - Predikat ditentukan dari ambang nilai minimum per tahun; predikat terendah menjadi batas bawah.
 */
function hitung_skor(int $tahunId, string $level): array
{
    $indikator = q_all(
        'SELECT i.*, p.nama AS pilar_nama, p.urutan AS pilar_urutan, b.singkatan AS bidang_singkatan, b.nama AS bidang_nama
           FROM indikator i
           JOIN pilar p ON p.id = i.pilar_id
           LEFT JOIN bidang b ON b.id = i.bidang_id
          WHERE i.tahun_id = ? AND i.level = ?
          ORDER BY p.urutan, p.id, i.urutan, i.id',
        [$tahunId, $level]
    );

    $nilai = [];
    foreach (q_all('SELECT n.indikator_id, n.entitas_id, n.sub_periode, n.nilai
                      FROM nilai n JOIN indikator i ON i.id = n.indikator_id
                     WHERE i.tahun_id = ? AND i.level = ?', [$tahunId, $level]) as $r) {
        $nilai[(int)$r['entitas_id']][(int)$r['indikator_id']][(int)$r['sub_periode']] = (float)$r['nilai'];
    }

    $isian = [];
    foreach (q_all('SELECT s.* FROM isian s JOIN indikator i ON i.id = s.indikator_id
                     WHERE i.tahun_id = ? AND i.level = ?', [$tahunId, $level]) as $r) {
        $isian[(int)$r['entitas_id']][(int)$r['indikator_id']] = $r;
    }

    $predikat = daftar_predikat($tahunId);
    $pilarList = [];
    foreach ($indikator as $ind) {
        $pilarList[(int)$ind['pilar_id']] ??= ['nama' => $ind['pilar_nama'], 'bobot' => 0.0];
        $pilarList[(int)$ind['pilar_id']]['bobot'] += (float)$ind['bobot'];
    }

    $hasil = [];
    foreach (entitas_peserta($tahunId, $level) as $e) {
        $eid = (int)$e['id'];
        $sumWV = 0.0;
        $sumW = 0.0;
        $berlaku = 0;
        $terisi = 0;
        $detail = [];
        $pilar = [];

        foreach ($indikator as $ind) {
            $iid = (int)$ind['id'];
            $w = (float)$ind['bobot'];
            $jumlahSub = FREKUENSI[$ind['frekuensi']] ?? 1;
            $vals = $nilai[$eid][$iid] ?? [];
            $meta = $isian[$eid][$iid] ?? null;

            $d = [
                'status'     => 'kosong',
                'nilai'      => null,
                'per_sub'    => $vals,
                'terisi'     => count($vals),
                'jumlah_sub' => $jumlahSub,
                'catatan'    => $meta['catatan'] ?? null,
                'bukti_url'  => $meta['bukti_url'] ?? null,
                'bobot'      => $w,
                'kontribusi' => null,
            ];

            if ($meta && (int)$meta['na'] === 1) {
                $d['status'] = 'na';
            } elseif ($vals) {
                $d['nilai'] = array_sum($vals) / count($vals);
                $d['status'] = count($vals) >= $jumlahSub ? 'lengkap' : 'sebagian';
                $sumWV += $w * $d['nilai'];
                $sumW += $w;
            }

            if ($d['status'] !== 'na') {
                $berlaku++;
                if ($d['nilai'] !== null) {
                    $terisi++;
                }
            }

            $pid = (int)$ind['pilar_id'];
            $pilar[$pid] ??= ['nama' => $ind['pilar_nama'], 'wv' => 0.0, 'w' => 0.0, 'nilai' => null];
            if ($d['nilai'] !== null) {
                $pilar[$pid]['wv'] += $w * $d['nilai'];
                $pilar[$pid]['w'] += $w;
            }
            $detail[$iid] = $d;
        }

        $total = $sumW > 0 ? $sumWV / $sumW : null;
        foreach ($detail as &$d) {
            if ($d['nilai'] !== null && $sumW > 0) {
                $d['kontribusi'] = $d['bobot'] * $d['nilai'] / $sumW;
            }
        }
        unset($d);
        foreach ($pilar as &$p) {
            $p['nilai'] = $p['w'] > 0 ? $p['wv'] / $p['w'] : null;
        }
        unset($p);

        $hasil[] = [
            'entitas'        => $e,
            'detail'         => $detail,
            'pilar'          => $pilar,
            'total'          => $total,
            'bobot_dihitung' => $sumW,
            'kelengkapan'    => $berlaku > 0 ? $terisi / $berlaku * 100 : 0.0,
            'predikat'       => $total === null ? null : predikat_untuk($total, $predikat),
            'peringkat'      => null,
        ];
    }

    usort($hasil, function ($a, $b) {
        if ($a['total'] === null && $b['total'] === null) {
            return strcmp($a['entitas']['nama'], $b['entitas']['nama']);
        }
        if ($a['total'] === null) {
            return 1;
        }
        if ($b['total'] === null) {
            return -1;
        }
        return $b['total'] <=> $a['total'];
    });

    $rank = 0;
    $prev = null;
    foreach ($hasil as $i => &$r) {
        if ($r['total'] === null) {
            continue;
        }
        $rounded = round($r['total'], 4);
        if ($prev === null || $rounded !== $prev) {
            $rank = $i + 1;
            $prev = $rounded;
        }
        $r['peringkat'] = $rank;
    }
    unset($r);

    return [
        'indikator'   => $indikator,
        'pilar'       => $pilarList,
        'hasil'       => $hasil,
        'total_bobot' => array_sum(array_map(fn($i) => (float)$i['bobot'], $indikator)),
        'predikat'    => $predikat,
    ];
}

function predikat_untuk(float $total, array $predikat): ?array
{
    foreach ($predikat as $p) {
        if ($total >= (float)$p['min_nilai']) {
            return $p;
        }
    }
    return $predikat ? end($predikat) : null;
}
