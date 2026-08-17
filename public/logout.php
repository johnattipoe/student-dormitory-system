<?php
require __DIR__ . '/../vendor/autoload.php';
$dotenv = \Dotenv\Dotenv::createImmutable(__DIR__ . '/..');
$dotenv->safeLoad();
session_start();

require_once __DIR__ . '/../app/config/constants.php';
require_once __DIR__ . '/../app/helpers/functions.php';

use App\Controllers\AuthController;
(new AuthController())->logout();
