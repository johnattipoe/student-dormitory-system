<?php

namespace App\Middleware;

use App\Services\AuthService;

if (!class_exists(AuthService::class, false)) {
    require_once __DIR__ . '/../services/AuthService.php';
}

/**
 * AuthMiddleware
 * Include at the top of any protected page:
 *   require __DIR__ . '/../../app/middleware/AuthMiddleware.php';
 * Redirects to /login.php if no active session exists.
 */
class AuthMiddleware
{
    public static function handle(): array
    {
        if (!AuthService::isLoggedIn()) {
            header('Location: /login.php?redirect=' . urlencode($_SERVER['REQUEST_URI'] ?? '/'));
            exit;
        }

        // basic idle-timeout check
        $lifetimeMinutes = require __DIR__ . '/../config/app.php';
        $maxSeconds = ($lifetimeMinutes['session_lifetime'] ?? 120) * 60;
        if (isset($_SESSION['login_time']) && (time() - $_SESSION['login_time']) > $maxSeconds) {
            (new AuthService())->logout();
            header('Location: /login.php?expired=1');
            exit;
        }

        return AuthService::currentUser();
    }
}
