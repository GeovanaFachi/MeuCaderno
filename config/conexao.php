<?php

$host = getenv('MEUCADERNO_DB_HOST') ?: 'localhost';
$database = getenv('MEUCADERNO_DB_NAME') ?: 'db_meu_caderno';
$user = getenv('MEUCADERNO_DB_USER') ?: 'root';
$password = getenv('MEUCADERNO_DB_PASSWORD');
$port = getenv('MEUCADERNO_DB_PORT');

if ($password === false) {
    $password = '';
}

$portDsn = '';
if ($port !== false && $port !== '') {
    $validatedPort = filter_var($port, FILTER_VALIDATE_INT, [
        'options' => ['min_range' => 1, 'max_range' => 65535],
    ]);
    if ($validatedPort === false) {
        throw new InvalidArgumentException('A porta do banco de dados é inválida.');
    }
    $portDsn = ';port=' . $validatedPort;
}

$pdo = new PDO(
    "mysql:host={$host}{$portDsn};dbname={$database};charset=utf8mb4",
    $user,
    $password,
    [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]
);

return $pdo;
