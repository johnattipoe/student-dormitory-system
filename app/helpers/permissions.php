<?php
/**
 * Permission helper compatibility layer.
 * The project already exposes permission checks in app/helpers/auth.php,
 * but these helpers provide a simple wrapper for modules that expect a
 * lightweight permission lookup without coupling to the auth implementation.
 */
if (!function_exists('permission_level')) {
    function permission_level(string $module): string
    {
        $role = current_role() ?? '';
        $permissions = require __DIR__ . '/../config/permissions.php';
        return (string) ($permissions[$role][$module] ?? 'none');
    }
}

if (!function_exists('has_permission')) {
    function has_permission(string $module, string $requiredLevel): bool
    {
        $level = permission_level($module);
        $priority = [
            'none' => 0,
            'limited' => 1,
            'view' => 2,
            'own' => 3,
            'manage' => 4,
            'full' => 5,
        ];

        return ($priority[$level] ?? 0) >= ($priority[$requiredLevel] ?? 0);
    }
}
