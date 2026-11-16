<?php
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require dirname(__DIR__) . '/app/functions.php';
require dirname(__DIR__) . '/app/db.php';
require dirname(__DIR__) . '/app/auth.php';
date_default_timezone_set('Asia/Jakarta');
$_SESSION=[];
$checks=0; $userId=0; $categoryId=0;
function check(bool $condition,string $label): void { global $checks; if (!$condition) throw new RuntimeException('GAGAL: ' . $label); $checks++; echo "OK: $label\n"; }
function invalid(array $data,string $label): void { try { validated_log($data); } catch (InvalidArgumentException $error) { check(true,$label); return; } check(false,$label); }
try {
    $suffix=bin2hex(random_bytes(6));
    query('INSERT INTO users (employee_id,name,password_hash) VALUES (?,?,?)',['test-'.$suffix,'Integration Test',password_hash(bin2hex(random_bytes(20)),PASSWORD_DEFAULT)]);
    $userId=(int)db()->lastInsertId(); $_SESSION['user_id']=$userId;
    query('INSERT INTO categories (name,color) VALUES (?,?)',['test-'.$suffix,'#123456']); $categoryId=(int)db()->lastInsertId();
    $base=['title'=>'Test '.$suffix,'description'=>'Pencarian unik '.$suffix,'log_date'=>date('Y-m-d'),'status'=>'planned','progress'=>20,'blocker_note'=>'','links'=>'https://example.com/task','visibility'=>'public','category_id'=>$categoryId];
    invalid([...$base,'title'=>''],'judul wajib');
    invalid([...$base,'title'=>str_repeat('x',121)],'batas panjang judul');
    invalid([...$base,'status'=>'invalid'],'status whitelist');
    invalid([...$base,'progress'=>101],'batas progres');
    invalid([...$base,'log_date'=>'2026-02-30'],'tanggal kalender');
    invalid([...$base,'links'=>'javascript:alert(1)'],'URL berbahaya ditolak');
    invalid([...$base,'visibility'=>'invalid'],'visibilitas whitelist');
    invalid([...$base,'status'=>'blocked'],'kendala wajib');
    invalid([...$base,'category_id'=>999999999],'kategori harus ada');
    $data=validated_log($base); $id=save_log($data);
    check((bool)find_log($id),'catatan publik ditemukan');
    check((int)query('SELECT COUNT(*) FROM log_history WHERE log_id=?',[$id])->fetchColumn()===1,'riwayat awal');
    save_log(validated_log([...$base,'status'=>'done','progress'=>1]),$id);
    check((int)find_log($id)['progress']===100,'selesai otomatis 100 persen');
    check((int)query('SELECT COUNT(*) FROM log_history WHERE log_id=?',[$id])->fetchColumn()===2,'perubahan status tercatat');
    save_log(validated_log([...$base,'status'=>'in_progress','progress'=>45]),$id);
    save_log(validated_log([...$base,'status'=>'in_progress','progress'=>55]),$id);
    check((int)query('SELECT COUNT(*) FROM log_history WHERE log_id=?',[$id])->fetchColumn()===4,'perubahan progres tercatat');
    save_log(validated_log([...$base,'status'=>'blocked','blocker_note'=>'Menunggu akses','visibility'=>'private']),$id);
    check(find_log($id)===false,'detail publik menolak privat');
    check((bool)find_log($id,true),'pemilik dapat membuka privat');
    $_GET=['q'=>$suffix]; [$rows,$count]=filtered_logs(); check($count===0,'pencarian publik tidak membocorkan privat');
    [$rows,$count]=filtered_logs(true); check($count===1,'admin dapat mencari privat');
    save_log(validated_log($base),$id);
    $_GET=['q'=>$suffix,'category'=>(string)$categoryId,'status'=>'planned','from'=>date('Y-m-d'),'to'=>date('Y-m-d')];
    [$rows,$count]=filtered_logs(); check($count===1,'gabungan filter dan pencarian');
    $_GET=['q'=>"' OR 1=1 -- " . $suffix]; [$rows,$count]=filtered_logs(); check($count===0,'pencarian dengan SQL sebagai teks biasa');
    check(e('<script>"x"</script>')==='&lt;script&gt;&quot;x&quot;&lt;/script&gt;','escape HTML');
    $_SERVER['SCRIPT_NAME']='/web kerjaan/public/admin/index.php';
    check(base_path()==='/web%20kerjaan/public','path sesi aman untuk folder dengan spasi');
    query('DELETE FROM categories WHERE id=?',[$categoryId]); $categoryId=0;
    check(find_log($id)['category_id']===null,'hapus kategori mempertahankan catatan');
    query('DELETE FROM logs WHERE id=?',[$id]);
    check((int)query('SELECT COUNT(*) FROM log_history WHERE log_id=?',[$id])->fetchColumn()===0,'hapus catatan membersihkan riwayat');
    for ($i=0;$i<51;$i++) query('INSERT INTO logs (user_id,title,description,log_date,status,progress,visibility) VALUES (?,?,?,?,?,?,?)',[$userId,'Paginasi '.$suffix,'Page '.$suffix,date('Y-m-d'),'planned',0,'public']);
    $_GET=['q'=>$suffix,'page'=>1]; [$rows,$count,$page]=filtered_logs(true);
    check(count($rows)===50 && $count===51 && $page===1,'paginasi membatasi 50 catatan');
    $_GET=['q'=>$suffix,'page'=>2]; [$rows,$count,$page]=filtered_logs(true);
    check(count($rows)===1 && $page===2,'halaman kedua menampilkan sisa catatan');
    echo "LULUS: $checks pemeriksaan.\n";
} finally {
    if ($userId) query('DELETE FROM users WHERE id=?',[$userId]);
    if ($categoryId) query('DELETE FROM categories WHERE id=?',[$categoryId]);
}
