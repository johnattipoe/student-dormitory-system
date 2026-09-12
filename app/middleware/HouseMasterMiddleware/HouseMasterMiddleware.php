<?php

namespace App\Middleware;

use App\Services\AuthService;

/**
 * HouseMasterMiddleware
 *
 * Protects pages used by the House Master.
 *
 * Usage:
 *
 * require __DIR__ . '/../../app/middleware/HouseMasterMiddleware/HouseMasterMiddleware.php';
 * HouseMasterMiddleware::handle();
 */
class HouseMasterMiddleware
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

        if ($role !== 'house_master') {

            http_response_code(403);

            echo '403 - Access Denied';

            exit;
        }

        return $user;
    }
}