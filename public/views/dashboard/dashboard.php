<?php
if (!defined('APP_ROOT')) {
    require __DIR__ . '/../../bootstrap.php';
}

// Generic dashboard router: bounces the user to their role-specific dashboard.
$role = \App\Services\AuthService::role();
$target = ROLE_DASHBOARD[$role] ?? '/views/auth/login.php';
redirect(base_url('index.php?route=' . urlencode($target)));
