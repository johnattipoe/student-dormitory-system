<?php
require_once __DIR__ . '/../vendor/autoload.php';
$rootPath = dirname(__DIR__);
if (file_exists($rootPath . '/.env')) {
    $dotenv = \Dotenv\Dotenv::createImmutable($rootPath);
    $dotenv->safeLoad();
} elseif (file_exists($rootPath . '/.env.example')) {
    $dotenv = \Dotenv\Dotenv::createImmutable($rootPath, '.env.example');
    $dotenv->safeLoad();
}

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../app/config/constants/constants.php';
$requestPath = parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH) ?: '';
$routedPath = (string) ($_GET['route'] ?? '');
if (str_contains($requestPath, '/forgot-password') || str_contains($requestPath, '/reset-password')
    || str_contains($routedPath, '/forgot-password') || str_contains($routedPath, '/reset-password')) {
    define('SKIP_REMOTE_SETTINGS', true);
}
$appConfig = require __DIR__ . '/../app/config/app/app.php';
if (!empty($appConfig['timezone'])) {
    date_default_timezone_set((string) $appConfig['timezone']);
}
require_once __DIR__ . '/../app/helpers/functions/functions.php';

// Keep the login and admin portal available while the rest of the system is offline.
$isLoginRequest = str_ends_with($requestPath, '/login.php') || str_ends_with($requestPath, '/logout.php');
$isEntryRequest = $requestPath === '/' || str_ends_with($requestPath, '/index.php');
$isIncidentRequest = str_contains($requestPath, '/incidents/');
$isVisitorRequest = str_contains($requestPath, '/views/visitors/')
    || str_contains($requestPath, '/views/student/visitors/');
$isAdminUser = strtolower((string) ($_SESSION[AUTH_ROLE_SESSION] ?? '')) === ROLE_ADMIN;
if (!empty($appConfig['advanced']['maintenance_mode']) && !$isLoginRequest && !$isEntryRequest && !$isIncidentRequest && !$isVisitorRequest && !$isAdminUser) {
    http_response_code(503);
    header('Retry-After: 3600');
    echo '<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Maintenance</title></head><body><h1>System maintenance</h1><p>Please try again later.</p></body></html>';
    exit;
}

// Page routes need the global loading spinner; JSON endpoints must stay JSON-only.
$requestUri = $_SERVER['REQUEST_URI'] ?? '';
$acceptsJson = str_contains(strtolower($_SERVER['HTTP_ACCEPT'] ?? ''), 'application/json');
$isXmlHttpRequest = strtolower($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') === 'xmlhttprequest';
$requestMethod = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$isSettingsExport = $requestMethod === 'POST' && ($_POST['action'] ?? '') === 'export';
$isStudentTemplateDownload = $requestMethod === 'GET' && isset($_GET['download_template']);
if (!str_contains($requestUri, '/ajax/') && !str_contains($requestUri, '/reports/') && !$acceptsJson && !$isXmlHttpRequest && !$isSettingsExport && !$isStudentTemplateDownload) {
    if (ob_get_level() === 0) {
        ob_start();
    }
    include __DIR__ . '/../app/views/components/loading/loading.php';
}

// Define public root for use in views and nested includes
if (!defined('PUBLIC_ROOT')) {
    define('PUBLIC_ROOT', __DIR__);
}

// Fallback autoloader for Services and Models (handles stale Composer autoload)
spl_autoload_register(function ($class) {
    // Only handle App namespace
    if (!str_starts_with($class, 'App\\')) {
        return false;
    }
    
    // Map App\Services\ClassName -> app/services/ClassName/ClassName.php
    if (str_starts_with($class, 'App\\Services\\')) {
        $className = str_replace('App\\Services\\', '', $class);
        $path = __DIR__ . '/../app/services/' . $className . '/' . $className . '.php';
        if (file_exists($path)) {
            require_once $path;
            return true;
        }
    }
    
    // Map App\Models\ClassName -> app/models/ClassName/ClassName.php
    if (str_starts_with($class, 'App\\Models\\')) {
        $className = str_replace('App\\Models\\', '', $class);
        $path = __DIR__ . '/../app/models/' . $className . '/' . $className . '.php';
        if (file_exists($path)) {
            require_once $path;
            return true;
        }
    }
    
    // Map App\Controllers\ClassName -> app/controllers/ClassName.php
    if (str_starts_with($class, 'App\\Controllers\\')) {
        $className = str_replace('App\\Controllers\\', '', $class);
        $path = __DIR__ . '/../app/controllers/' . $className . '/' . $className . '.php';
        if (file_exists($path)) {
            require_once $path;
            return true;
        }
    }
    
    // Map App\Middleware\ClassName -> app/middleware/ClassName.php
    if (str_starts_with($class, 'App\\Middleware\\')) {
        $className = str_replace('App\\Middleware\\', '', $class);
        $path = __DIR__ . '/../app/middleware/' . $className . '/' . $className . '.php';
        if (file_exists($path)) {
            require_once $path;
            return true;
        }
    }

    // Map App\Migrations\ClassName -> app/migrations/ClassName/ClassName.php
    if (str_starts_with($class, 'App\\Migrations\\')) {
        $className = str_replace('App\\Migrations\\', '', $class);
        $path = __DIR__ . '/../app/migrations/' . $className . '/' . $className . '.php';
        if (file_exists($path)) {
            require_once $path;
            return true;
        }
    }
    
    return false;
}, true, true);

