<?php
require __DIR__ . '/../../app/bootstrap.php';
require_admin();
[$logs] = filtered_logs(true,true);
header('Content-Type: text/csv; charset=UTF-8');
header('Content-Disposition: attachment; filename="rekap-kerja-' . date('Y-m-d') . '.csv"');
$out = fopen('php://output','w');
fwrite($out,"\xEF\xBB\xBF");
fputcsv($out,['Tanggal','Judul','Deskripsi','Proyek','Status','Progres (%)','Kendala','Tautan','Visibilitas']);
foreach ($logs as $log) {
    $cells = [$log['log_date'],$log['title'],$log['description'],$log['category_name'],statuses()[$log['status']],$log['progress'],$log['blocker_note'],$log['links'],$log['visibility']];
    $cells = array_map(function ($value) { $value=(string)($value ?? ''); return preg_match('/^[\s]*[=+@\-\t\r]/u',$value) ? "'" . $value : $value; },$cells);
    fputcsv($out,$cells);
}
fclose($out);
