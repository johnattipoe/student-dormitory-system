<?php
/**
 * Front controller.
 * Every "page" request is funneled here as ?route=/views/xxx/yyy.php
 * so we can run global bootstrap (session, env, helpers) exactly once.
 */
require __DIR__ . '/bootstrap.php';

use App\Middleware\AuthMiddleware;

$route = $_GET['route'] ?? '/views/dashboard/dashboard.php';
$routeParts = parse_url($route);
$route = $routeParts['path'] ?? '/views/dashboard/dashboard.php';
if (!empty($routeParts['query'])) {
    parse_str($routeParts['query'], $routeQuery);
    $_GET = array_merge($routeQuery, $_GET);
}
$route = str_replace(['..'], '', $route); // basic traversal guard
if ($route !== '/' && $route !== '') {
    $route = rtrim($route, '/');
}

$defaultRoleRoutes = [
    '/views/admin' => '/views/admin/dashboard.php',
    '/views/admin/' => '/views/admin/dashboard.php',
    '/views/admin/dashboard' => '/views/admin/dashboard.php',
    '/views/admin/dashboard/' => '/views/admin/dashboard.php',
    '/views/house-master' => '/views/house-master/dashboard/index.php',
    '/views/house-master/' => '/views/house-master/dashboard/index.php',
    '/views/house-master/dashboard' => '/views/house-master/dashboard/index.php',
    '/views/house-master/dashboard/' => '/views/house-master/dashboard/index.php',
    '/views/senior-houseparent' => '/views/senior-houseparent/dashboard/index.php',
    '/views/senior-houseparent/' => '/views/senior-houseparent/dashboard/index.php',
    '/views/senior-houseparent/dashboard' => '/views/senior-houseparent/dashboard/index.php',
    '/views/senior-houseparent/dashboard/' => '/views/senior-houseparent/dashboard/index.php',
    '/views/security' => '/views/security/dashboard/dashboard.php',
    '/views/security/' => '/views/security/dashboard/dashboard.php',
    '/views/security/dashboard' => '/views/security/dashboard/dashboard.php',
    '/views/security/dashboard/' => '/views/security/dashboard/dashboard.php',
    '/views/nurse' => '/views/nurse/dashboard/dashboard.php',
    '/views/nurse/' => '/views/nurse/dashboard/dashboard.php',
    '/views/nurse/dashboard' => '/views/nurse/dashboard/dashboard.php',
    '/views/nurse/dashboard/' => '/views/nurse/dashboard/dashboard.php',
    '/views/student' => '/views/student/dashboard/index.php',
    '/views/student/' => '/views/student/dashboard/index.php',
    '/views/student/dashboard' => '/views/student/dashboard/index.php',
    '/views/student/dashboard/' => '/views/student/dashboard/index.php',
    '/views/gallery' => '/views/gallery/index.php',
    '/views/gallery/' => '/views/gallery/index.php',
    '/gallery' => '/views/gallery/index.php',
    '/gallery/' => '/views/gallery/index.php',
    '/dashboard' => '/views/dashboard/dashboard.php',
    '/dashboard/' => '/views/dashboard/dashboard.php',
];

if (isset($defaultRoleRoutes[$route])) {
    $route = $defaultRoleRoutes[$route];
}

$target = __DIR__ . $route;
if (!is_file($target)) {
    $withoutExtension = preg_replace('/\.php$/', '', $route);
    $candidates = [
        __DIR__ . $withoutExtension . '/index.php',
        __DIR__ . $withoutExtension . '.php',
        __DIR__ . $route . '/index.php',
    ];

    foreach ($candidates as $candidate) {
        if (is_file($candidate)) {
            $target = $candidate;
            break;
        }
    }
}

$publicRoutes = [
    '/views/auth/login/login.php',
    '/views/auth/forgot-password/forgot-password.php',
    '/views/auth/reset-password/reset-password.php',
];

if (!in_array($route, $publicRoutes, true)) {
    $currentUser = AuthMiddleware::handle(); // redirects to login if not authed
}

if (is_file($target)) {
    include $target;
} else {
    http_response_code(404);
    echo '404 - Page not found';
}
