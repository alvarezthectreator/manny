<?php

declare(strict_types=1);

ini_set('session.use_strict_mode', '1');
ini_set('session.gc_maxlifetime', '43200');
$requestPath = parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH) ?: '';
$adminContext = ($_GET['context'] ?? '') === 'admin' || str_contains($requestPath, '/api/admin/');
session_name($adminContext ? 'cladvest_admin_session' : 'cladvest_session');
session_set_cookie_params([
    'lifetime' => 0,
    'path' => '/',
    'secure' => isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
    'httponly' => true,
    'samesite' => 'Lax',
]);
session_start();
header('Cache-Control: no-store');
header('X-Content-Type-Options: nosniff');

function auth_database(): PDO
{
    static $pdo;
    if ($pdo instanceof PDO) {
        return $pdo;
    }

    $config = require __DIR__ . '/../../config/database.php';
    $pdo = new PDO($config['dsn'], $config['username'], $config['password'], [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);

    return $pdo;
}

function auth_respond(array $payload, int $status = 200): never
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE);
    exit;
}

function auth_require_method(string $method): void
{
    if (($_SERVER['REQUEST_METHOD'] ?? '') !== $method) {
        header('Allow: ' . $method);
        auth_respond(['ok' => false, 'message' => 'Method not allowed.'], 405);
    }
}

function auth_request_data(): array
{
    $data = json_decode(file_get_contents('php://input') ?: '', true);
    if (!is_array($data)) {
        auth_respond(['ok' => false, 'message' => 'Invalid request body.'], 400);
    }

    return $data;
}

function auth_csrf_token(): string
{
    if (!isset($_SESSION['csrf_token']) || !is_string($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }

    return $_SESSION['csrf_token'];
}

function auth_require_csrf(array $data): void
{
    $provided = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? ($data['csrf_token'] ?? '');
    $expected = $_SESSION['csrf_token'] ?? '';
    if (!is_string($provided) || !is_string($expected) || $expected === '' || !hash_equals($expected, $provided)) {
        auth_respond(['ok' => false, 'message' => 'Your session expired. Refresh the page and try again.'], 419);
    }
}

function auth_client_ip(): ?string
{
    $address = $_SERVER['REMOTE_ADDR'] ?? '';
    return filter_var($address, FILTER_VALIDATE_IP) ? $address : null;
}

function auth_record_security_event(PDO $pdo, ?int $userId, string $eventType, string $outcome): void
{
    $statement = $pdo->prepare(
        'INSERT INTO security_events (user_id, event_type, outcome, ip_address, user_agent) '
        . 'VALUES (:user_id, :event_type, :outcome, INET6_ATON(:ip_address), :user_agent)'
    );
    $statement->execute([
        'user_id' => $userId,
        'event_type' => $eventType,
        'outcome' => $outcome,
        'ip_address' => auth_client_ip(),
        'user_agent' => substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 512),
    ]);
}

function auth_establish_session(PDO $pdo, int $userId): void
{
    session_regenerate_id(true);
    $_SESSION = [];
    $sessionHash = hash('sha256', session_id());
    $statement = $pdo->prepare(
        'INSERT INTO user_sessions (user_id, token_hash, ip_address, user_agent, expires_at) '
        . 'VALUES (:user_id, :token_hash, INET6_ATON(:ip_address), :user_agent, DATE_ADD(UTC_TIMESTAMP(6), INTERVAL 12 HOUR))'
    );
    $statement->execute([
        'user_id' => $userId,
        'token_hash' => $sessionHash,
        'ip_address' => auth_client_ip(),
        'user_agent' => substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 512),
    ]);
    $_SESSION['user_id'] = $userId;
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

function auth_current_user(PDO $pdo): ?array
{
    $userId = filter_var($_SESSION['user_id'] ?? null, FILTER_VALIDATE_INT);
    if (!$userId) {
        return null;
    }

    $statement = $pdo->prepare(
        'SELECT u.id, u.email, p.first_name, p.last_name, p.country_code, '
        . 'COALESCE(pref.email_updates, 1) AS email_updates '
        . 'FROM users u '
        . 'JOIN user_sessions s ON s.user_id = u.id '
        . 'LEFT JOIN user_profiles p ON p.user_id = u.id '
        . 'LEFT JOIN user_preferences pref ON pref.user_id = u.id '
        . 'WHERE u.id = :user_id AND u.status = \'active\' '
        . 'AND s.token_hash = :token_hash AND s.revoked_at IS NULL AND s.expires_at > UTC_TIMESTAMP(6) '
        . 'LIMIT 1'
    );
    $statement->execute([
        'user_id' => $userId,
        'token_hash' => hash('sha256', session_id()),
    ]);
    $user = $statement->fetch();
    if (!$user) {
        $_SESSION = [];
        return null;
    }

    $touch = $pdo->prepare('UPDATE user_sessions SET last_seen_at = UTC_TIMESTAMP(6) WHERE token_hash = :token_hash');
    $touch->execute(['token_hash' => hash('sha256', session_id())]);

    return $user;
}

function auth_require_user(PDO $pdo): array
{
    $user = auth_current_user($pdo);
    if (!$user) {
        auth_respond(['ok' => false, 'message' => 'Sign-in required.'], 401);
    }

    return $user;
}