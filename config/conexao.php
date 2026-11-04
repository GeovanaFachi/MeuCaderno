<?php

$host = getenv('MEUCADERNO_DB_HOST') ?: 'localhost';
$database = getenv('MEUCADERNO_DB_NAME') ?: 'db_meu_caderno';
$user = getenv('MEUCADERNO_DB_USER') ?: 'root';
$password = getenv('MEUCADERNO_DB_PASSWORD');

if ($password === false) {
    $password = '';
}

$pdo = new PDO(
    "mysql:host={$host};dbname={$database};charset=utf8mb4",
    $user,
    $password,
    [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]
);

return $pdo;
