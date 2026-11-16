<?php
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require dirname(__DIR__) . '/app/functions.php';
require dirname(__DIR__) . '/app/db.php';
$base=$argv[1] ?? 'http://127.0.0.1/web%20kerjaan/public/';
if (!in_array(parse_url($base,PHP_URL_HOST),['localhost','127.0.0.1'],true)) exit("Hanya untuk server lokal.\n");
$jar=tempnam(sys_get_temp_dir(),'worklog-test-');
$checks=0; $userId=0; $categoryId=0; $suffix=bin2hex(random_bytes(6));
$password=bin2hex(random_bytes(16));
function expect(bool $ok,string $message): void { global $checks; if (!$ok) throw new RuntimeException('GAGAL: '.$message); echo 'OK: '.$message."\n"; $checks++; }
function request(string $path,?array $data=null,bool $session=true): array {
    global $base,$jar;
    $ch=curl_init($base.$path);
    $options=[CURLOPT_RETURNTRANSFER=>true,CURLOPT_FOLLOWLOCATION=>false,CURLOPT_TIMEOUT=>15];
    if ($session) { $options[CURLOPT_COOKIEFILE]=$jar; $options[CURLOPT_COOKIEJAR]=$jar; }
    if ($data!==null) { $options[CURLOPT_POST]=true; $options[CURLOPT_POSTFIELDS]=http_build_query($data); }
    curl_setopt_array($ch,$options); $body=curl_exec($ch);
    if ($body===false) throw new RuntimeException(curl_error($ch));
    $code=curl_getinfo($ch,CURLINFO_HTTP_CODE); curl_close($ch);
    return [$code,$body];
}
function token(string $body): string { if (!preg_match('/name="csrf" value="([a-f0-9]+)"/',$body,$match)) throw new RuntimeException('Token CSRF tidak ditemukan.'); return $match[1]; }
$loginBucket=hash('sha256', parse_url($base,PHP_URL_HOST)==='localhost' ? '::1' : '127.0.0.1');
$oldAttempt=query('SELECT * FROM login_attempts WHERE identity_hash=?',[$loginBucket])->fetch();
try {
    query('INSERT INTO users (employee_id,name,password_hash) VALUES (?,?,?)',['http-'.$suffix,'HTTP Test',password_hash($password,PASSWORD_DEFAULT)]); $userId=(int)db()->lastInsertId();
    [$code,$html]=request(''); expect($code===200 && str_contains($html,'Jurnal kerja'),'dashboard publik');
    [$code]=request('admin/',null,false); expect($code===303,'admin wajib login');
    [$code,$html]=request('login.php'); $csrf=token($html);
    [$code]=request('login.php',['employee_id'=>'http-'.$suffix,'password'=>$password]); expect($code===403,'login menolak POST tanpa CSRF');
    [$code,$html]=request('login.php',['csrf'=>$csrf,'employee_id'=>'http-'.$suffix,'password'=>$password]); expect($code===303,'login berhasil (HTTP '.$code.', '.strip_tags(mb_substr($html,0,200)).')');
    [$code,$html]=request('admin/form.php'); expect($code===200,'form admin'); $csrf=token($html);
    [$code]=request('admin/save.php'); expect($code===405,'GET tidak mengubah data');
    [$code]=request('admin/save.php',['title'=>'CSRF test']); expect($code===403,'simpan menolak CSRF kosong');
    $data=['csrf'=>$csrf,'title'=>'HTTP '.$suffix.' <script>alert(1)</script>','description'=>'Deskripsi '.$suffix,'log_date'=>date('Y-m-d'),'status'=>'in_progress','progress'=>40,'blocker_note'=>'','links'=>'https://example.com','visibility'=>'private','category_id'=>''];
    [$code]=request('admin/save.php',$data); expect($code===303,'tambah catatan melalui HTTP');
    $log=query('SELECT * FROM logs WHERE user_id=?',[$userId])->fetch(); $id=(int)$log['id'];
    [$code]=request('log.php?id='.$id,null,false); expect($code===404,'detail privat menolak pengunjung');
    [$code,$html]=request('?q='.$suffix,null,false); expect(!str_contains($html,'HTTP '.$suffix),'pencarian tidak membocorkan privat');
    [$code,$html]=request('log.php?id='.$id); expect($code===200 && str_contains($html,'&lt;script&gt;'),'detail admin meng-escape HTML');
    [$code]=request('admin/quick_status.php',['csrf'=>$csrf,'id'=>$id,'status'=>'done','progress'=>5,'blocker_note'=>'']); expect($code===303,'ubah status cepat');
    $log=query('SELECT * FROM logs WHERE id=?',[$id])->fetch(); expect($log['status']==='done' && (int)$log['progress']===100,'status cepat memaksa 100 persen');
    request('admin/quick_status.php',['csrf'=>$csrf,'id'=>$id,'status'=>'blocked','progress'=>5,'blocker_note'=>'']);
    expect(query('SELECT status FROM logs WHERE id=?',[$id])->fetchColumn()==='done','kendala kosong ditolak');
    [$code]=request('admin/save.php',[...$data,'id'=>$id,'visibility'=>'public','status'=>'done','progress'=>50]); expect($code===303,'edit catatan');
    [$code,$html]=request('log.php?id='.$id,null,false); expect($code===200 && str_contains($html,'&lt;script&gt;'),'detail publik dengan HTML aman');
    [$code,$csv]=request('admin/export.php?q='.$suffix); expect($code===200 && str_contains($csv,'HTTP '.$suffix),'CSV sesuai filter');
    request('admin/categories.php',['csrf'=>$csrf,'name'=>'HTTP-'.$suffix,'color'=>'#aabbcc']);
    $categoryId=(int)query('SELECT id FROM categories WHERE name=?',['HTTP-'.$suffix])->fetchColumn(); expect($categoryId>0,'tambah kategori');
    request('admin/categories.php',['csrf'=>$csrf,'id'=>$categoryId,'name'=>'HTTP-edited-'.$suffix,'color'=>'#bbccdd']);
    expect(query('SELECT name FROM categories WHERE id=?',[$categoryId])->fetchColumn()==='HTTP-edited-'.$suffix,'edit kategori');
    request('admin/categories.php',['csrf'=>$csrf,'id'=>$categoryId,'action'=>'delete']); expect(!query('SELECT id FROM categories WHERE id=?',[$categoryId])->fetch(),'hapus kategori'); $categoryId=0;
    [$code]=request('admin/delete.php',['csrf'=>$csrf,'id'=>$id]); expect($code===303 && !query('SELECT id FROM logs WHERE id=?',[$id])->fetch(),'hapus catatan melalui HTTP');
    [$code]=request('logout.php',['csrf'=>$csrf]); expect($code===303,'logout melalui POST');
    [$code]=request('admin/'); expect($code===303,'sesi tidak lagi mengakses admin');
    foreach (['../app/config.php','../storage/initial-access.txt','../database/schema.sql','../.git/config','../scripts/setup.php','../prd%20(6).md'] as $path) {
        [$code]=request($path,null,false); expect(in_array($code,[403,404],true),'file internal dilindungi: '.basename($path));
    }
    // Persistent throttle must survive a fresh session after five failures.
    query('DELETE FROM login_attempts WHERE identity_hash=?',[$loginBucket]);
    for ($i=0;$i<5;$i++) {
        [$code,$html]=request('login.php');
        request('login.php',['csrf'=>token($html),'employee_id'=>'http-'.$suffix,'password'=>'wrong']);
    }
    if (is_file($jar)) unlink($jar);
    $jar=tempnam(sys_get_temp_dir(),'worklog-test-');
    [$code,$html]=request('login.php');
    [$code,$html]=request('login.php',['csrf'=>token($html),'employee_id'=>'http-'.$suffix,'password'=>$password]);
    expect($code===200 && str_contains($html,'Terlalu banyak percobaan'),'lima kegagalan memblokir login benar');
    echo "LULUS: $checks pemeriksaan HTTP.\n";
} finally {
    if ($userId) query('DELETE FROM users WHERE id=?',[$userId]);
    if ($categoryId) query('DELETE FROM categories WHERE id=?',[$categoryId]);
    query('DELETE FROM login_attempts WHERE identity_hash=?',[$loginBucket]);
    if ($oldAttempt) query('INSERT INTO login_attempts (identity_hash,attempts,window_started) VALUES (?,?,?)',[$oldAttempt['identity_hash'],(int)$oldAttempt['attempts'],(int)$oldAttempt['window_started']]);
    if (is_file($jar)) unlink($jar);
}
