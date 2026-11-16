<?php
require __DIR__ . '/../../app/bootstrap.php';
require_admin(); require_post();
$log = find_log((int)($_POST['id'] ?? 0),true);
if (!$log) { http_response_code(404); exit('Catatan tidak ditemukan.'); }
try {
    $data = validated_log([...$log, 'status'=>$_POST['status'] ?? '', 'progress'=>$_POST['progress'] ?? '', 'blocker_note'=>$_POST['blocker_note'] ?? '']);
    save_log($data,(int)$log['id']); flash('Status dan progres berhasil diperbarui.');
} catch (InvalidArgumentException $e) { flash($e->getMessage()); }
redirect('admin/');
