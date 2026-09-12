<?php

/**
 * Authentication Helper
 * Student Dormitory Management System
 *
 * Handles:
 * - Session management
 * - Login state
 * - Current user
 * - User roles
 * - Role permissions
 * - Access control
 * - Login/logout helpers
 * - Dashboard redirection
 *
 * Supported roles:
 * - admin
 * - house_master
 * - houseparent
 * - security
 * - nurse
 * - student
 */

use App\Services\AuthService;


/*
|--------------------------------------------------------------------------
| Start Session
|--------------------------------------------------------------------------
|
| Make sure a PHP session exists before using $_SESSION.
|
*/

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}


/*
|--------------------------------------------------------------------------
| Session Keys
|--------------------------------------------------------------------------
*/

if (!defined('AUTH_USER_SESSION')) {
    define('AUTH_USER_SESSION', 'auth_user');
}

if (!defined('AUTH_UID_SESSION')) {
    define('AUTH_UID_SESSION', 'uid');
}

if (!defined('AUTH_ROLE_SESSION')) {
    define('AUTH_ROLE_SESSION', 'role');
}


/*
|--------------------------------------------------------------------------
| Role Dashboard Configuration
|--------------------------------------------------------------------------
*/

if (!defined('ROLE_DASHBOARD')) {
    $dashboardMap = [
        'admin' => '/views/admin/dashboard.php',
        'house_master' => '/views/house-master/dashboard/index.php',
        'house_mistress' => '/views/house-master/dashboard/index.php',
        'senior-houseparent' => '/views/senior-houseparent/dashboard/index.php',
        'security' => '/views/security/dashboard/dashboard.php',
        'nurse' => '/views/nurse/dashboard/dashboard.php',
        'student' => '/views/student/dashboard/index.php'
    ];

    // Custom role dashboards managed via Firestore
    define('ROLE_DASHBOARD', $dashboardMap);
}


/*
|--------------------------------------------------------------------------
| Available Roles
|--------------------------------------------------------------------------
*/

if (!function_exists('available_roles')) {

    function available_roles(): array
    {
        $roles = [
            'admin',
            'house_master',
            'house_mistress',
            'senior-houseparent',
            'security',
            'nurse',
            'student'
        ];

        // Custom roles managed via Firestore (roles collection)
        return array_values(array_unique($roles));
    }
}


/*
|--------------------------------------------------------------------------
| Normalize Role
|--------------------------------------------------------------------------
*/

if (!function_exists('normalize_role')) {

    function normalize_role(?string $role): string
    {
        if (!$role) {
            return '';
        }

        $normalizedRole = strtolower(trim($role));
        if (in_array($normalizedRole, ['houseparent', 'senior-houseparent', 'senior_houseparent', 'senior houseparent'], true)) {
            return 'senior-houseparent';
        }

        return $normalizedRole;
    }
}


/*
|--------------------------------------------------------------------------
| Check Whether User Is Logged In
|--------------------------------------------------------------------------
*/

if (!function_exists('is_logged_in')) {

    function is_logged_in(): bool
    {
        return !empty($_SESSION[AUTH_USER_SESSION])
            || !empty($_SESSION[AUTH_UID_SESSION]);
    }
}


/*
|--------------------------------------------------------------------------
| Current User
|--------------------------------------------------------------------------
*/

if (!function_exists('current_user')) {

    function current_user(): ?array
    {
        if (
            isset($_SESSION[AUTH_USER_SESSION]) &&
            is_array($_SESSION[AUTH_USER_SESSION])
        ) {
            return $_SESSION[AUTH_USER_SESSION];
        }

        return null;
    }
}


/*
|--------------------------------------------------------------------------
| Current User ID
|--------------------------------------------------------------------------
*/

if (!function_exists('current_user_id')) {

    function current_user_id(): ?string
    {
        if (!empty($_SESSION[AUTH_UID_SESSION])) {
            return (string) $_SESSION[AUTH_UID_SESSION];
        }

        $user = current_user();

        if ($user && !empty($user['uid'])) {
            return (string) $user['uid'];
        }

        if ($user && !empty($user['id'])) {
            return (string) $user['id'];
        }

        return null;
    }
}


/*
|--------------------------------------------------------------------------
| Current User Role
|--------------------------------------------------------------------------
*/

if (!function_exists('current_role')) {

    function current_role(): ?string
    {
        if (!empty($_SESSION[AUTH_ROLE_SESSION])) {
            return normalize_role(
                $_SESSION[AUTH_ROLE_SESSION]
            );
        }

        $user = current_user();

        if ($user && !empty($user['role'])) {
            return normalize_role(
                $user['role']
            );
        }

        return null;
    }
}


/*
|--------------------------------------------------------------------------
| Current User Name
|--------------------------------------------------------------------------
*/

if (!function_exists('current_user_name')) {

    function current_user_name(): string
    {
        $user = current_user();

        if (!$user) {
            return 'Guest';
        }

        if (!empty($user['name'])) {
            return (string) $user['name'];
        }

        if (!empty($user['displayName'])) {
            return (string) $user['displayName'];
        }

        $firstName = $user['firstName'] ?? '';
        $lastName = $user['lastName'] ?? '';

        $name = trim(
            $firstName . ' ' . $lastName
        );

        if ($name !== '') {
            return $name;
        }

        if (!empty($user['email'])) {
            return (string) $user['email'];
        }

        return 'User';
    }
}


/*
|--------------------------------------------------------------------------
| Current User Email
|--------------------------------------------------------------------------
*/

if (!function_exists('current_user_email')) {

    function current_user_email(): ?string
    {
        $user = current_user();

        if (
            $user &&
            !empty($user['email'])
        ) {
            return (string) $user['email'];
        }

        return null;
    }
}


/*
|--------------------------------------------------------------------------
| Check Specific Role
|--------------------------------------------------------------------------
*/

if (!function_exists('has_role')) {

    function has_role(string $role): bool
    {
        return current_role() === normalize_role($role);
    }
}


/*
|--------------------------------------------------------------------------
| Check Multiple Roles
|--------------------------------------------------------------------------
*/

if (!function_exists('has_any_role')) {

    function has_any_role(array $roles): bool
    {
        $currentRole = current_role();

        if (!$currentRole) {
            return false;
        }

        foreach ($roles as $role) {

            if (
                $currentRole === normalize_role($role)
            ) {
                return true;
            }
        }

        return false;
    }
}


/*
|--------------------------------------------------------------------------
| Is Admin
|--------------------------------------------------------------------------
*/

if (!function_exists('is_admin')) {

    function is_admin(): bool
    {
        return has_role('admin');
    }
}


/*
|--------------------------------------------------------------------------
| Is House Master
|--------------------------------------------------------------------------
*/

if (!function_exists('is_house_master')) {

    function is_house_master(): bool
    {
        return has_role('house_master');
    }
}


/*
|--------------------------------------------------------------------------
| Is House Mistress
|--------------------------------------------------------------------------
*/

if (!function_exists('is_house_mistress')) {

    function is_house_mistress(): bool
    {
        return has_role('house_mistress');
    }
}


/*
|--------------------------------------------------------------------------
| Is Houseparent
|--------------------------------------------------------------------------
*/

if (!function_exists('is_senior_houseparent')) {

    function is_senior_houseparent(): bool
    {
        return has_role('senior-houseparent');
    }
}


/*
|--------------------------------------------------------------------------
| Is Security
|--------------------------------------------------------------------------
*/

if (!function_exists('is_security')) {

    function is_security(): bool
    {
        return has_role('security');
    }
}


/*
|--------------------------------------------------------------------------
| Is Nurse
|--------------------------------------------------------------------------
*/

if (!function_exists('is_nurse')) {

    function is_nurse(): bool
    {
        return has_role('nurse');
    }
}


/*
|--------------------------------------------------------------------------
| Is Student
|--------------------------------------------------------------------------
*/

if (!function_exists('is_student')) {

    function is_student(): bool
    {
        return has_role('student');
    }
}


/*
|--------------------------------------------------------------------------
| Login User
|--------------------------------------------------------------------------
*/

if (!function_exists('login_user')) {

    function login_user(array $user): void
    {
        session_regenerate_id(true);

        $_SESSION[AUTH_USER_SESSION] = $user;

        $_SESSION[AUTH_UID_SESSION] =
            $user['uid']
            ?? $user['id']
            ?? null;

        $_SESSION[AUTH_ROLE_SESSION] =
            normalize_role(
                $user['role'] ?? ''
            );

        $_SESSION['logged_in'] = true;

        $_SESSION['login_time'] = time();
    }
}


/*
|--------------------------------------------------------------------------
| Logout User
|--------------------------------------------------------------------------
*/

if (!function_exists('logout_user')) {

    function logout_user(): void
    {
        $_SESSION = [];

        if (
            ini_get('session.use_cookies')
        ) {

            $params = session_get_cookie_params();

            setcookie(
                session_name(),
                '',
                time() - 42000,
                $params['path'],
                $params['domain'],
                $params['secure'],
                $params['httponly']
            );
        }

        session_destroy();
    }
}


/*
|--------------------------------------------------------------------------
| Require Authentication
|--------------------------------------------------------------------------
*/

if (!function_exists('require_login')) {

    function require_login(): void
    {
        if (is_logged_in()) {
            return;
        }

        flash(
            'error',
            'Please login to access this page.'
        );

        redirect(
            base_url('login.php')
        );

        exit;
    }
}


/*
|--------------------------------------------------------------------------
| Require Specific Role
|--------------------------------------------------------------------------
*/

if (!function_exists('require_role')) {

    function require_role(string $role): void
    {
        require_login();

        if (has_role($role)) {
            return;
        }

        access_denied();
    }
}


/*
|--------------------------------------------------------------------------
| Require Any Role
|--------------------------------------------------------------------------
*/

if (!function_exists('require_any_role')) {

    function require_any_role(array $roles): void
    {
        require_login();

        if (has_any_role($roles)) {
            return;
        }

        access_denied();
    }
}


/*
|--------------------------------------------------------------------------
| Access Denied
|--------------------------------------------------------------------------
*/

if (!function_exists('access_denied')) {

    function access_denied(): void
    {
        http_response_code(403);

        $file =
            __DIR__ .
            '/../../../public/views/errors/403.php';

        if (file_exists($file)) {

            include $file;

        } else {

            echo '<!DOCTYPE html>';
            echo '<html>';
            echo '<head>';
            echo '<title>Access Denied</title>';
            echo '</head>';
            echo '<body>';

            echo '<h1>403 - Access Denied</h1>';

            echo '<p>';
            echo 'You do not have permission to access this page.';
            echo '</p>';

            echo '<a href="' .
                htmlspecialchars(
                    base_url('index.php')
                ) .
                '">Return to Dashboard</a>';

            echo '</body>';
            echo '</html>';
        }

        exit;
    }
}


/*
|--------------------------------------------------------------------------
| Dashboard URL
|--------------------------------------------------------------------------
*/

if (!function_exists('dashboard_url')) {

    function dashboard_url(): string
    {
        $role = current_role();

        $path =
            ROLE_DASHBOARD[$role]
            ?? '/views/dashboard/dashboard.php';

        return base_url(
            'index.php?route=' .
            urlencode($path)
        );
    }
}


/*
|--------------------------------------------------------------------------
| Redirect To Dashboard
|--------------------------------------------------------------------------
*/

if (!function_exists('redirect_to_dashboard')) {

    function redirect_to_dashboard(): void
    {
        redirect(
            dashboard_url()
        );

        exit;
    }
}


/*
|--------------------------------------------------------------------------
| Check Permission
|--------------------------------------------------------------------------
|
| Permission structure for the Student Dormitory System.
|
*/

if (!function_exists('permission_matrix')) {
    function permission_matrix(): array
    {
        static $permissions;
        if ($permissions !== null) {
            return $permissions;
        }

        $permissions = require __DIR__ . '/../../config/permissions/permissions.php';
        try {
            $savedPermissions = \App\Services\FirebaseService::getInstance()->getCollection(COL_PERMISSIONS, [], 200);
            foreach ($savedPermissions as $savedPermission) {
                $role = (string) ($savedPermission['role'] ?? '');
                if ($role !== '' && !empty($savedPermission['levels']) && is_array($savedPermission['levels'])) {
                    $permissions[$role] = array_replace($permissions[$role] ?? [], $savedPermission['levels']);
                }
            }
        } catch (\Throwable $e) {
            // The built-in matrix remains available when Firestore is unavailable.
        }

        return $permissions;
    }
}

if (!function_exists('has_permission')) {

    function has_permission(
        string $permission
    ): bool {
        $role = current_role();
        if (!$role) {
            return false;
        }
        [$module, $action] = array_pad(explode('.', strtolower(trim($permission)), 2), 2, 'view');
        $module = ['medical' => 'medical_records'][$module] ?? $module;
        $requiredLevel = match ($action) {
            'create', 'edit', 'mark', 'allocate', 'register', 'send' => 'manage',
            'delete', 'generate' => 'full',
            default => 'view',
        };

        $permissions = permission_matrix();
        $currentLevel = $permissions[$role][$module] ?? 'none';
        $levelOrder = [
            'none' => 0,
            'own' => 2,
            'view' => 2,
            'limited' => 3,
            'manage' => 4,
            'full' => 5,
        ];

        return ($levelOrder[$currentLevel] ?? 0) >= ($levelOrder[$requiredLevel] ?? 0);
    }
}


if (!function_exists('can')) {

    function can(string $module, string $requiredLevel): bool
    {
        $role = current_role();
        if (!$role) {
            return false;
        }

        $permissions = permission_matrix();
        $currentLevel = $permissions[$role][$module] ?? 'none';

        $levelOrder = [
            'none' => 0,
            'own' => 1,
            'view' => 2,
            'limited' => 3,
            'manage' => 4,
            'full' => 5,
        ];

        return ($levelOrder[$currentLevel] ?? 0) >= ($levelOrder[$requiredLevel] ?? 0);
    }
}


/*
|--------------------------------------------------------------------------
| Require Permission
|--------------------------------------------------------------------------
*/

if (!function_exists('require_permission')) {

    function require_permission(
        string $permission
    ): void {

        require_login();

        if (
            has_permission($permission)
        ) {
            return;
        }

        access_denied();
    }
}


/*
|--------------------------------------------------------------------------
| User House ID
|--------------------------------------------------------------------------
*/

if (!function_exists('current_house_id')) {

    function current_house_id(): ?string
    {
        $user = current_user();

        if (!$user) {
            return null;
        }

        return
            $user['houseId']
            ?? $user['house_id']
            ?? null;
    }
}


/*
|--------------------------------------------------------------------------
| Check Whether User Belongs To House
|--------------------------------------------------------------------------
*/

if (!function_exists('belongs_to_house')) {

    function belongs_to_house(
        string $houseId
    ): bool {

        if (is_admin()) {
            return true;
        }

        return current_house_id() === $houseId;
    }
}


/*
|--------------------------------------------------------------------------
| Require House Access
|--------------------------------------------------------------------------
*/

if (!function_exists('require_house_access')) {

    function require_house_access(
        string $houseId
    ): void {

        require_login();

        if (
            belongs_to_house($houseId)
        ) {
            return;
        }

        access_denied();
    }
}


/*
|--------------------------------------------------------------------------
| Session Flash Message
|--------------------------------------------------------------------------
*/

if (!function_exists('flash')) {

    function flash(
        string $type,
        string $message
    ): void {

        $_SESSION['flash'] = [
            'type' => $type,
            'message' => $message
        ];
    }
}


/*
|--------------------------------------------------------------------------
| Get Flash Message
|--------------------------------------------------------------------------
*/

if (!function_exists('get_flash')) {

    function get_flash(): ?array
    {
        if (
            empty($_SESSION['flash'])
        ) {
            return null;
        }

        $flash =
            $_SESSION['flash'];

        unset(
            $_SESSION['flash']
        );

        return $flash;
    }
}


/*
|--------------------------------------------------------------------------
| Check Whether User Is Active
|--------------------------------------------------------------------------
*/

if (!function_exists('current_user_is_active')) {

    function current_user_is_active(): bool
    {
        $user = current_user();

        if (!$user) {
            return false;
        }

        if (
            isset($user['active']) &&
            !$user['active']
        ) {
            return false;
        }

        if (
            isset($user['status']) &&
            strtolower(
                (string) $user['status']
            ) !== 'active'
        ) {
            return false;
        }

        return true;
    }
}


/*
|--------------------------------------------------------------------------
| Get Authentication User
|--------------------------------------------------------------------------
|
| Optional Firebase-backed refresh of the current user.
|
*/

if (!function_exists('refresh_current_user')) {

    function refresh_current_user(): ?array
    {
        $uid = current_user_id();

        if (!$uid) {
            return null;
        }

        try {

            $userService =
                new \App\Services\UserService();

            $user =
                $userService->find($uid);

            if (
                is_array($user)
            ) {

                $_SESSION[
                    AUTH_USER_SESSION
                ] = $user;

                $_SESSION[
                    AUTH_UID_SESSION
                ] =
                    $user['uid']
                    ?? $user['id']
                    ?? $uid;

                $_SESSION[
                    AUTH_ROLE_SESSION
                ] =
                    normalize_role(
                        $user['role'] ?? ''
                    );

                return $user;
            }

        } catch (\Throwable $e) {

            return current_user();
        }

        return current_user();
    }
}


/*
|--------------------------------------------------------------------------
| CSRF Token
|--------------------------------------------------------------------------
*/

if (!function_exists('csrf_token')) {

    function csrf_token(): string
    {
        if (
            empty($_SESSION['csrf_token'])
        ) {

            $_SESSION['csrf_token'] =
                bin2hex(
                    random_bytes(32)
                );
        }

        return $_SESSION['csrf_token'];
    }
}


/*
|--------------------------------------------------------------------------
| CSRF Hidden Input
|--------------------------------------------------------------------------
*/

if (!function_exists('csrf_field')) {

    function csrf_field(): string
    {
        return
            '<input type="hidden" ' .
            'name="csrf_token" value="' .
            htmlspecialchars(
                csrf_token(),
                ENT_QUOTES,
                'UTF-8'
            ) .
            '">';
    }
}


/*
|--------------------------------------------------------------------------
| Verify CSRF Token
|--------------------------------------------------------------------------
*/

if (!function_exists('verify_csrf_token')) {

    function verify_csrf_token(
        ?string $token
    ): bool {

        if (!$token) {
            return false;
        }

        if (
            empty($_SESSION['csrf_token'])
        ) {
            return false;
        }

        return hash_equals(
            $_SESSION['csrf_token'],
            $token
        );
    }
}


/*
|--------------------------------------------------------------------------
| Require Valid CSRF Token
|--------------------------------------------------------------------------
*/

if (!function_exists('require_csrf')) {

    function require_csrf(): void
    {
        if (
            !verify_csrf_token(
                $_POST['csrf_token'] ?? null
            )
        ) {

            http_response_code(419);

            exit(
                'Invalid or expired security token.'
            );
        }
    }
}


/*
|--------------------------------------------------------------------------
| Session Timeout
|--------------------------------------------------------------------------
*/

if (!function_exists('check_session_timeout')) {

    function check_session_timeout(
        ?int $timeout = null
    ): void {

        if (!is_logged_in()) {
            return;
        }

        $config = require __DIR__ . '/../../config/app/app.php';
        $timeoutSeconds = $timeout ?? ((int) ($config['session_lifetime'] ?? 120) * 60);

        $lastActivity =
            $_SESSION['last_activity']
            ?? time();

        if (
            time() - $lastActivity > $timeoutSeconds
        ) {

            logout_user();

            flash(
                'error',
                'Your session has expired. Please login again.'
            );

            redirect(
                base_url('login.php')
            );

            exit;
        }

        $_SESSION['last_activity'] =
            time();
    }
}


/*
|--------------------------------------------------------------------------
| Initialize Authentication
|--------------------------------------------------------------------------
*/

if (!function_exists('initialize_auth')) {

    function initialize_auth(): void
    {
        if (!is_logged_in()) {
            return;
        }

        check_session_timeout();

        if (
            !current_user_is_active()
        ) {

            logout_user();

            flash(
                'error',
                'Your account is inactive.'
            );

            redirect(
                base_url('login.php')
            );

            exit;
        }
    }
}