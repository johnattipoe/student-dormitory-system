<?php
require_once __DIR__ . '/../vendor/autoload.php';

if (!class_exists('App\\Controllers\\AuthController')) {
    require_once __DIR__ . '/../app/controllers/AuthController.php';
}

$dotenv = \Dotenv\Dotenv::createImmutable(__DIR__ . '/..');
$dotenv->safeLoad();
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../app/config/constants.php';
require_once __DIR__ . '/../app/helpers/functions.php';

use App\Controllers\AuthController;

$controller = new AuthController();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $controller->login();
} else {
    $controller->showLogin();
}
