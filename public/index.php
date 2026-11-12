<?php
declare(strict_types=1);

require __DIR__ . '/../app/bootstrap.php';

$routes = ['login', 'logout', 'dashboard', 'rapor', 'input', 'sanggahan', 'konfig', 'log', 'ekspor', 'akun'];
$p = $_GET['p'] ?? 'dashboard';
if (!in_array($p, $routes, true)) {
    $p = 'dashboard';
}

try {
    require APP_ROOT . '/app/pages/' . $p . '.php';
} catch (Throwable $e) {
    error_log((string)$e);
    if (!headers_sent()) {
        http_response_code(500);
    }
    echo '<div style="font-family:sans-serif;padding:24px"><h2>Terjadi kesalahan</h2><pre style="white-space:pre-wrap">'
        . h($e->getMessage()) . "\n" . h($e->getFile()) . ':' . $e->getLine() . '</pre><a href="index.php">Kembali</a></div>';
}
