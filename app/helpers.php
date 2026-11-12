<?php
declare(strict_types=1);

// ---------------------------------------------------------------------------
// Konstanta domain
// ---------------------------------------------------------------------------
const LEVELS = ['satker' => 'Satuan Kerja', 'unit' => 'Unit Kerja / Unor'];
const ROLES = [
    'admin'    => 'Super Admin (Biro Perencanaan dan Keuangan)',
    'pengampu' => 'Pengampu Indikator',
    'entitas'  => 'Satker / Unit Kerja',
    'pimpinan' => 'Pimpinan',
];
const FREKUENSI = ['tahunan' => 1, 'semester' => 2, 'triwulan' => 4, 'bulanan' => 12];
const FREKUENSI_LABEL = ['tahunan' => 'Tahunan', 'semester' => 'Semesteran', 'triwulan' => 'Triwulanan', 'bulanan' => 'Bulanan'];
const STATUS_TAHUN = [
    'pengisian'  => 'Pengisian Nilai',
    'verifikasi' => 'Verifikasi & Sanggahan',
    'final'      => 'Final (Terkunci)',
];
const KATEGORI_ANGGARAN = ['RM' => 'Rupiah Murni', 'PNBP_RM' => 'PNBP & Rupiah Murni'];
const JENIS_PREDIKAT = ['reward' => 'Reward', 'pembinaan' => 'Pembinaan', 'punishment' => 'Punishment'];
const STATUS_SANGGAHAN = ['diajukan' => 'Diajukan', 'diterima' => 'Diterima', 'ditolak' => 'Ditolak'];

// ---------------------------------------------------------------------------
// Utilitas umum
// ---------------------------------------------------------------------------
function h(mixed $v): string
{
    return htmlspecialchars((string)($v ?? ''), ENT_QUOTES, 'UTF-8');
}

function fmt(?float $n, int $d = 2): string
{
    return $n === null ? '–' : number_format($n, $d, ',', '.');
}

/** Ubah input teks ("46,16" / "46.16") menjadi float 0–100. String kosong = null. */
function parse_nilai(mixed $s): ?float
{
    $s = str_replace(' ', '', trim((string)$s));
    if ($s === '') {
        return null;
    }
    if (str_contains($s, ',') && str_contains($s, '.')) {
        $s = str_replace('.', '', $s);
    }
    $s = str_replace(',', '.', $s);
    if (!is_numeric($s)) {
        throw new InvalidArgumentException("'$s' bukan angka");
    }
    $f = (float)$s;
    if ($f < 0 || $f > 100) {
        throw new InvalidArgumentException("nilai $f di luar rentang 0–100");
    }
    return round($f, 4);
}

function sub_periode_labels(string $frek): array
{
    return match ($frek) {
        'semester' => [1 => 'Smt I', 2 => 'Smt II'],
        'triwulan' => [1 => 'TW I', 2 => 'TW II', 3 => 'TW III', 4 => 'TW IV'],
        'bulanan'  => [1 => 'Jan', 2 => 'Feb', 3 => 'Mar', 4 => 'Apr', 5 => 'Mei', 6 => 'Jun',
                       7 => 'Jul', 8 => 'Agu', 9 => 'Sep', 10 => 'Okt', 11 => 'Nov', 12 => 'Des'],
        default    => [1 => 'Nilai'],
    };
}

function url(string $page, array $params = []): string
{
    return 'index.php?' . http_build_query(array_merge(['p' => $page], $params));
}

function redirect(string $to): never
{
    header('Location: ' . $to);
    exit;
}

function is_post(): bool
{
    return ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST';
}

function flash(string $type, string $msg): void
{
    $_SESSION['flash'][] = [$type, $msg];
}

function take_flashes(): array
{
    $f = $_SESSION['flash'] ?? [];
    unset($_SESSION['flash']);
    return $f;
}

function csrf_token(): string
{
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(16));
    }
    return $_SESSION['csrf'];
}

function csrf_field(): string
{
    return '<input type="hidden" name="_csrf" value="' . h(csrf_token()) . '">';
}

function csrf_check(): void
{
    if (!hash_equals($_SESSION['csrf'] ?? '', (string)($_POST['_csrf'] ?? ''))) {
        http_response_code(419);
        exit('Sesi formulir kedaluwarsa. Silakan kembali dan muat ulang halaman.');
    }
}

// ---------------------------------------------------------------------------
// Autentikasi & otorisasi
// ---------------------------------------------------------------------------
function current_user(bool $refresh = false): ?array
{
    static $u = false;
    if ($u === false || $refresh) {
        $u = null;
        if (!empty($_SESSION['uid'])) {
            $u = q_one(
                'SELECT u.*, b.nama AS bidang_nama, b.singkatan AS bidang_singkatan,
                        e.nama AS entitas_nama, e.level AS entitas_level
                   FROM users u
                   LEFT JOIN bidang b ON b.id = u.bidang_id
                   LEFT JOIN entitas e ON e.id = u.entitas_id
                  WHERE u.id = ? AND u.aktif = 1',
                [$_SESSION['uid']]
            );
        }
    }
    return $u;
}

function require_login(): array
{
    $u = current_user();
    if (!$u) {
        redirect(url('login'));
    }
    return $u;
}

function require_role(string ...$roles): array
{
    $u = require_login();
    if (!in_array($u['role'], $roles, true)) {
        http_response_code(403);
        render_error('Akses ditolak', 'Anda tidak memiliki hak akses ke halaman ini.');
    }
    return $u;
}

function has_role(string ...$roles): bool
{
    $u = current_user();
    return $u !== null && in_array($u['role'], $roles, true);
}

/** Apakah user boleh mengubah nilai indikator ini pada tahun tsb. */
function can_edit_nilai(array $u, array $tahun, array $ind): bool
{
    if ($tahun['status'] === 'final') {
        return false;
    }
    if ($u['role'] === 'admin') {
        return true;
    }
    if ($u['role'] === 'pengampu') {
        return $tahun['status'] === 'pengisian' && (int)$ind['bidang_id'] === (int)$u['bidang_id'];
    }
    return false;
}

function log_act(string $aksi, string $detail = ''): void
{
    $u = current_user();
    q('INSERT INTO log_aktivitas (user_id, aksi, detail, waktu) VALUES (?, ?, ?, ?)',
        [$u['id'] ?? null, $aksi, $detail, date('Y-m-d H:i:s')]);
}

// ---------------------------------------------------------------------------
// Pilihan tahun & level (disimpan di sesi)
// ---------------------------------------------------------------------------
function semua_tahun(): array
{
    return q_all('SELECT * FROM tahun ORDER BY tahun DESC');
}

function pilih_tahun(): ?array
{
    if (isset($_GET['t'])) {
        $_SESSION['t'] = (int)$_GET['t'];
    }
    $t = !empty($_SESSION['t']) ? q_one('SELECT * FROM tahun WHERE id = ?', [$_SESSION['t']]) : null;
    if (!$t) {
        // default: tahun terbaru yang sudah memiliki nilai, jika tidak ada ambil tahun terbaru
        $t = q_one('SELECT t.* FROM tahun t WHERE EXISTS (SELECT 1 FROM nilai n JOIN indikator i ON i.id = n.indikator_id WHERE i.tahun_id = t.id) ORDER BY t.tahun DESC LIMIT 1')
            ?? q_one('SELECT * FROM tahun ORDER BY tahun DESC LIMIT 1');
        if ($t) {
            $_SESSION['t'] = (int)$t['id'];
        }
    }
    return $t;
}

function pilih_level(): string
{
    if (isset($_GET['lvl']) && isset(LEVELS[$_GET['lvl']])) {
        $_SESSION['lvl'] = $_GET['lvl'];
    }
    return $_SESSION['lvl'] ?? 'satker';
}

/** Form pemilih tahun & level (GET, auto-submit). */
function filter_bar(string $page, array $tahun, ?string $level, array $extra = []): string
{
    $html = '<form method="get" class="filter-bar"><input type="hidden" name="p" value="' . h($page) . '">';
    foreach ($extra as $k => $v) {
        $html .= '<input type="hidden" name="' . h($k) . '" value="' . h($v) . '">';
    }
    $html .= '<label>Tahun <select name="t" data-autosubmit>';
    foreach (semua_tahun() as $t) {
        $sel = (int)$t['id'] === (int)$tahun['id'] ? ' selected' : '';
        $html .= '<option value="' . (int)$t['id'] . '"' . $sel . '>' . (int)$t['tahun'] . ' — ' . h(STATUS_TAHUN[$t['status']]) . '</option>';
    }
    $html .= '</select></label>';
    if ($level !== null) {
        $html .= '<label>Level <select name="lvl" data-autosubmit>';
        foreach (LEVELS as $k => $v) {
            $html .= '<option value="' . $k . '"' . ($k === $level ? ' selected' : '') . '>' . h($v) . '</option>';
        }
        $html .= '</select></label>';
    }
    $html .= '<noscript><button class="btn btn-sm">Terapkan</button></noscript></form>';
    return $html;
}

function status_badge(string $status): string
{
    $cls = ['pengisian' => 'info', 'verifikasi' => 'warn', 'final' => 'ok'][$status] ?? 'muted';
    return '<span class="badge badge-' . $cls . '">' . h(STATUS_TAHUN[$status] ?? $status) . '</span>';
}

function predikat_badge(?array $p): string
{
    if (!$p) {
        return '<span class="badge badge-muted">Belum dinilai</span>';
    }
    return '<span class="badge" style="background:' . h($p['warna']) . '1f;color:' . h($p['warna']) . ';border-color:' . h($p['warna']) . '55">' . h($p['nama']) . '</span>';
}

// ---------------------------------------------------------------------------
// Data bersama
// ---------------------------------------------------------------------------
/** Entitas yang dinilai pada tahun & level tsb: yang aktif, atau yang sudah punya data di tahun itu. */
function entitas_peserta(int $tahunId, string $level): array
{
    return q_all(
        'SELECT e.*, p.nama AS induk_nama FROM entitas e LEFT JOIN entitas p ON p.id = e.induk_id
          WHERE e.level = ?
            AND (e.aktif = 1
                 OR e.id IN (SELECT n.entitas_id FROM nilai n JOIN indikator i ON i.id = n.indikator_id WHERE i.tahun_id = ?)
                 OR e.id IN (SELECT s.entitas_id FROM isian s JOIN indikator i ON i.id = s.indikator_id WHERE i.tahun_id = ?))
          ORDER BY e.urutan, e.nama',
        [$level, $tahunId, $tahunId]
    );
}

function daftar_predikat(int $tahunId): array
{
    return q_all('SELECT * FROM predikat WHERE tahun_id = ? ORDER BY min_nilai DESC', [$tahunId]);
}

function buat_predikat_default(int $tahunId): void
{
    $default = [
        ['Sangat Baik', 85, '#15803d', 'reward', 'Reward utama: piagam penghargaan pimpinan dan prioritas insentif / tambahan alokasi anggaran'],
        ['Baik', 75, '#2563eb', 'reward', 'Reward: piagam penghargaan dan publikasi praktik baik'],
        ['Cukup', 65, '#d97706', 'pembinaan', 'Pembinaan: wajib menyusun rencana aksi perbaikan untuk indikator bernilai rendah'],
        ['Kurang', 0, '#dc2626', 'punishment', 'Punishment: pendampingan khusus, pemanggilan oleh pimpinan, dan evaluasi alokasi anggaran'],
    ];
    foreach ($default as [$nama, $min, $warna, $jenis, $konsekuensi]) {
        q('INSERT INTO predikat (tahun_id, nama, min_nilai, warna, jenis, konsekuensi) VALUES (?, ?, ?, ?, ?, ?)',
            [$tahunId, $nama, $min, $warna, $jenis, $konsekuensi]);
    }
}

/** Salin pilar, indikator, dan predikat dari satu tahun ke tahun lain (tanpa nilai). */
function salin_konfigurasi(int $dariTahunId, int $keTahunId): void
{
    $mapPilar = [];
    foreach (q_all('SELECT * FROM pilar WHERE tahun_id = ? ORDER BY id', [$dariTahunId]) as $p) {
        q('INSERT INTO pilar (tahun_id, level, nama, urutan) VALUES (?, ?, ?, ?)', [$keTahunId, $p['level'], $p['nama'], $p['urutan']]);
        $mapPilar[$p['id']] = last_id();
    }
    foreach (q_all('SELECT * FROM indikator WHERE tahun_id = ? ORDER BY id', [$dariTahunId]) as $i) {
        q('INSERT INTO indikator (tahun_id, level, pilar_id, kode, nama, bobot, bidang_id, frekuensi, deskripsi, urutan)
           VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
            [$keTahunId, $i['level'], $mapPilar[$i['pilar_id']], $i['kode'], $i['nama'], $i['bobot'],
             $i['bidang_id'], $i['frekuensi'], $i['deskripsi'], $i['urutan']]);
    }
    $predikat = q_all('SELECT * FROM predikat WHERE tahun_id = ?', [$dariTahunId]);
    if (!$predikat) {
        buat_predikat_default($keTahunId);
    }
    foreach ($predikat as $p) {
        q('INSERT INTO predikat (tahun_id, nama, min_nilai, warna, jenis, konsekuensi) VALUES (?, ?, ?, ?, ?, ?)',
            [$keTahunId, $p['nama'], $p['min_nilai'], $p['warna'], $p['jenis'], $p['konsekuensi']]);
    }
}

// ---------------------------------------------------------------------------
// Render
// ---------------------------------------------------------------------------
function render_header(string $title, string $active = ''): void
{
    $GLOBALS['__title'] = $title;
    $GLOBALS['__active'] = $active;
    require APP_ROOT . '/app/views/header.php';
}

function render_footer(): void
{
    require APP_ROOT . '/app/views/footer.php';
}

function render_error(string $title, string $msg): never
{
    render_header($title);
    echo '<div class="card"><h2>' . h($title) . '</h2><p>' . h($msg) . '</p>'
        . '<a class="btn" href="' . h(url('dashboard')) . '">Kembali ke Dashboard</a></div>';
    render_footer();
    exit;
}

function chart_attr(array $config): string
{
    return h(json_encode($config, JSON_UNESCAPED_UNICODE));
}
