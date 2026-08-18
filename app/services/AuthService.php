<?php

namespace App\Services;

if (!class_exists(\App\Services\ActivityLogService::class, false)) {
    require_once __DIR__ . '/ActivityLogService.php';
}

if (!class_exists(\App\Services\FirebaseAuthService::class, false)) {
    require_once __DIR__ . '/FirebaseAuthService.php';
}

if (!class_exists(\App\Services\FirebaseService::class, false)) {
    require_once __DIR__ . '/FirebaseService.php';
}

/**
 * High-level login/logout flow: verifies credentials against Firebase Auth,
 * loads the matching Firestore user profile, and stores both in the session.
 */
class AuthService
{
    /** @return array{success:bool, message:string, user?:array} */
    public static function login(string $email, string $password): array
    {
        $defaultAdminEmail = $_ENV['DEFAULT_ADMIN_EMAIL'] ?? '';
        $defaultAdminPassword = $_ENV['DEFAULT_ADMIN_PASSWORD'] ?? '';

        if (
            $defaultAdminEmail &&
            $defaultAdminPassword &&
            $email === $defaultAdminEmail &&
            $password === $defaultAdminPassword
        ) {
            $user = [
                'uid' => 'default-admin',
                'name' => 'Administrator',
                'email' => $defaultAdminEmail,
                'role' => \ROLE_ADMIN,
                'houseId' => null,
            ];

            session_put(AUTH_USER_SESSION, $user);
            session_put(AUTH_UID_SESSION, $user['uid']);
            session_put(AUTH_ROLE_SESSION, $user['role']);
            session_put('idToken', null);

            ActivityLogService::log($user['uid'], 'login', 'Default admin logged in');

            return [
                'success' => true,
                'message' => 'Logged in successfully.',
                'user' => $user,
            ];
        }

        $auth = FirebaseAuthService::signIn($email, $password);
        if (!$auth) {
            return ['success' => false, 'message' => 'Invalid email or password.'];
        }

        $profile = FirebaseService::getInstance()->getDocument(\COL_USERS, $auth['uid']);
        if (!$profile) {
            return ['success' => false, 'message' => 'No user profile found for this account. Contact an administrator.'];
        }

        if (($profile['status'] ?? 'active') !== 'active') {
            return ['success' => false, 'message' => 'This account has been disabled. Contact an administrator.'];
        }

        $appConfig = require APP_ROOT . '/app/config/app.php';
        if (!empty($appConfig['require_email_verification'])) {
            $emailVerified = filter_var($profile['emailVerified'] ?? true, FILTER_VALIDATE_BOOLEAN);
            $temporaryPasswordUser = !empty($profile['temp_password']) || (($profile['auth_created'] ?? true) === false);
            if (!$emailVerified && !$temporaryPasswordUser) {
                return ['success' => false, 'message' => 'Please verify your email before signing in.'];
            }
        }

        $user = [
            'uid'     => $auth['uid'],
            'name'    => $profile['name'] ?? $profile['email'],
            'email'   => $profile['email'] ?? $auth['email'],
            'role'    => $profile['role'] ?? \ROLE_STUDENT,
            'houseId' => $profile['houseId'] ?? null,
        ];

        session_put(AUTH_USER_SESSION, $user);
        session_put(AUTH_UID_SESSION, $auth['uid']);
        session_put(AUTH_ROLE_SESSION, $user['role']);
        session_put('idToken', $auth['idToken']);

        ActivityLogService::log($auth['uid'], 'login', 'User logged in');

        return [
            'success' => true,
            'message' => 'Logged in successfully.',
            'user' => $user,
        ];
    }

    public static function isLoggedIn(): bool
    {
        return !empty($_SESSION[AUTH_USER_SESSION]) || !empty($_SESSION[AUTH_UID_SESSION]);
    }



    public static function currentUser(): ?array
    {
        return $_SESSION[AUTH_USER_SESSION] ?? null;
    }

    public static function role(): ?string
    {
        $role = $_SESSION[AUTH_ROLE_SESSION] ?? ($_SESSION[AUTH_USER_SESSION]['role'] ?? null);

        if ($role === null) {
            return null;
        }

        return strtolower(trim($role));
    }

    public static function logout(): void
    {
        $user = self::currentUser();
        if ($user) {
            ActivityLogService::log($user['uid'], 'logout', 'User logged out');
        }
        session_destroy_all();
    }
}
