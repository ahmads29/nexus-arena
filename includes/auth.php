<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/functions.php';

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_set_cookie_params([
        'httponly' => true,
        'samesite' => 'Lax',
        'secure' => (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off'),
    ]);
    session_start();
}

function current_user(): ?array
{
    return $_SESSION['user'] ?? null;
}

function login_user(string $username, string $password): bool
{
    $stmt = db()->prepare('SELECT u.*, r.name role_name FROM users u JOIN roles r ON r.id = u.role_id WHERE (u.username = ? OR u.email = ?) AND u.status = "ACTIVE" LIMIT 1');
    $stmt->execute([$username, $username]);
    $user = $stmt->fetch();
    if (!$user || !password_verify($password, $user['password_hash'])) {
        return false;
    }

    session_regenerate_id(true);
    $_SESSION['user'] = [
        'id' => (int)$user['id'],
        'name' => $user['name'],
        'username' => $user['username'],
        'role_id' => (int)$user['role_id'],
        'role' => $user['role_name'],
    ];
    audit_log('LOGIN', 'users', (int)$user['id']);
    return true;
}

function require_login(): void
{
    if (!current_user()) {
        header('Location: ' . ADMIN_BASE . '/login.php');
        exit;
    }
}

function logout_user(): void
{
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'], $params['secure'], $params['httponly']);
    }
    session_destroy();
}

