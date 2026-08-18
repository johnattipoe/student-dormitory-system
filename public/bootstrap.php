<?php
require_once __DIR__ . '/../vendor/autoload.php';
$dotenv = \Dotenv\Dotenv::createImmutable(__DIR__ . '/..');
$dotenv->safeLoad();

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../app/config/constants.php';
$appConfig = require __DIR__ . '/../app/config/app.php';
if (!empty($appConfig['timezone'])) {
    date_default_timezone_set((string) $appConfig['timezone']);
}
require_once __DIR__ . '/../app/helpers/functions.php';

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
    
    // Map App\Services\ClassName -> app/services/ClassName.php
    if (str_starts_with($class, 'App\\Services\\')) {
        $className = str_replace('App\\Services\\', '', $class);
        $path = __DIR__ . '/../app/services/' . $className . '.php';
        if (file_exists($path)) {
            require_once $path;
            return true;
        }
    }
    
    // Map App\Models\ClassName -> app/models/ClassName.php
    if (str_starts_with($class, 'App\\Models\\')) {
        $className = str_replace('App\\Models\\', '', $class);
        $path = __DIR__ . '/../app/models/' . $className . '.php';
        if (file_exists($path)) {
            require_once $path;
            return true;
        }
    }
    
    // Map App\Controllers\ClassName -> app/controllers/ClassName.php
    if (str_starts_with($class, 'App\\Controllers\\')) {
        $className = str_replace('App\\Controllers\\', '', $class);
        $path = __DIR__ . '/../app/controllers/' . $className . '.php';
        if (file_exists($path)) {
            require_once $path;
            return true;
        }
    }
    
    // Map App\Middleware\ClassName -> app/middleware/ClassName.php
    if (str_starts_with($class, 'App\\Middleware\\')) {
        $className = str_replace('App\\Middleware\\', '', $class);
        $path = __DIR__ . '/../app/middleware/' . $className . '.php';
        if (file_exists($path)) {
            require_once $path;
            return true;
        }
    }
    
    return false;
}, true, true);

