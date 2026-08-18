<?php
/**
 * Front controller.
 * Every "page" request is funneled here as ?route=/views/xxx/yyy.php
 * so we can run global bootstrap (session, env, helpers) exactly once.
 */
require_once __DIR__ . '/../vendor/autoload.php';

if (!class_exists('App\\Middleware\\AuthMiddleware')) {
    require_once __DIR__ . '/../app/middleware/AuthMiddleware.php';
}

$dotenv = \Dotenv\Dotenv::createImmutable(__DIR__ . '/..');
$dotenv->safeLoad();

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../app/config/constants.php';
require_once __DIR__ . '/../app/helpers/functions.php';

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
