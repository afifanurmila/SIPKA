<?php
declare(strict_types=1);

/**
 * Data awal diambil dari file "Skor Reward and Punishment.xlsx":
 *  - 2024 Satker  : sheet "Penilaian Akhir 2024-IKPA-Satker" (IKPA bulanan 80%, TL 15%, SAKIP 5%)
 *  - 2025 Satker  : sheet "Penilaian Akhir Satker 2025"
 *  - 2025 Unit    : sheet "Penilaian Unor 2025"
 *  - 2026         : salinan konfigurasi 2025, status Pengisian (belum ada nilai)
 */
function isi_data_awal(): void
{
    // --- Bidang pengampu --------------------------------------------------
    $bidang = [];
    foreach ([
        'renkeu' => ['Biro Perencanaan dan Keuangan', 'Renkeu'],
        'itama'  => ['Inspektorat', 'Inspektorat'],
        'umum'   => ['Biro Umum, Kerja Sama, dan Hubungan Masyarakat (BMN & Barjas)', 'Biro Umum'],
        'hos'    => ['Biro Hukum, Organisasi, dan Sumber Daya Manusia', 'Biro HOS'],
    ] as $k => [$nama, $singkat]) {
        q('INSERT INTO bidang (nama, singkatan) VALUES (?, ?)', [$nama, $singkat]);
        $bidang[$k] = last_id();
    }

    // --- Entitas: Satker --------------------------------------------------
    $satker = [];
    $urut = 0;
    foreach ([
        ['LAN Jakarta', 'RM', 1],
        ['PUSJAR SKTASNAS', 'RM', 1],
        ['PUSJAR SKPP', 'RM', 1],
        ['PUSJAR SKMP', 'RM', 1],
        ['PUSJAR SKMK', 'RM', 1],
        ['Politeknik STIA LAN Jakarta', 'PNBP_RM', 1],
        ['Politeknik STIA LAN Bandung', 'PNBP_RM', 1],
        ['Politeknik STIA LAN Makassar', 'PNBP_RM', 1],
        // Nomenklatur 2024 (tidak aktif lagi sejak 2025)
        ['Puslatbang KHAN', 'RM', 0],
        ['Puslatbang KMP', 'RM', 0],
        ['Puslatbang PKASN', 'RM', 0],
        ['Puslatbang KDOD', 'RM', 0],
    ] as [$nama, $kat, $aktif]) {
        q('INSERT INTO entitas (nama, level, kategori_anggaran, aktif, urutan) VALUES (?, ?, ?, ?, ?)',
            [$nama, 'satker', $kat, $aktif, ++$urut]);
        $satker[$nama] = last_id();
    }

    // --- Entitas: Unit kerja (di bawah LAN Jakarta) ----------------------
    $unitData2025 = [
        // nama => [NK Perencanaan, Serapan, MR, SAKIP, SKM (null = tidak berlaku)]
        'Biro Perencanaan dan Keuangan'                    => [8.75, 82.60, 66.67, 74.54, 83.53],
        'Biro Hukum, Organisasi, dan Sumber Daya Manusia'  => [56.72, 76.29, 83.33, 71.21, 81.54],
        'Biro Umum, Kerja Sama, dan Hubungan Masyarakat'   => [41.87, 82.76, 35, 62.51, 81.66],
        'Inspektorat'                                      => [41.13, 61.09, 86.67, 85.22, 85.16],
        'Pusat Data dan Informasi'                         => [35.80, 78.21, 88.33, 74.84, 77.80],
        'DIT. SPK2AN'                                      => [30.33, 96.84, 88.33, 75.83, null],
        'DIT. APK2'                                        => [3.00, 62.35, 75, 68.99, null],
        'DIT. PKJF ASN'                                    => [29.36, 73.48, 58.33, 69.86, 90.18],
        'DIT. SPT'                                         => [3.00, 95.10, 38.33, 72.89, null],
        'DIT. TEKDIJAR'                                    => [3.00, 93.03, 85, 70.62, null],
        'DIT. EKOJAR'                                      => [3.00, 66.91, 80, 75.33, null],
        'DITJAR MAPIM'                                     => [60.32, 83.34, 83.33, 71.26, 94.50],
        'DIRJAR KSK'                                       => [52.63, 78.87, 28.33, 71.67, 91.75],
        'DITJAR TF'                                        => [39.47, 82.95, 58.33, 73.03, 90.50],
        'DIT PMPK'                                         => [30.33, 98.65, 81.67, 72.89, null],
        'DIT PMP'                                          => [32.00, 83.71, 56.67, 72.89, 90.75],
    ];
    $unit = [];
    $urut = 0;
    foreach (array_keys($unitData2025) as $nama) {
        q('INSERT INTO entitas (nama, level, induk_id, kategori_anggaran, aktif, urutan) VALUES (?, ?, ?, ?, 1, ?)',
            [$nama, 'unit', $satker['LAN Jakarta'], 'RM', ++$urut]);
        $unit[$nama] = last_id();
    }

    // --- Pengguna demo ----------------------------------------------------
    $users = [
        ['admin', 'admin123', 'Admin Biro Perencanaan dan Keuangan', 'admin', $bidang['renkeu'], null],
        ['inspektorat', 'pengampu123', 'Pengampu Inspektorat', 'pengampu', $bidang['itama'], null],
        ['biroumum', 'pengampu123', 'Pengampu BMN & Barjas (Biro Umum)', 'pengampu', $bidang['umum'], null],
        ['birohos', 'pengampu123', 'Pengampu Biro HOS', 'pengampu', $bidang['hos'], null],
        ['lanjakarta', 'satker123', 'Operator LAN Jakarta', 'entitas', null, $satker['LAN Jakarta']],
        ['poltekbdg', 'satker123', 'Operator Politeknik STIA LAN Bandung', 'entitas', null, $satker['Politeknik STIA LAN Bandung']],
        ['pusdatin', 'satker123', 'Operator Pusat Data dan Informasi', 'entitas', null, $unit['Pusat Data dan Informasi']],
        ['pimpinan', 'pimpinan123', 'Sekretaris Utama', 'pimpinan', null, null],
    ];
    foreach ($users as [$un, $pw, $nama, $role, $bid, $ent]) {
        q('INSERT INTO users (username, password_hash, nama, role, bidang_id, entitas_id) VALUES (?, ?, ?, ?, ?, ?)',
            [$un, password_hash($pw, PASSWORD_DEFAULT), $nama, $role, $bid, $ent]);
    }
    $adminId = (int)q_val("SELECT id FROM users WHERE username = 'admin'");

    // --- Helper -----------------------------------------------------------
    $buatTahun = function (int $tahun, string $status, string $catatan): int {
        q('INSERT INTO tahun (tahun, status, catatan) VALUES (?, ?, ?)', [$tahun, $status, $catatan]);
        return last_id();
    };
    /** $struktur: [namaPilar => [[kode, nama, bobot, bidangKey, frekuensi, deskripsi], ...]] → [kode => id] */
    $buatIndikator = function (int $tahunId, string $level, array $struktur) use ($bidang): array {
        $map = [];
        $up = 0;
        foreach ($struktur as $pilarNama => $items) {
            q('INSERT INTO pilar (tahun_id, level, nama, urutan) VALUES (?, ?, ?, ?)', [$tahunId, $level, $pilarNama, ++$up]);
            $pid = last_id();
            $ui = 0;
            foreach ($items as [$kode, $nama, $bobot, $bid, $frek, $desk]) {
                q('INSERT INTO indikator (tahun_id, level, pilar_id, kode, nama, bobot, bidang_id, frekuensi, deskripsi, urutan)
                   VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
                    [$tahunId, $level, $pid, $kode, $nama, $bobot, $bidang[$bid], $frek, $desk, ++$ui]);
                $map[$kode] = last_id();
            }
        }
        return $map;
    };
    $isiNilai = function (int $indId, int $entId, int $sub, float $v): void {
        q('INSERT INTO nilai (indikator_id, entitas_id, sub_periode, nilai) VALUES (?, ?, ?, ?)', [$indId, $entId, $sub, $v]);
    };
    $tandaiNA = function (int $indId, int $entId, string $alasan) use ($adminId): void {
        q('INSERT INTO isian (indikator_id, entitas_id, na, catatan, updated_by, updated_at) VALUES (?, ?, 1, ?, ?, ?)',
            [$indId, $entId, $alasan, $adminId, date('Y-m-d H:i:s')]);
    };

    // =====================================================================
    // 2024 — Satker (IKPA bulanan)
    // =====================================================================
    $t2024 = $buatTahun(2024, 'final', 'Penilaian satker berbasis IKPA Januari–November 2024, TL Rekomendasi Hasil Audit, dan SAKIP.');
    buat_predikat_default($t2024);
    $i24 = $buatIndikator($t2024, 'satker', [
        'Pelaksanaan Anggaran' => [
            ['IKPA', 'Indikator Kinerja Pelaksanaan Anggaran (IKPA)', 80, 'renkeu', 'bulanan', 'Nilai IKPA bulanan dari OMSPAN; nilai indikator = rata-rata bulan yang tersedia.'],
        ],
        'Pengawasan' => [
            ['TL', 'Tindak Lanjut Rekomendasi Hasil Audit', 15, 'itama', 'tahunan', '% tindak lanjut yang sudah sesuai.'],
            ['SAKIP', 'Nilai Evaluasi SAKIP', 5, 'itama', 'tahunan', 'Nilai evaluasi SAKIP satker.'],
        ],
    ]);
    $data2024 = [
        'Puslatbang KHAN'              => [[100, 100, 99.9, 93.19, 96.33, 97.7, 95.94, 97.41, 98.32, 96.54, 97.14], 100, 77.8],
        'Puslatbang KMP'               => [[100, 100, 99.84, 94.03, 95.83, 98.41, 96.6, 97.09, 97.49, 95.13, 95.82], 95, 76.3],
        'LAN Jakarta'                  => [[93.8, 97.66, 98.59, 96.53, 97.94, 97.36, 96.62, 97.91, 97.93, 96.38, 97.14], 95, 71.48],
        'Puslatbang PKASN'             => [[94.85, 96.91, 100, 94.62, 96.49, 98.57, 95.2, 96.28, 97.05, 95.78, 96.24], 95, 76.8],
        'Politeknik STIA LAN Makassar' => [[86.74, 96.02, 98.24, 93.33, 95.5, 97.37, 96.23, 97.11, 97.24, 96.53, 96.97], 100, 75.99],
        'Puslatbang KDOD'              => [[96.55, 91.19, 94.73, 91.68, 94.67, 95.67, 95.67, 96.11, 96.82, 96.28, 95.8], 100, 78.99],
        'Politeknik STIA LAN Bandung'  => [[99.19, 97.98, 100, 92.87, 92.51, 94.05, 92.18, 94.17, 94.44, 95.06, 97.19], 90, 75.36],
        'Politeknik STIA LAN Jakarta'  => [[94.67, 95.82, 91.23, 89.06, 93.74, 93.98, 90.74, 90.34, 91.59, 91.53, 91.84], 80, 72.68],
    ];
    foreach ($data2024 as $nama => [$ikpa, $tl, $sakip]) {
        foreach ($ikpa as $bulan => $v) {
            $isiNilai($i24['IKPA'], $satker[$nama], $bulan + 1, $v);
        }
        $isiNilai($i24['TL'], $satker[$nama], 1, $tl);
        $isiNilai($i24['SAKIP'], $satker[$nama], 1, $sakip);
    }

    // =====================================================================
    // 2025 — Satker
    // =====================================================================
    $t2025 = $buatTahun(2025, 'final', 'Hasil penilaian 2025 dari file Skor Reward and Punishment.xlsx.');
    buat_predikat_default($t2025);
    $struktur2025Satker = [
        'Nilai Kinerja Anggaran' => [
            ['NKPA', 'Nilai Kinerja Perencanaan Anggaran', 15, 'renkeu', 'tahunan', 'Sesuai bobot PMK: 75% Capaian Rincian Output, 10% Penggunaan SBK, 15% Efisiensi SBK.'],
            ['IKPA', 'NK Pelaksanaan Anggaran (IKPA)', 15, 'renkeu', 'tahunan', 'Nilai Indikator Kinerja Pelaksanaan Anggaran keseluruhan.'],
        ],
        'Pengadaan Barang/Jasa' => [
            ['RUP', 'Persentase Pengumuman RUP', 5, 'umum', 'tahunan', '% jumlah pagu yang diumumkan / total pagu pengadaan.'],
            ['REALPBJ', 'Persentase Pencatatan Realisasi Pengadaan', 10, 'umum', 'tahunan', '% nilai yang dicatatkan / total pagu pengadaan yang diumumkan.'],
        ],
        'Barang Milik Negara' => [
            ['IPA', 'Indeks Pengelolaan Aset', 15, 'umum', 'tahunan', 'IPA per satker: realisasi PNBP aset, ketepatan RKBMN/LBP/Wasdal, tindak lanjut BMN, temuan BPK terkait BMN, kesesuaian SBSK.'],
        ],
        'Pengawasan' => [
            ['TLHA', 'Tindak Lanjut Rekomendasi Hasil Audit', 15, 'itama', 'tahunan', '% TL yang sudah sesuai dalam 2 tahun terakhir.'],
            ['MR', 'Manajemen Risiko', 5, 'itama', 'tahunan', 'Kualitas dokumen, tindak lanjut revisi, dan pemantauan MR.'],
            ['IPPK', 'Indeks Program Pencegahan Korupsi', 10, 'itama', 'tahunan', 'LHKAN, keaktifan diseminasi anti gratifikasi, mitigasi gratifikasi.'],
            ['SAKIP', 'Nilai Evaluasi SAKIP', 10, 'itama', 'tahunan', 'Nilai evaluasi SAKIP satker.'],
        ],
    ];
    $i25s = $buatIndikator($t2025, 'satker', $struktur2025Satker);
    $data2025Satker = [
        'LAN Jakarta'                  => [46.16, 93.76, 81.18, 9.94, 94.4, 66.0, 68.33, 89.06, null],
        'PUSJAR SKTASNAS'              => [67.83, 97.35, 60.61, 13.44, 96.3, 96.0, 88.33, 89.31, 79.45],
        'PUSJAR SKPP'                  => [42.78, 93.97, 36.79, 22.18, 96.8, 95.0, 85.0, 91.16, 80.01],
        'PUSJAR SKMP'                  => [75.74, 92.49, 36.8, 0.0, 97.9, 91.43, 58.33, 91.51, 79.43],
        'PUSJAR SKMK'                  => [54.43, 95.83, 88.55, 10.01, 95.6, 100.0, 83.33, 90.53, 79.8],
        'Politeknik STIA LAN Jakarta'  => [39.43, 95.56, 63.24, 11.31, 97.8, 97.14, 68.33, 88.18, 77.64],
        'Politeknik STIA LAN Bandung'  => [55.42, 98.74, 31.14, 18.24, 97.5, 97.56, 31.67, 86.07, 74.39],
        'Politeknik STIA LAN Makassar' => [39.92, 98.04, 83.32, 17.34, 97.8, 100.0, 35.0, 88.27, 77.59],
    ];
    $kodeSatker = ['NKPA', 'IKPA', 'RUP', 'REALPBJ', 'IPA', 'TLHA', 'MR', 'IPPK', 'SAKIP'];
    foreach ($data2025Satker as $nama => $vals) {
        foreach ($vals as $idx => $v) {
            if ($v === null) {
                $tandaiNA($i25s[$kodeSatker[$idx]], $satker[$nama], 'Tidak ada evaluasi SAKIP di level satker LAN Jakarta');
            } else {
                $isiNilai($i25s[$kodeSatker[$idx]], $satker[$nama], 1, $v);
            }
        }
    }

    // =====================================================================
    // 2025 — Unit Kerja / Unor
    // =====================================================================
    $i25u = $buatIndikator($t2025, 'unit', [
        'Perencanaan dan Keuangan' => [
            ['NKPA', 'Nilai Kinerja Perencanaan Anggaran', 25, 'renkeu', 'tahunan', 'Sesuai bobot PMK: 75% Capaian Rincian Output, 10% Penggunaan SBK, 15% Efisiensi SBK.'],
            ['SERAPAN', 'Serapan Anggaran (Bendahara)', 20, 'renkeu', 'tahunan', 'Sinkronisasi realisasi perencanaan dan keuangan SP2D/Bendahara (pagu terakhir).'],
        ],
        'Pengawasan' => [
            ['MR', 'Manajemen Risiko', 15, 'itama', 'tahunan', 'Kualitas dokumen, tindak lanjut revisi, dan pemantauan MR.'],
            ['SAKIP', 'Nilai Evaluasi SAKIP', 25, 'itama', 'tahunan', 'Nilai evaluasi SAKIP unit kerja.'],
        ],
        'Kualitas Layanan' => [
            ['SKM', 'Survei Kepuasan Pengguna Layanan', 15, 'hos', 'tahunan', 'Hasil Survei Kepuasan Masyarakat (SKM).'],
        ],
    ]);
    $kodeUnit = ['NKPA', 'SERAPAN', 'MR', 'SAKIP', 'SKM'];
    foreach ($unitData2025 as $nama => $vals) {
        foreach ($vals as $idx => $v) {
            if ($v === null) {
                $tandaiNA($i25u[$kodeUnit[$idx]], $unit[$nama], 'Tidak memiliki layanan yang disurvei (SKM)');
            } else {
                $isiNilai($i25u[$kodeUnit[$idx]], $unit[$nama], 1, $v);
            }
        }
    }

    // =====================================================================
    // 2026 — salin konfigurasi 2025, masa pengisian
    // =====================================================================
    $t2026 = $buatTahun(2026, 'pengisian', 'Konfigurasi disalin dari 2025. Silakan sesuaikan indikator & bobot sebelum pengisian.');
    salin_konfigurasi($t2025, $t2026);
}
