<?php

$localConfigPath = __DIR__ . '/database.local.php';
$localConfig = is_file($localConfigPath) ? require $localConfigPath : [];
$readSetting = static function (string $name, mixed $default) use ($localConfig): mixed {
    $value = getenv('CLADVEST_DB_' . strtoupper($name));
    return $value !== false && $value !== '' ? $value : ($localConfig[$name] ?? $default);
};

$host = (string) $readSetting('host', '127.0.0.1');
$port = (int) $readSetting('port', 8889);
$database = (string) $readSetting('database', 'cladvest_app');
$charset = 'utf8mb4';

return [
    'dsn' => "mysql:host={$host};port={$port};dbname={$database};charset={$charset}",
    'username' => (string) $readSetting('username', ''),
    'password' => (string) $readSetting('password', ''),
];