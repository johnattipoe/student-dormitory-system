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
