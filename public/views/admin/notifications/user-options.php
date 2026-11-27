<?php
if (!defined('APP_ROOT')) {
    $dir = __DIR__;
    for ($i = 0; $i < 10; $i++) {
        if (file_exists($dir . '/bootstrap.php')) {
            require $dir . '/bootstrap.php';
            break;
        }
        $parent = dirname($dir);
        if ($parent === $dir) {
            break;
        }
        $dir = $parent;
    }
}

$allowedRoles = [ROLE_ADMIN];
require APP_ROOT . '/app/middleware/RoleMiddleware/RoleMiddleware.php';

use App\Services\UserService;

$options = [];
foreach ((new UserService())->all() as $user) {
    $id = trim((string) ($user['uid'] ?? $user['id'] ?? ''));
    if ($id === '') {
        continue;
    }

    $options[] = [
        'id' => $id,
        'label' => (string) ($user['name'] ?? $user['email'] ?? 'Unknown'),
        'role' => (string) ($user['role'] ?? ''),
    ];
}

json_success('User options', ['users' => $options]);
