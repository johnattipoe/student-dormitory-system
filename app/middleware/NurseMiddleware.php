<?php

namespace App\Middleware;

use App\Services\AuthService;

/**
 * NurseMiddleware
 *
 * Protects the nurse/medical portal.
 *
 * Nurse functions include:
 * - Viewing student medical records
 * - Creating medical records
 * - Updating medical records
 * - Recording health incidents
 *
 * Usage:
 *
 * require __DIR__ . '/../../app/middleware/NurseMiddleware.php';
 * NurseMiddleware::handle();
 */
class NurseMiddleware
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

        if ($role !== 'nurse') {

            http_response_code(403);

            echo '403 - Access Denied';

            exit;
        }

        return $user;
    }
}
