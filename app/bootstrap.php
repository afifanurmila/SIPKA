<?php
declare(strict_types=1);

define('APP_ROOT', dirname(__DIR__));
define('DATA_DIR', APP_ROOT . '/data');

// Deteksi lingkungan Vercel serverless (hanya folder /tmp yang bisa ditulis)
if (getenv('VERCEL') || getenv('AWS_LAMBDA_FUNCTION_NAME')) {
    $tmpDb = sys_get_temp_dir() . '/sipka.sqlite';
    if (!file_exists($tmpDb) && file_exists(DATA_DIR . '/sipka.sqlite')) {
        copy(DATA_DIR . '/sipka.sqlite', $tmpDb);
    }
    define('DB_PATH', $tmpDb);
    ini_set('session.save_path', sys_get_temp_dir());
} else {
    define('DB_PATH', DATA_DIR . '/sipka.sqlite');
}

define('APP_NAME', 'SIPKA');
define('APP_FULLNAME', 'Sistem Informasi Penilaian Kinerja Anggaran');

date_default_timezone_set('Asia/Jakarta');
mb_internal_encoding('UTF-8');

if (PHP_SAPI !== 'cli') {
    ini_set('session.cookie_httponly', '1');
    ini_set('session.use_strict_mode', '1');
    session_name('SIPKASESSID');
    session_start();
}

require __DIR__ . '/helpers.php';
require __DIR__ . '/db.php';
require __DIR__ . '/scoring.php';
