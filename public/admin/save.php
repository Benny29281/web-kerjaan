<?php
require __DIR__ . '/../../app/bootstrap.php';
require_admin(); require_post();
$id = (int)($_POST['id'] ?? 0);
try {
    $data = validated_log($_POST);
    save_log($data, $id ?: null);
    flash($id ? 'Catatan berhasil diperbarui.' : 'Catatan baru berhasil disimpan.');
    redirect('admin/');
} catch (InvalidArgumentException $e) {
    $_SESSION['form_errors'] = [$e->getMessage()];
    $_SESSION['form_old'] = array_intersect_key($_POST, array_flip(['id','title','description','log_date','status','progress','blocker_note','links','visibility','category_id']));
    redirect('admin/form.php' . ($id ? '?id=' . $id : ''));
}
