<?php
/**
 * Front controller.
 * Every "page" request is funneled here as ?route=/views/xxx/yyy.php
 * so we can run global bootstrap (session, env, helpers) exactly once.
 */
require __DIR__ . '/bootstrap.php';

use App\Middleware\AuthMiddleware;

$route = $_GET['route'] ?? '/views/dashboard/dashboard.php';
$route = str_replace(['..'], '', $route); // basic traversal guard
$target = __DIR__ . $route;

$publicRoutes = ['/views/auth/login.php'];

if (!in_array($route, $publicRoutes, true)) {
    $currentUser = AuthMiddleware::handle(); // redirects to login if not authed
}

if (is_file($target)) {
    include $target;
} else {
    http_response_code(404);
    echo '404 - Page not found';
}
