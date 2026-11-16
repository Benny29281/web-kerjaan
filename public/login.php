<?php
require __DIR__ . '/../app/bootstrap.php';
if (is_admin()) redirect('admin/');
$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_post();
    try {
        if (try_login(trim((string)($_POST['employee_id'] ?? '')), (string)($_POST['password'] ?? ''))) redirect('admin/');
        $error = 'ID karyawan atau kata sandi tidak cocok.';
    } catch (InvalidArgumentException $e) { $error = $e->getMessage(); }
}
page_start('Masuk pemilik');
?>
<div class="auth-layout"><div class="eyebrow">RUANG KERJA PERSONAL</div><h1>Halo, Benny<span class="accent">.</span></h1><p>Masuk untuk mencatat langkah berikutnya.</p><form class="panel auth-form" method="post"><?= csrf_field() ?><?php if ($error): ?><div class="notice error" role="alert"><?= e($error) ?></div><?php endif; ?><label>ID karyawan<input name="employee_id" required maxlength="30" autocomplete="username" value="<?= e($_POST['employee_id'] ?? config()['owner']['employee_id']) ?>"></label><label>Kata sandi<input type="password" name="password" required autocomplete="current-password"></label><button class="btn btn-primary">Masuk ke ruang kerja →</button><p class="muted">Akses pengelolaan khusus pemilik jurnal.</p></form><a class="back-link" href="<?= e(url()) ?>">← Kembali ke jurnal publik</a></div>
<?php page_end(); ?>
