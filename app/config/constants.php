<?php
// Application root directory for path helpers and bootstrap.
if (!defined('APP_ROOT')) {
    define('APP_ROOT', dirname(__DIR__, 2));
}

// Roles recognized across the whole system
if (!defined('ROLE_ADMIN')) {
    define('ROLE_ADMIN', 'admin');
}
if (!defined('ROLE_HOUSE_MASTER')) {
    define('ROLE_HOUSE_MASTER', 'house_master');
}
if (!defined('ROLE_HOUSE_MISTRESS')) {
    define('ROLE_HOUSE_MISTRESS', 'house_mistress');
}
if (!defined('ROLE_HOUSEPARENT')) {
    define('ROLE_HOUSEPARENT', 'houseparent');
}
if (!defined('ROLE_SECURITY')) {
    define('ROLE_SECURITY', 'security');
}
if (!defined('ROLE_NURSE')) {
    define('ROLE_NURSE', 'nurse');
}
if (!defined('ROLE_STUDENT')) {
    define('ROLE_STUDENT', 'student');
}

$baseRoles = [
    ROLE_ADMIN, ROLE_HOUSE_MASTER, ROLE_HOUSE_MISTRESS, ROLE_HOUSEPARENT,
    ROLE_SECURITY, ROLE_NURSE, ROLE_STUDENT,
];

// Custom roles are now managed through Firestore (roles collection)
$customRoleKeys = [];
$allRoles = array_values(array_unique(array_merge($baseRoles, $customRoleKeys)));
define('ALL_ROLES', $allRoles);

$roleDashboard = [
    ROLE_ADMIN          => '/views/admin/dashboard.php',
    ROLE_HOUSE_MASTER   => '/views/house-master/dashboard/index.php',
    ROLE_HOUSE_MISTRESS => '/views/house-master/dashboard/index.php',
    ROLE_HOUSEPARENT    => '/views/houseparent/dashboard/index.php',
    ROLE_SECURITY       => '/views/security/dashboard/dashboard.php',
    ROLE_NURSE          => '/views/nurse/dashboard/dashboard.php',
    ROLE_STUDENT        => '/views/student/dashboard/index.php',
];

// Custom role dashboards managed via Firestore (roles collection)
define('ROLE_DASHBOARD', $roleDashboard);

define('STATUS_ACTIVE', 'active');
define('STATUS_SUSPENDED', 'suspended');
define('STATUS_INACTIVE', 'inactive');

define('COL_USERS', 'users');
define('COL_STUDENTS', 'students');
define('COL_HOUSES', 'houses');
define('COL_ROOMS', 'rooms');
define('COL_ROOM_ALLOCATIONS', 'room_allocations');
define('COL_ATTENDANCE', 'attendance');
define('COL_ACTIVITY_LOGS', 'activity_logs');
define('COL_INCIDENTS', 'incidents');
define('COL_VISITORS', 'visitors');
define('COL_VISITOR_REQUESTS', 'visitor_requests');
define('COL_MEDICAL_RECORDS', 'medical_records');
define('COL_NOTIFICATIONS', 'notifications');
define('COL_PARENT_MESSAGES', 'parent_messages');
define('COL_REPORTS', 'reports');
define('COL_ROLES', 'roles');
define('COL_PERMISSIONS', 'permissions');
define('COL_SETTINGS', 'settings');
