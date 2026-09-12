<?php
declare(strict_types=1);

function user_can(string $permission): bool
{
    $user = current_user();
    if (!$user) {
        return false;
    }
    if (($user['role'] ?? '') === 'Admin') {
        return true;
    }
    $stmt = db()->prepare(
        'SELECT 1 FROM role_permissions rp JOIN permissions p ON p.id = rp.permission_id WHERE rp.role_id = ? AND p.name = ? LIMIT 1'
    );
    $stmt->execute([$user['role_id'], $permission]);
    return (bool)$stmt->fetchColumn();
}

function require_permission(string $permission): void
{
    if (!user_can($permission)) {
        http_response_code(403);
        echo 'Forbidden';
        exit;
    }
}

