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
        $requestedPath = self::requestPath();
        if ($requestedPath === '/views/admin/dashboard.php'
            && $role !== ROLE_ADMIN
            && !in_array($role, [ROLE_HOUSE_MASTER, ROLE_HOUSE_MISTRESS, ROLE_SENIOR_HOUSEPARENT, ROLE_SECURITY, ROLE_NURSE, ROLE_STUDENT], true)) {
            header('Location: ' . base_url('index.php?route=' . urlencode('/views/dashboard/dashboard.php')));
            exit;
        }
        $customRoleModules = self::modulesForRequest();
        $hasCustomPermission = $requestedPath === '/views/dashboard/dashboard.php';
        if (function_exists('can')) {
            $requiredLevel = self::requiredPermissionLevel($requestedPath);
            foreach ($customRoleModules as $module) {
                if (can($module, $requiredLevel)) {
                    $hasCustomPermission = true;
                    break;
                }
            }
        }
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

    private static function requestPath(): string
    {
        $route = $_GET['route'] ?? '';
        $path = is_string($route) ? parse_url($route, PHP_URL_PATH) : null;
        if (!is_string($path) || $path === '') {
            $path = parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH) ?: '';
        }

        return '/' . ltrim(str_replace('\\', '/', $path), '/');
    }

    private static function modulesForRequest(): array
    {
        $path = self::requestPath();
        $routes = [
            '/views/admin/rooms/allocation/' => ['room_allocation'],
            '/views/admin/visitors/index/' => ['visitors', 'visitor_requests'],
            '/views/admin/emergency-contacts/' => ['emergency_contacts', 'emergency_alerts'],
            '/views/admin/activity-logs/' => ['activity_logs', 'audit_trail'],
            '/views/gallery/' => ['students'],
            '/views/medical/' => ['medical_records', 'health_reports'],
            '/views/admin/users/' => ['users'],
            '/views/admin/students/' => ['students'],
            '/views/admin/houses/' => ['houses'],
            '/views/admin/rooms/' => ['rooms'],
            '/views/admin/attendance/' => ['attendance'],
            '/views/admin/visitors/' => ['visitors'],
            '/views/admin/incidents/' => ['incidents'],
            '/views/admin/notifications/' => ['notifications'],
            '/views/admin/announcements/' => ['announcements'],
            '/views/admin/backup-restore/' => ['backup_restore'],
            '/views/admin/finance/' => ['finance'],
            '/views/admin/settings/' => ['settings'],
            '/views/admin/profile' => ['profile'],
            '/views/house-master/finance/' => ['finance'],
            '/views/parent-messages/' => ['message_parents'],
            '/views/attendance/' => ['attendance'],
            '/views/visitors/' => ['visitors'],
            '/views/incidents/' => ['incidents'],
            '/views/reports/' => ['reports'],
        ];

        foreach ($routes as $routePrefix => $modules) {
            if (str_starts_with($path, $routePrefix)) {
                return $modules;
            }
        }

        return [];
    }

    private static function requiredPermissionLevel(string $path): string
    {
        $isGalleryWrite = str_starts_with($path, '/views/gallery/')
            && (
                strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'GET'
                || preg_match('#/gallery/(edit|delete)/#', $path) === 1
            );

        return $isGalleryWrite ? 'manage' : 'view';
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
