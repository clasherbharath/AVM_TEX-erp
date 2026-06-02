<?php
/**
 * Primary database connection (PDO / MySQL).
 * Database: avm_tex
 */
declare(strict_types=1);

$dbHost = '127.0.0.1';
$dbName = 'avm_tex';
$dbUser = 'root';
$dbPass = '';
$dbCharset = 'utf8mb4';

$dsn = "mysql:host={$dbHost};dbname={$dbName};charset={$dbCharset}";

$pdoOptions = [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES => false,
];

try {
    $pdo = new PDO($dsn, $dbUser, $dbPass, $pdoOptions);
} catch (PDOException $e) {
    throw new PDOException(
        'Database connection failed: ' . $e->getMessage(),
        (int)$e->getCode(),
        $e
    );
}
