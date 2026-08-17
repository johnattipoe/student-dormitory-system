<?php

namespace App\Middleware;

use App\Services\AuthService;

/**
 * SecurityMiddleware
 *
 * Protects the security portal.
 *
 * Security officers can manage:
 * - Visitors
 * - Visitor check-in/check-out
 * - Security incidents
 * - Security records
 *
 * Usage:
 *
 * require __DIR__ . '/../../app/middleware/SecurityMiddleware.php';
 * SecurityMiddleware::handle();
 */
class SecurityMiddleware
{
    public static function handle(): array
    {
        if (!AuthService::isLoggedIn()) {

            header(
                'Location: /login.php?redirect=' .
                urlencode(
                    $_SERVER['REQUEST_URI'] ?? '/'
                )
            );

            exit;
        }

        // Session timeout.
        $config = require __DIR__ . '/../config/app.php';

        $maxSeconds =
            ($config['session_lifetime'] ?? 120) * 60;

        if (
            isset($_SESSION['login_time']) &&
            (time() - $_SESSION['login_time']) > $maxSeconds
        ) {

            (new AuthService())->logout();

            header(
                'Location: /login.php?expired=1'
            );

            exit;
        }

        $user = AuthService::currentUser();

        if (!$user) {

            header(
                'Location: /login.php'
            );

            exit;
        }

        $role = strtolower(
            trim($user['role'] ?? '')
        );

        if ($role !== 'security') {

            http_response_code(403);

            echo '403 - Access Denied';

            exit;
        }

        return $user;
    }
}
