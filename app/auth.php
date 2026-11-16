<?php
function is_admin(): bool { return isset($_SESSION['user_id']); }
function require_admin(): void
{
    if (!is_admin()) { redirect('login.php'); }
}
function csrf_token(): string
{
    return $_SESSION['csrf'] ??= bin2hex(random_bytes(32));
}
function csrf_field(): string { return '<input type="hidden" name="csrf" value="' . e(csrf_token()) . '">'; }
function require_post(): void
{
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') { http_response_code(405); header('Allow: POST'); exit('Metode tidak diizinkan.'); }
    if (!hash_equals(csrf_token(), (string)($_POST['csrf'] ?? ''))) { http_response_code(403); exit('Sesi formulir kedaluwarsa. Muat ulang halaman lalu coba lagi.'); }
}
function login_identity(): string { return hash('sha256', (string)($_SERVER['REMOTE_ADDR'] ?? 'local')); }
function try_login(string $employee, string $password): bool
{
    $key = login_identity();
    // Lock one persistent bucket per source IP, including across fresh sessions.
    query('INSERT IGNORE INTO login_attempts (identity_hash, attempts, window_started) VALUES (?, 0, ?)', [$key, time()]);
    db()->beginTransaction();
    try {
        $attempt = query('SELECT * FROM login_attempts WHERE identity_hash = ? FOR UPDATE', [$key])->fetch();
        if (time() - (int)$attempt['window_started'] >= 300) {
            query('UPDATE login_attempts SET attempts = 0, window_started = ? WHERE identity_hash = ?', [time(), $key]);
            $attempt['attempts'] = 0;
        }
        if ((int)$attempt['attempts'] >= 5) { db()->commit(); throw new InvalidArgumentException('Terlalu banyak percobaan. Tunggu hingga 5 menit lalu coba lagi.'); }
        $user = query('SELECT * FROM users WHERE employee_id = ?', [$employee])->fetch();
        $valid = password_verify($password, $user['password_hash'] ?? '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2uheWG/igi.');
        if (!$user || !$valid) {
            query('UPDATE login_attempts SET attempts = attempts + 1 WHERE identity_hash = ?', [$key]);
            db()->commit(); return false;
        }
        query('DELETE FROM login_attempts WHERE identity_hash = ?', [$key]);
        db()->commit();
        session_regenerate_id(true);
        $_SESSION['user_id'] = (int)$user['id'];
        $_SESSION['csrf'] = bin2hex(random_bytes(32));
        return true;
    } catch (Throwable $e) { if (db()->inTransaction()) db()->rollBack(); throw $e; }
}
