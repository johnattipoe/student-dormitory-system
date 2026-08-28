<?php

namespace App\Middleware;

use App\Services\AuthService;

/**
 * AdminMiddleware
 *
 * Protects pages that can only be accessed
 * by administrators.
 *
 * Usage:
 *
 * require __DIR__ . '/../../app/middleware/AdminMiddleware/AdminMiddleware.php';
 * AdminMiddleware::handle();
 */
class AdminMiddleware
{
    public static function handle(): array
    {
        // Make sure the user is logged in.
        if (!AuthService::isLoggedIn()) {

            header(
                'Location: /login.php?redirect=' .
                urlencode(
                    $_SERVER['REQUEST_URI'] ?? '/'
                )
            );

            exit;
        }

        // Check session timeout.
        $config = require __DIR__ . '/../../config/app/app.php';

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

        // Get current user.
        $user = AuthService::currentUser();

        if (!$user) {

            header(
                'Location: /login.php'
            );

            exit;
        }

        // Check administrator role.
        $role = strtolower(
            trim($user['role'] ?? '')
        );

        if ($role !== 'admin') {

            http_response_code(403);

            echo '403 - Access Denied';

            exit;
        }

        return $user;
    }
}
