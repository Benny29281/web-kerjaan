<?php
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require dirname(__DIR__) . '/app/functions.php';
require dirname(__DIR__) . '/app/db.php';
date_default_timezone_set('Asia/Jakarta');
$directory = dirname(__DIR__) . '/database/backups';
if (!is_dir($directory)) mkdir($directory,0700,true);
$filename = $directory . '/worklog-' . date('Y-m-d-His') . '.backup.sql';
$handle = fopen($filename,'xb');
if (!$handle) exit(1);
try {
    db()->exec('SET TRANSACTION ISOLATION LEVEL REPEATABLE READ');
    db()->beginTransaction();
    fwrite($handle,"-- Worklog backup. Simpan di tempat aman.\nSET NAMES utf8mb4;\nSET FOREIGN_KEY_CHECKS=0;\n");
    foreach (['users','categories','logs','log_history','login_attempts'] as $table) {
        $create = query('SHOW CREATE TABLE `' . $table . '`')->fetch(PDO::FETCH_NUM)[1];
        fwrite($handle,"DROP TABLE IF EXISTS `$table`;\n$create;\n");
        foreach (query('SELECT * FROM `' . $table . '`')->fetchAll() as $row) {
            $values = array_map(fn($v) => $v === null ? 'NULL' : db()->quote((string)$v),array_values($row));
            fwrite($handle,'INSERT INTO `' . $table . '` VALUES (' . implode(',',$values) . ");\n");
        }
    }
    fwrite($handle,"SET FOREIGN_KEY_CHECKS=1;\n");
    db()->commit(); fclose($handle);
    echo "Backup berhasil: $filename\n";
} catch (Throwable $e) { if (db()->inTransaction()) db()->rollBack(); fclose($handle); unlink($filename); fwrite(STDERR,"Backup gagal: " . $e->getMessage() . "\n"); exit(1); }
