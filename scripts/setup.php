<?php
// CLI only. Optional JSON credentials are read from stdin, never command arguments.
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
date_default_timezone_set('Asia/Jakarta');
$root = dirname(__DIR__);
if (is_file($root . '/app/config.php')) { fwrite(STDERR,"Konfigurasi sudah ada. Setup tidak menimpa akun atau database.\n"); exit(1); }
$input = trim(stream_get_contents(STDIN));
$options = $input === '' ? [] : json_decode($input,true,512,JSON_THROW_ON_ERROR);
$host = $options['host'] ?? '127.0.0.1';
$port = (int)($options['port'] ?? 3306);
$dbName = 'worklog';
$appUser = 'worklog_app';
$appPassword = bin2hex(random_bytes(24));
$ownerPassword = $options['owner_password'] ?? (bin2hex(random_bytes(10)) . 'Az!7');
if (strlen($ownerPassword)<12) { fwrite(STDERR,"Kata sandi pemilik minimal 12 karakter.\n"); exit(1); }
try {
    $pdo = new PDO("mysql:host=$host;port=$port;charset=utf8mb4",$options['admin_user'] ?? 'root',$options['admin_password'] ?? '',[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_EMULATE_PREPARES=>false]);
    $exists = $pdo->prepare('SELECT SCHEMA_NAME FROM INFORMATION_SCHEMA.SCHEMATA WHERE SCHEMA_NAME = ?'); $exists->execute([$dbName]);
    if ($exists->fetchColumn()) throw new RuntimeException('Database worklog sudah ada. Gunakan konfigurasi manual agar data lama tidak tersentuh.');
    $account = $pdo->prepare('SELECT COUNT(*) FROM mysql.user WHERE User = ?'); $account->execute([$appUser]);
    if ($account->fetchColumn()) throw new RuntimeException('User worklog_app sudah ada. Gunakan konfigurasi manual; setup tidak mengganti kredensial lama.');
    $pdo->prepare('CREATE DATABASE worklog CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci')->execute();
    $pdo->prepare('USE worklog')->execute();
    foreach (explode(';',file_get_contents($root . '/database/schema.sql')) as $sql) if (trim($sql)!=='') $pdo->prepare($sql)->execute();
    // Account names and schema are constants; credentials are quoted by PDO for MySQL DDL.
    $pdo->prepare("CREATE USER 'worklog_app'@'localhost' IDENTIFIED BY " . $pdo->quote($appPassword))->execute();
    $pdo->prepare("GRANT SELECT, INSERT, UPDATE, DELETE ON worklog.* TO 'worklog_app'@'localhost'")->execute();
    $stmt = $pdo->prepare('INSERT INTO users (employee_id,name,password_hash) VALUES (?,?,?)');
    $stmt->execute(['ipt2026006','Benny Dwiyanto',password_hash($ownerPassword,PASSWORD_DEFAULT)]);
    $stmt = $pdo->prepare('INSERT INTO categories (name,color) VALUES (?,?)');
    foreach ([['Pengembangan Aplikasi','#6366f1'],['Infrastruktur IT','#0f9f85'],['Dukungan & Maintenance','#dc9646']] as $cat) $stmt->execute($cat);
    $config = require $root . '/app/config.example.php';
    $config['db'] = ['host'=>$host,'port'=>$port,'name'=>$dbName,'user'=>$appUser,'password'=>$appPassword];
    // Verify actual application access before writing the configuration.
    new PDO("mysql:host=$host;port=$port;dbname=$dbName;charset=utf8mb4",$appUser,$appPassword,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
    file_put_contents($root . '/app/config.php',"<?php\nreturn " . var_export($config,true) . ";\n",LOCK_EX);
    file_put_contents($root . '/storage/initial-access.txt',"AKSES AWAL WORKLOG (hapus file setelah disimpan di pengelola kata sandi)\nID karyawan: ipt2026006\nKata sandi: " . $ownerPassword . "\n\nGanti kata sandi dari menu Ubah kata sandi setelah masuk.\n",LOCK_EX);
    echo "Setup berhasil. Akun awal disimpan di storage/initial-access.txt (diabaikan Git).\n";
    if (($options['admin_user'] ?? 'root')==='root' && ($options['admin_password'] ?? '')==='') echo "CATATAN: root MySQL masih memakai password kosong. Atur password root melalui HeidiSQL sesuai kebijakan IT.\n";
} catch (Throwable $error) { fwrite(STDERR,"Setup gagal: " . $error->getMessage() . "\n"); exit(1); }
