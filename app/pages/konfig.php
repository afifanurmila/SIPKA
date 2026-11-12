<?php
declare(strict_types=1);

$u = require_role('admin');
$tab = $_GET['tab'] ?? 'tahun';
$tahun = pilih_tahun();

// Handle POST actions
if (is_post()) {
    csrf_check();
    $aksi = $_POST['aksi'] ?? '';

    // --- TAB TAHUN ---
    if ($aksi === 'tambah_tahun') {
        $tahunAngka = (int)($_POST['tahun'] ?? 0);
        $salinDari = (int)($_POST['salin_dari'] ?? 0);
        $catatan = trim((string)($_POST['catatan'] ?? ''));

        if ($tahunAngka < 2000 || $tahunAngka > 2100) {
            flash('error', 'Tahun anggaran tidak valid.');
        } elseif (q_one('SELECT id FROM tahun WHERE tahun = ?', [$tahunAngka])) {
            flash('error', "Tahun $tahunAngka sudah ada.");
        } else {
            transaksi(function () use ($tahunAngka, $salinDari, $catatan) {
                q('INSERT INTO tahun (tahun, status, catatan) VALUES (?, ?, ?)', [$tahunAngka, 'pengisian', $catatan]);
                $baruId = last_id();
                if ($salinDari > 0) {
                    salin_konfigurasi($salinDari, $baruId);
                } else {
                    buat_predikat_default($baruId);
                }
            });
            log_act('tambah_tahun', "Membuat tahun anggaran $tahunAngka");
            flash('success', "Tahun anggaran $tahunAngka berhasil dibuat.");
            redirect(url('konfig', ['tab' => 'tahun']));
        }
    }

    if ($aksi === 'ubah_status_tahun') {
        $tid = (int)($_POST['tahun_id'] ?? 0);
        $status = $_POST['status'] ?? 'pengisian';
        if (isset(STATUS_TAHUN[$status])) {
            q('UPDATE tahun SET status = ? WHERE id = ?', [$status, $tid]);
            log_act('ubah_status_tahun', "Tahun ID $tid status diubah ke $status");
            flash('success', 'Status tahun penilaian berhasil diperbarui.');
        }
        redirect(url('konfig', ['tab' => 'tahun']));
    }

    // --- TAB INDIKATOR & PILAR ---
    if ($aksi === 'tambah_pilar') {
        $tid = (int)($_POST['tahun_id'] ?? 0);
        $lvl = $_POST['level'] ?? 'satker';
        $nama = trim((string)($_POST['nama'] ?? ''));
        $urutan = (int)($_POST['urutan'] ?? 0);
        if ($nama !== '') {
            q('INSERT INTO pilar (tahun_id, level, nama, urutan) VALUES (?, ?, ?, ?)', [$tid, $lvl, $nama, $urutan]);
            flash('success', 'Pilar berhasil ditambahkan.');
        }
        redirect(url('konfig', ['tab' => 'indikator', 't' => $tid, 'lvl' => $lvl]));
    }

    if ($aksi === 'simpan_indikator') {
        $tid = (int)($_POST['tahun_id'] ?? 0);
        $lvl = $_POST['level'] ?? 'satker';
        $pid = (int)($_POST['pilar_id'] ?? 0);
        $kode = trim((string)($_POST['kode'] ?? ''));
        $nama = trim((string)($_POST['nama'] ?? ''));
        $bobot = (float)str_replace(',', '.', (string)($_POST['bobot'] ?? '0'));
        $bidangId = !empty($_POST['bidang_id']) ? (int)$_POST['bidang_id'] : null;
        $frek = $_POST['frekuensi'] ?? 'tahunan';
        $deskripsi = trim((string)($_POST['deskripsi'] ?? ''));
        $urutan = (int)($_POST['urutan'] ?? 0);
        $id = (int)($_POST['id'] ?? 0);

        if ($nama === '' || $bobot <= 0) {
            flash('error', 'Nama indikator dan bobot (> 0) wajib diisi.');
        } else {
            if ($id > 0) {
                q(
                    'UPDATE indikator SET pilar_id = ?, kode = ?, nama = ?, bobot = ?, bidang_id = ?, frekuensi = ?, deskripsi = ?, urutan = ? WHERE id = ?',
                    [$pid, $kode ?: null, $nama, $bobot, $bidangId, $frek, $deskripsi ?: null, $urutan, $id]
                );
                flash('success', "Indikator '$nama' berhasil diperbarui.");
            } else {
                q(
                    'INSERT INTO indikator (tahun_id, level, pilar_id, kode, nama, bobot, bidang_id, frekuensi, deskripsi, urutan)
                     VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
                    [$tid, $lvl, $pid, $kode ?: null, $nama, $bobot, $bidangId, $frek, $deskripsi ?: null, $urutan]
                );
                flash('success', "Indikator '$nama' berhasil ditambahkan.");
            }
        }
        redirect(url('konfig', ['tab' => 'indikator', 't' => $tid, 'lvl' => $lvl]));
    }

    if ($aksi === 'hapus_indikator') {
        $id = (int)($_POST['id'] ?? 0);
        $ind = q_one('SELECT * FROM indikator WHERE id = ?', [$id]);
        if ($ind) {
            q('DELETE FROM indikator WHERE id = ?', [$id]);
            flash('success', "Indikator '{$ind['nama']}' berhasil dihapus.");
            redirect(url('konfig', ['tab' => 'indikator', 't' => $ind['tahun_id'], 'lvl' => $ind['level']]));
        }
        redirect(url('konfig', ['tab' => 'indikator']));
    }

    // --- TAB PREDIKAT ---
    if ($aksi === 'simpan_predikat') {
        $tid = (int)($_POST['tahun_id'] ?? 0);
        $nama = trim((string)($_POST['nama'] ?? ''));
        $min = (float)str_replace(',', '.', (string)($_POST['min_nilai'] ?? '0'));
        $warna = trim((string)($_POST['warna'] ?? '#2563eb'));
        $jenis = $_POST['jenis'] ?? 'reward';
        $konsekuensi = trim((string)($_POST['konsekuensi'] ?? ''));
        $id = (int)($_POST['id'] ?? 0);

        if ($nama === '') {
            flash('error', 'Nama predikat wajib diisi.');
        } else {
            if ($id > 0) {
                q('UPDATE predikat SET nama = ?, min_nilai = ?, warna = ?, jenis = ?, konsekuensi = ? WHERE id = ?',
                    [$nama, $min, $warna, $jenis, $konsekuensi ?: null, $id]);
            } else {
                q('INSERT INTO predikat (tahun_id, nama, min_nilai, warna, jenis, konsekuensi) VALUES (?, ?, ?, ?, ?, ?)',
                    [$tid, $nama, $min, $warna, $jenis, $konsekuensi ?: null]);
            }
            flash('success', 'Skema predikat berhasil disimpan.');
        }
        redirect(url('konfig', ['tab' => 'predikat', 't' => $tid]));
    }

    if ($aksi === 'hapus_predikat') {
        $id = (int)($_POST['id'] ?? 0);
        q('DELETE FROM predikat WHERE id = ?', [$id]);
        flash('success', 'Predikat berhasil dihapus.');
        redirect(url('konfig', ['tab' => 'predikat', 't' => $tahun['id']]));
    }

    // --- TAB ENTITAS (SATKER / UNIT) ---
    if ($aksi === 'simpan_entitas') {
        $nama = trim((string)($_POST['nama'] ?? ''));
        $lvl = $_POST['level'] ?? 'satker';
        $kat = $_POST['kategori_anggaran'] ?? 'RM';
        $indukId = !empty($_POST['induk_id']) ? (int)$_POST['induk_id'] : null;
        $aktif = !empty($_POST['aktif']) ? 1 : 0;
        $urutan = (int)($_POST['urutan'] ?? 0);
        $id = (int)($_POST['id'] ?? 0);

        if ($nama === '') {
            flash('error', 'Nama satker/unit kerja tidak boleh kosong.');
        } else {
            if ($id > 0) {
                q('UPDATE entitas SET nama = ?, level = ?, kategori_anggaran = ?, induk_id = ?, aktif = ?, urutan = ? WHERE id = ?',
                    [$nama, $lvl, $kat, $indukId, $aktif, $urutan, $id]);
                flash('success', "Entitas '$nama' berhasil diperbarui.");
            } else {
                q('INSERT INTO entitas (nama, level, kategori_anggaran, induk_id, aktif, urutan) VALUES (?, ?, ?, ?, ?, ?)',
                    [$nama, $lvl, $kat, $indukId, $aktif, $urutan]);
                flash('success', "Entitas '$nama' berhasil ditambahkan.");
            }
        }
        redirect(url('konfig', ['tab' => 'entitas']));
    }

    // --- TAB USERS ---
    if ($aksi === 'simpan_user') {
        $username = trim((string)($_POST['username'] ?? ''));
        $nama = trim((string)($_POST['nama'] ?? ''));
        $role = $_POST['role'] ?? 'entitas';
        $bidangId = !empty($_POST['bidang_id']) ? (int)$_POST['bidang_id'] : null;
        $entitasId = !empty($_POST['entitas_id']) ? (int)$_POST['entitas_id'] : null;
        $password = trim((string)($_POST['password'] ?? ''));
        $aktif = !empty($_POST['aktif']) ? 1 : 0;
        $id = (int)($_POST['id'] ?? 0);

        if ($username === '' || $nama === '') {
            flash('error', 'Username dan nama lengkap wajib diisi.');
        } else {
            if ($id > 0) {
                if ($password !== '') {
                    q('UPDATE users SET username = ?, nama = ?, role = ?, bidang_id = ?, entitas_id = ?, aktif = ?, password_hash = ? WHERE id = ?',
                        [$username, $nama, $role, $bidangId, $entitasId, $aktif, password_hash($password, PASSWORD_DEFAULT), $id]);
                } else {
                    q('UPDATE users SET username = ?, nama = ?, role = ?, bidang_id = ?, entitas_id = ?, aktif = ? WHERE id = ?',
                        [$username, $nama, $role, $bidangId, $entitasId, $aktif, $id]);
                }
                flash('success', "Pengguna '$username' berhasil diperbarui.");
            } else {
                if ($password === '') {
                    flash('error', 'Password awal wajib diisi untuk pengguna baru.');
                } elseif (q_one('SELECT id FROM users WHERE username = ?', [$username])) {
                    flash('error', "Username '$username' sudah digunakan.");
                } else {
                    q('INSERT INTO users (username, password_hash, nama, role, bidang_id, entitas_id, aktif) VALUES (?, ?, ?, ?, ?, ?, ?)',
                        [$username, password_hash($password, PASSWORD_DEFAULT), $nama, $role, $bidangId, $entitasId, $aktif]);
                    flash('success', "Pengguna '$username' berhasil dibuat.");
                }
            }
        }
        redirect(url('konfig', ['tab' => 'users']));
    }
}

$level = pilih_level();
$semuaBidang = q_all('SELECT * FROM bidang ORDER BY id');
$semuaSatker = q_all("SELECT * FROM entitas WHERE level = 'satker' ORDER BY nama");

render_header('Konfigurasi Sistem', 'konfig');
?>
<div class="page-head">
    <h2>Konfigurasi Sistem</h2>
</div>

<div class="tabs-nav">
    <a href="<?= h(url('konfig', ['tab' => 'tahun'])) ?>" class="<?= $tab === 'tahun' ? 'active' : '' ?>">📅 Periode &amp; Tahun</a>
    <a href="<?= h(url('konfig', ['tab' => 'indikator'])) ?>" class="<?= $tab === 'indikator' ? 'active' : '' ?>">📊 Pilar &amp; Indikator</a>
    <a href="<?= h(url('konfig', ['tab' => 'predikat'])) ?>" class="<?= $tab === 'predikat' ? 'active' : '' ?>">🏆 Predikat &amp; Ambang</a>
    <a href="<?= h(url('konfig', ['tab' => 'entitas'])) ?>" class="<?= $tab === 'entitas' ? 'active' : '' ?>">🏛️ Satker &amp; Unit Kerja</a>
    <a href="<?= h(url('konfig', ['tab' => 'users'])) ?>" class="<?= $tab === 'users' ? 'active' : '' ?>">👥 Manajemen Pengguna</a>
</div>

<?php if ($tab === 'tahun'): ?>
    <div class="grid-2">
        <div class="card">
            <h3>Daftar Tahun Penilaian</h3>
            <table class="table">
                <thead><tr><th>Tahun</th><th>Status</th><th>Catatan</th><th>Ubah Status</th></tr></thead>
                <tbody>
                <?php foreach (semua_tahun() as $t): ?>
                    <tr>
                        <td><strong><?= (int)$t['tahun'] ?></strong></td>
                        <td><?= status_badge($t['status']) ?></td>
                        <td class="small muted"><?= h($t['catatan'] ?? '–') ?></td>
                        <td>
                            <form method="post" class="inline-form">
                                <?= csrf_field() ?>
                                <input type="hidden" name="aksi" value="ubah_status_tahun">
                                <input type="hidden" name="tahun_id" value="<?= $t['id'] ?>">
                                <select name="status" data-autosubmit>
                                    <?php foreach (STATUS_TAHUN as $sk => $sl): ?>
                                        <option value="<?= $sk ?>" <?= $t['status'] === $sk ? 'selected' : '' ?>><?= h($sl) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <div class="card">
            <h3>Buka Tahun Penilaian Baru</h3>
            <form method="post" class="stack">
                <?= csrf_field() ?>
                <input type="hidden" name="aksi" value="tambah_tahun">
                <label>Tahun Anggaran
                    <input type="number" name="tahun" value="<?= (int)date('Y') + 1 ?>" required min="2020" max="2099">
                </label>
                <label>Salin Struktur Indikator Dari
                    <select name="salin_dari">
                        <option value="0">— Mulai dari kosong —</option>
                        <?php foreach (semua_tahun() as $t): ?>
                            <option value="<?= $t['id'] ?>">Tahun <?= (int)$t['tahun'] ?></option>
                        <?php endforeach; ?>
                    </select>
                </label>
                <label>Catatan / Keterangan
                    <textarea name="catatan" rows="2" placeholder="Catatan regulasi / dasar penilaian"></textarea>
                </label>
                <button class="btn btn-primary">Buat Tahun Penilaian</button>
            </form>
        </div>
    </div>

<?php elseif ($tab === 'indikator'): ?>
    <?php
    $pilarList = q_all('SELECT * FROM pilar WHERE tahun_id = ? AND level = ? ORDER BY urutan, id', [$tahun['id'], $level]);
    $indList = q_all(
        'SELECT i.*, p.nama AS pilar_nama, b.singkatan AS bidang_singkatan
           FROM indikator i JOIN pilar p ON p.id = i.pilar_id LEFT JOIN bidang b ON b.id = i.bidang_id
          WHERE i.tahun_id = ? AND i.level = ? ORDER BY p.urutan, p.id, i.urutan, i.id',
        [$tahun['id'], $level]
    );
    $totalBobot = array_sum(array_map(fn($r) => (float)$r['bobot'], $indList));
    ?>
    <div class="page-head">
        <?= filter_bar('konfig', $tahun, $level, ['tab' => 'indikator']) ?>
        <div>
            Total Bobot: <strong class="<?= abs($totalBobot - 100) < 0.001 ? 'text-ok' : 'text-error' ?>"><?= fmt($totalBobot) ?>%</strong>
        </div>
    </div>

    <div class="grid-2">
        <div class="card">
            <h3>Daftar Indikator (<?= h(LEVELS[$level]) ?> - <?= (int)$tahun['tahun'] ?>)</h3>
            <?php if (!$indList): ?>
                <p class="muted">Belum ada indikator yang dibuat.</p>
            <?php else: ?>
                <table class="table compact">
                    <thead><tr><th>Pilar</th><th>Kode / Nama</th><th>Pengampu</th><th>Frekuensi</th><th class="num">Bobot</th><th>Aksi</th></tr></thead>
                    <tbody>
                    <?php foreach ($indList as $i): ?>
                        <tr>
                            <td class="small"><?= h($i['pilar_nama']) ?></td>
                            <td>
                                <strong><?= h($i['nama']) ?></strong>
                                <?php if ($i['kode']): ?><span class="badge badge-muted"><?= h($i['kode']) ?></span><?php endif; ?>
                            </td>
                            <td class="small"><?= h($i['bidang_singkatan'] ?? '–') ?></td>
                            <td class="small"><?= h(FREKUENSI_LABEL[$i['frekuensi']]) ?></td>
                            <td class="num"><strong><?= fmt((float)$i['bobot']) ?>%</strong></td>
                            <td>
                                <form method="post" style="display:inline" onsubmit="return confirm('Hapus indikator ini?')">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="aksi" value="hapus_indikator">
                                    <input type="hidden" name="id" value="<?= $i['id'] ?>">
                                    <button class="btn btn-sm btn-ghost text-error">Hapus</button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>

        <div class="stack">
            <div class="card">
                <h3>Tambah / Buat Pilar Baru</h3>
                <form method="post" class="stack">
                    <?= csrf_field() ?>
                    <input type="hidden" name="aksi" value="tambah_pilar">
                    <input type="hidden" name="tahun_id" value="<?= $tahun['id'] ?>">
                    <input type="hidden" name="level" value="<?= $level ?>">
                    <label>Nama Pilar (mis. "Perencanaan dan Keuangan", "Pengawasan")
                        <input name="nama" required>
                    </label>
                    <label>Urutan Tampil
                        <input type="number" name="urutan" value="1" min="1">
                    </label>
                    <button class="btn">Tambah Pilar</button>
                </form>
            </div>

            <div class="card">
                <h3>Tambah Indikator Baru</h3>
                <?php if (!$pilarList): ?>
                    <p class="muted">Silakan buat pilar terlebih dahulu di formulir atas.</p>
                <?php else: ?>
                    <form method="post" class="stack">
                        <?= csrf_field() ?>
                        <input type="hidden" name="aksi" value="simpan_indikator">
                        <input type="hidden" name="tahun_id" value="<?= $tahun['id'] ?>">
                        <input type="hidden" name="level" value="<?= $level ?>">
                        <label>Pilar
                            <select name="pilar_id" required>
                                <?php foreach ($pilarList as $p): ?>
                                    <option value="<?= $p['id'] ?>"><?= h($p['nama']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </label>
                        <div class="row-2">
                            <label>Kode Singkat
                                <input name="kode" placeholder="mis. IKPA, SAKIP">
                            </label>
                            <label>Bobot (%)
                                <input type="number" step="0.01" name="bobot" required placeholder="mis. 15">
                            </label>
                        </div>
                        <label>Nama Indikator Lengkap
                            <input name="nama" required placeholder="mis. Indikator Kinerja Pelaksanaan Anggaran">
                        </label>
                        <div class="row-2">
                            <label>Unit Pengampu
                                <select name="bidang_id">
                                    <option value="">— Tidak terikat —</option>
                                    <?php foreach ($semuaBidang as $b): ?>
                                        <option value="<?= $b['id'] ?>"><?= h($b['singkatan']) ?> — <?= h($b['nama']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </label>
                            <label>Frekuensi Pengisian
                                <select name="frekuensi">
                                    <?php foreach (FREKUENSI_LABEL as $fk => $fl): ?>
                                        <option value="<?= $fk ?>"><?= h($fl) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </label>
                        </div>
                        <label>Deskripsi / Aturan Hitung
                            <textarea name="deskripsi" rows="2"></textarea>
                        </label>
                        <button class="btn btn-primary">Simpan Indikator</button>
                    </form>
                <?php endif; ?>
            </div>
        </div>
    </div>

<?php elseif ($tab === 'predikat'): ?>
    <?php $predList = daftar_predikat((int)$tahun['id']); ?>
    <div class="grid-2">
        <div class="card">
            <h3>Daftar Predikat &amp; Ambang Nilai (Tahun <?= (int)$tahun['tahun'] ?>)</h3>
            <table class="table">
                <thead><tr><th>Predikat</th><th>Nilai Min</th><th>Jenis</th><th>Konsekuensi / Aksi</th><th>Hapus</th></tr></thead>
                <tbody>
                <?php foreach ($predList as $p): ?>
                    <tr>
                        <td><?= predikat_badge($p) ?></td>
                        <td><strong>≥ <?= fmt((float)$p['min_nilai'], 0) ?></strong></td>
                        <td><span class="badge badge-info"><?= h(JENIS_PREDIKAT[$p['jenis']] ?? $p['jenis']) ?></span></td>
                        <td class="small"><?= h($p['konsekuensi'] ?? '–') ?></td>
                        <td>
                            <form method="post" style="display:inline" onsubmit="return confirm('Hapus predikat ini?')">
                                <?= csrf_field() ?>
                                <input type="hidden" name="aksi" value="hapus_predikat">
                                <input type="hidden" name="id" value="<?= $p['id'] ?>">
                                <button class="btn btn-sm btn-ghost text-error">✕</button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <div class="card">
            <h3>Tambah / Sesuaikan Predikat</h3>
            <form method="post" class="stack">
                <?= csrf_field() ?>
                <input type="hidden" name="aksi" value="simpan_predikat">
                <input type="hidden" name="tahun_id" value="<?= $tahun['id'] ?>">
                <label>Nama Predikat
                    <input name="nama" required placeholder="mis. Sangat Baik, Baik, Kurang">
                </label>
                <div class="row-2">
                    <label>Batas Nilai Minimum
                        <input type="number" step="0.1" name="min_nilai" required placeholder="0–100">
                    </label>
                    <label>Warna Penanda
                        <input type="color" name="warna" value="#2563eb">
                    </label>
                </div>
                <label>Kategori Konsekuensi
                    <select name="jenis">
                        <?php foreach (JENIS_PREDIKAT as $jk => $jl): ?>
                            <option value="<?= $jk ?>"><?= h($jl) ?></option>
                        <?php endforeach; ?>
                    </select>
                </label>
                <label>Deskripsi Konsekuensi / Tindak Lanjut
                    <textarea name="konsekuensi" rows="3" placeholder="mis. Piagam penghargaan, pembinaan satker, penyusunan rencana aksi"></textarea>
                </label>
                <button class="btn btn-primary">Simpan Predikat</button>
            </form>
        </div>
    </div>

<?php elseif ($tab === 'entitas'): ?>
    <?php $entList = q_all('SELECT e.*, p.nama AS induk_nama FROM entitas e LEFT JOIN entitas p ON p.id = e.induk_id ORDER BY e.level, e.urutan, e.nama'); ?>
    <div class="grid-2">
        <div class="card">
            <h3>Daftar Satker &amp; Unit Organisasi</h3>
            <div class="table-wrap">
                <table class="table compact">
                    <thead><tr><th>Nama</th><th>Level</th><th>Kategori</th><th>Status</th></tr></thead>
                    <tbody>
                    <?php foreach ($entList as $e): ?>
                        <tr>
                            <td>
                                <strong><?= h($e['nama']) ?></strong>
                                <?php if ($e['induk_nama']): ?><div class="small muted">Induk: <?= h($e['induk_nama']) ?></div><?php endif; ?>
                            </td>
                            <td><span class="badge badge-info"><?= h(LEVELS[$e['level']]) ?></span></td>
                            <td class="small"><?= h(KATEGORI_ANGGARAN[$e['kategori_anggaran']] ?? $e['kategori_anggaran']) ?></td>
                            <td><?= $e['aktif'] ? '<span class="badge badge-ok">Aktif</span>' : '<span class="badge badge-muted">Nonaktif</span>' ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <div class="card">
            <h3>Tambah Satker / Unit Baru</h3>
            <form method="post" class="stack">
                <?= csrf_field() ?>
                <input type="hidden" name="aksi" value="simpan_entitas">
                <label>Nama Satuan / Unit Kerja
                    <input name="nama" required placeholder="mis. Balai Diklat Keagamaan / Puslatbang">
                </label>
                <div class="row-2">
                    <label>Level
                        <select name="level">
                            <option value="satker">Satuan Kerja (Satker DIPA)</option>
                            <option value="unit">Unit Kerja / Unor</option>
                        </select>
                    </label>
                    <label>Kategori Pengelolaan Anggaran
                        <select name="kategori_anggaran">
                            <option value="RM">Rupiah Murni (RM)</option>
                            <option value="PNBP_RM">PNBP &amp; Rupiah Murni</option>
                        </select>
                    </label>
                </div>
                <label>Induk Satker (jika level Unit Kerja)
                    <select name="induk_id">
                        <option value="">— Tidak ada / Mandiri —</option>
                        <?php foreach ($semuaSatker as $st): ?>
                            <option value="<?= $st['id'] ?>"><?= h($st['nama']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </label>
                <div class="row-2">
                    <label>Urutan Tampil
                        <input type="number" name="urutan" value="10">
                    </label>
                    <label style="align-self: center; margin-top: 18px;">
                        <input type="checkbox" name="aktif" value="1" checked> Satker/Unit Aktif
                    </label>
                </div>
                <button class="btn btn-primary">Simpan Entitas</button>
            </form>
        </div>
    </div>

<?php elseif ($tab === 'users'): ?>
    <?php
    $userList = q_all(
        'SELECT u.*, b.singkatan AS bidang_singkatan, e.nama AS entitas_nama
           FROM users u
           LEFT JOIN bidang b ON b.id = u.bidang_id
           LEFT JOIN entitas e ON e.id = u.entitas_id
          ORDER BY u.role, u.username'
    );
    $semuaEntitas = q_all('SELECT * FROM entitas WHERE aktif = 1 ORDER BY level, nama');
    ?>
    <div class="grid-2">
        <div class="card">
            <h3>Daftar Pengguna Sistem</h3>
            <div class="table-wrap">
                <table class="table compact">
                    <thead><tr><th>Username / Nama</th><th>Peran</th><th>Afiliasi</th><th>Login Terakhir</th></tr></thead>
                    <tbody>
                    <?php foreach ($userList as $usr): ?>
                        <tr>
                            <td>
                                <strong><?= h($usr['nama']) ?></strong>
                                <div class="small muted"><code><?= h($usr['username']) ?></code></div>
                            </td>
                            <td><span class="badge badge-info"><?= h(ROLES[$usr['role']] ?? $usr['role']) ?></span></td>
                            <td class="small">
                                <?= h($usr['bidang_singkatan'] ?? $usr['entitas_nama'] ?? '–') ?>
                            </td>
                            <td class="small muted"><?= h($usr['last_login'] ?? 'Belum pernah') ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <div class="card">
            <h3>Tambah Pengguna Baru</h3>
            <form method="post" class="stack">
                <?= csrf_field() ?>
                <input type="hidden" name="aksi" value="simpan_user">
                <div class="row-2">
                    <label>Username
                        <input name="username" required placeholder="mis. stafkeu">
                    </label>
                    <label>Password Awal
                        <input type="password" name="password" required placeholder="Minimal 6 karakter">
                    </label>
                </div>
                <label>Nama Lengkap &amp; Jabatan
                    <input name="nama" required placeholder="mis. Ahmad Fauzi (Staf Renkeu)">
                </label>
                <label>Peran Pengguna
                    <select name="role" required>
                        <?php foreach (ROLES as $rk => $rl): ?>
                            <option value="<?= $rk ?>"><?= h($rl) ?></option>
                        <?php endforeach; ?>
                    </select>
                </label>
                <div class="row-2">
                    <label>Unit Pengampu (jika Peran: Pengampu)
                        <select name="bidang_id">
                            <option value="">— Bukan pengampu —</option>
                            <?php foreach ($semuaBidang as $b): ?>
                                <option value="<?= $b['id'] ?>"><?= h($b['singkatan']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </label>
                    <label>Satker / Unit (jika Peran: Entitas)
                        <select name="entitas_id">
                            <option value="">— Bukan entitas —</option>
                            <?php foreach ($semuaEntitas as $en): ?>
                                <option value="<?= $en['id'] ?>"><?= h($en['nama']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </label>
                </div>
                <label style="margin-top: 8px;">
                    <input type="checkbox" name="aktif" value="1" checked> Akun Aktif
                </label>
                <button class="btn btn-primary">Simpan Pengguna</button>
            </form>
        </div>
    </div>
<?php endif; ?>

<?php render_footer();
