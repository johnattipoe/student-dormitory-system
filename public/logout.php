<?php
require __DIR__ . '/bootstrap.php';

use App\Controllers\AuthController;
(new AuthController())->logout();
