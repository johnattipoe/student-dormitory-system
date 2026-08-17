<?php

namespace App\Middleware;

use App\Services\AuthService;

/**
 * RoleMiddleware
 * Usage inside a role-specific page (after AuthMiddleware::handle()):
 *   RoleMiddleware::allow(['admin', 'house_master']);
 */
class RoleMiddleware
{
    public static function allow(array $allowedRoles): void
    {
        if (!AuthService::isLoggedIn()) {
            header('Location: /login.php?redirect=' . urlencode($_SERVER['REQUEST_URI'] ?? '/'));
            exit;
        }

        $role = AuthService::role();
        if (!in_array($role, $allowedRoles, true)) {
            http_response_code(403);
            include __DIR__ . '/../../public/views/errors/403.php';
            exit;
        }
    }

    /** Check a permission level against the permissions matrix, e.g. can('medical_records', ['full','manage']) */
    public static function can(string $module, array $requiredLevels): bool
    {
        $permissions = require __DIR__ . '/../config/permissions.php';
        $role = AuthService::role();
        $level = $permissions[$role][$module] ?? 'none';
        return in_array($level, $requiredLevels, true);
    }
}

if (!empty($GLOBALS['allowedRoles']) && is_array($GLOBALS['allowedRoles'])) {
    RoleMiddleware::allow($GLOBALS['allowedRoles']);
}
