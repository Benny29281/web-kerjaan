<?php
require __DIR__ . '/../../app/bootstrap.php';
require_admin(); require_post();
query('DELETE FROM logs WHERE id=? AND user_id=?', [(int)($_POST['id'] ?? 0),(int)$_SESSION['user_id']]);
flash('Catatan sudah dihapus.'); redirect('admin/');
