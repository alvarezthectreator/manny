<?php

declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

try {
    $config = require __DIR__ . '/../config/database.php';
    $pdo = new PDO($config['dsn'], $config['username'], $config['password'], [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);
    $tables = $pdo->query("SHOW FULL TABLES WHERE Table_type = 'BASE TABLE'")->fetchAll(PDO::FETCH_COLUMN);

    echo json_encode([
        'ok' => true,
        'database' => 'cladvest_app',
        'server_version' => $pdo->getAttribute(PDO::ATTR_SERVER_VERSION),
        'table_count' => count($tables),
    ], JSON_THROW_ON_ERROR);
} catch (Throwable) {
    http_response_code(503);
    echo json_encode([
        'ok' => false,
        'error' => 'Database unavailable. Check MAMP and the local database settings.',
    ]);
}