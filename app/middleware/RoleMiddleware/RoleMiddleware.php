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
            $isAjax = strtolower($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') === 'xmlhttprequest'
                || str_contains(strtolower($_SERVER['HTTP_ACCEPT'] ?? ''), 'application/json');
            if ($isAjax && function_exists('json_error')) {
                json_error('Authentication required.', 401);
            }
            header('Location: /login.php?redirect=' . urlencode($_SERVER['REQUEST_URI'] ?? '/'));
            exit;
        }

        $role = AuthService::role();
        $requestedPath = parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH) ?: '';
        if ($requestedPath === '/views/admin/dashboard.php'
            && $role !== ROLE_ADMIN
            && !in_array($role, [ROLE_HOUSE_MASTER, ROLE_HOUSE_MISTRESS, ROLE_SENIOR_HOUSEPARENT, ROLE_SECURITY, ROLE_NURSE, ROLE_STUDENT], true)) {
            header('Location: ' . base_url('index.php?route=' . urlencode('/views/dashboard/dashboard.php')));
            exit;
        }
        $customRoleModule = self::moduleForRequest();
        $hasCustomPermission = $customRoleModule !== null
            && function_exists('can')
            && can($customRoleModule, 'view');
        if (!in_array($role, $allowedRoles, true) && !$hasCustomPermission) {
            $isAjax = strtolower($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') === 'xmlhttprequest'
                || str_contains(strtolower($_SERVER['HTTP_ACCEPT'] ?? ''), 'application/json');
            if ($isAjax && function_exists('json_error')) {
                json_error('Access denied.', 403);
            }
            http_response_code(403);
            include __DIR__ . '/../../../public/views/errors/403.php';
            exit;
        }
    }

    private static function moduleForRequest(): ?string
    {
        $path = str_replace('\\', '/', parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH) ?: '');
        $routes = [
            '/views/admin/users/' => 'users',
            '/views/admin/students/' => 'students',
            '/views/admin/houses/' => 'houses',
            '/views/admin/rooms/' => 'rooms',
            '/views/admin/attendance/' => 'attendance',
            '/views/admin/visitors/' => 'visitors',
            '/views/admin/incidents/' => 'incidents',
            '/views/admin/notifications/' => 'notifications',
            '/views/admin/announcements/' => 'announcements',
            '/views/admin/emergency-contacts/' => 'emergency_contacts',
            '/views/admin/activity-logs/' => 'activity_logs',
            '/views/admin/backup-restore/' => 'backup_restore',
            '/views/admin/settings/' => 'settings',
            '/views/admin/profile' => 'profile',
            '/views/parent-messages/' => 'message_parents',
            '/views/attendance/' => 'attendance',
            '/views/visitors/' => 'visitors',
            '/views/incidents/' => 'incidents',
            '/views/reports/' => 'reports',
            '/views/medical/' => 'medical_records',
        ];

        foreach ($routes as $route => $module) {
            if (str_contains($path, $route)) {
                return $module;
            }
        }

        return null;
    }

    /** Check a permission level against the permissions matrix, e.g. can('medical_records', ['full','manage']) */
    public static function can(string $module, array $requiredLevels): bool
    {
        $permissions = function_exists('permission_matrix')
            ? permission_matrix()
            : require __DIR__ . '/../../config/permissions/permissions.php';
        $role = AuthService::role();
        $level = $permissions[$role][$module] ?? 'none';
        return in_array($level, $requiredLevels, true);
    }
}

if (!empty($GLOBALS['allowedRoles']) && is_array($GLOBALS['allowedRoles'])) {
    RoleMiddleware::allow($GLOBALS['allowedRoles']);
}
