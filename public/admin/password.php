<?php
require __DIR__ . '/../../app/bootstrap.php';
require_admin();
$error='';
if ($_SERVER['REQUEST_METHOD']==='POST') {
    require_post();
    $user=query('SELECT * FROM users WHERE id=?',[(int)$_SESSION['user_id']])->fetch();
    $password=(string)($_POST['password'] ?? '');
    if (!password_verify((string)($_POST['current_password'] ?? ''),$user['password_hash'])) $error='Kata sandi saat ini tidak cocok.';
    elseif (strlen($password)<12 || strlen($password)>72) $error='Gunakan kata sandi 12–72 byte.';
    elseif ($password !== ($_POST['confirmation'] ?? '')) $error='Konfirmasi kata sandi tidak cocok.';
    else {
        query('UPDATE users SET password_hash=? WHERE id=?',[password_hash($password,PASSWORD_DEFAULT),(int)$_SESSION['user_id']]);
        session_regenerate_id(true); $_SESSION['csrf']=bin2hex(random_bytes(32));
        $accessFile=dirname(__DIR__,2) . '/storage/initial-access.txt';
        if (is_file($accessFile)) unlink($accessFile);
        flash('Kata sandi berhasil diganti. File akses awal sudah dihapus.'); redirect('admin/');
    }
}
page_start('Ubah kata sandi','admin');
?>
<div class="auth-layout"><h1>Ubah kata sandi<span class="accent">.</span></h1><form class="panel auth-form" method="post"><?= csrf_field() ?><?php if ($error): ?><div class="notice error"><?= e($error) ?></div><?php endif; ?><label>Kata sandi saat ini<input name="current_password" type="password" autocomplete="current-password" required></label><label>Kata sandi baru<input name="password" type="password" autocomplete="new-password" minlength="12" maxlength="72" required></label><label>Konfirmasi kata sandi baru<input name="confirmation" type="password" autocomplete="new-password" minlength="12" maxlength="72" required></label><button class="btn btn-primary">Simpan kata sandi</button></form></div>
<?php page_end(); ?>
