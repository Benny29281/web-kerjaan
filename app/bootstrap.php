<?php
declare(strict_types=1);
require_once __DIR__ . '/functions.php';
date_default_timezone_set(config()['timezone'] ?? 'Asia/Jakarta');
ini_set('display_errors', '0');
session_name('worklog_session');
session_set_cookie_params(['httponly' => true, 'samesite' => 'Lax', 'secure' => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off', 'path' => base_path() . '/']);
session_start();
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('Referrer-Policy: same-origin');
header("Content-Security-Policy: default-src 'self'; script-src 'self'; style-src 'self' 'unsafe-inline'; img-src 'self' data:; form-action 'self'; frame-ancestors 'none'; base-uri 'self'");
header('Cache-Control: no-store');
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/auth.php';
set_exception_handler(function (Throwable $error): void {
    error_log((string)$error);
    http_response_code(503);
    page_start('Koneksi belum siap');
    echo '<section class="empty panel"><div class="empty-icon">↗</div><h2>Jurnal kerja hampir siap</h2><p>Koneksi database belum tersedia. Jalankan MySQL di Laragon dan pengaturan awal melalui terminal.</p><code>php scripts/setup.php</code><p>Lihat README.md untuk panduan lengkap.</p></section>';
    page_end();
});
