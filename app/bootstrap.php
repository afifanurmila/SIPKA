<?php
declare(strict_types=1);

define('APP_ROOT', dirname(__DIR__));
define('DATA_DIR', APP_ROOT . '/data');
define('DB_PATH', DATA_DIR . '/sipka.sqlite');
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
