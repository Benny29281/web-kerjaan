<?php
function db(): PDO
{
    static $pdo;
    if (!$pdo) {
        $d = config()['db'];
        $pdo = new PDO('mysql:host=' . $d['host'] . ';port=' . (int)$d['port'] . ';dbname=' . $d['name'] . ';charset=utf8mb4', $d['user'], $d['password'], [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC, PDO::ATTR_EMULATE_PREPARES => false]);
        $pdo->exec("SET time_zone = '+07:00'");
    }
    return $pdo;
}
function query(string $sql, array $params = []): PDOStatement
{
    $stmt = db()->prepare($sql);
    foreach ($params as $key => $value) {
        $stmt->bindValue(is_int($key) ? $key + 1 : ':' . ltrim($key, ':'), $value, is_int($value) ? PDO::PARAM_INT : ($value === null ? PDO::PARAM_NULL : PDO::PARAM_STR));
    }
    $stmt->execute();
    return $stmt;
}
