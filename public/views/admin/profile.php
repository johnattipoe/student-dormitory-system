<?php
if (!defined('APP_ROOT')) {
    $dir = __DIR__;
    for ($i = 0; $i < 10; $i++) {
        if (file_exists($dir . '/bootstrap.php')) {
            require $dir . '/bootstrap.php';
            break;
        }
        $parent = dirname($dir);
        if ($parent === $dir) break;
        $dir = $parent;
    }
}
$allowedRoles = [ROLE_ADMIN];
require APP_ROOT . '/app/middleware/RoleMiddleware.php';

use App\Services\FirebaseAuthService;
use App\Services\FirebaseService;
use App\Services\UserService;

$user = current_user() ?? [];
$userId = current_user_id();
$profile = $userId ? (new UserService())->find($userId) : null;
if (is_array($profile)) $user = array_merge($user, $profile);
$csrfToken = $_SESSION['admin_profile_csrf'] ?? bin2hex(random_bytes(32));
$_SESSION['admin_profile_csrf'] = $csrfToken;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!hash_equals($csrfToken, (string) ($_POST['csrf_token'] ?? ''))) {
        flash('error', 'The form expired. Please refresh the page and try again.');
        redirect(url('views/admin/profile.php'));
    }

    $action = (string) ($_POST['action'] ?? '');
    if ($action === 'update_profile' && $userId) {
        $name = trim((string) sanitize($_POST['name'] ?? ''));
        $phone = trim((string) sanitize($_POST['phone'] ?? ''));
        $result = $name === ''
            ? ['success' => false, 'message' => 'Your name is required.']
            : (new UserService())->update($userId, ['name' => $name, 'phone' => $phone]);
        if ($result['success']) session_put(AUTH_USER_SESSION, array_merge($user, ['name' => $name, 'phone' => $phone]));
        flash($result['success'] ? 'success' : 'error', $result['message']);
        redirect(url('views/admin/profile.php'));
    }

    if ($action === 'send_password_reset') {
        try {
            $email = trim((string) ($user['email'] ?? ''));
            $result = $email !== ''
                ? FirebaseAuthService::sendPasswordResetEmail($email)
                : ['success' => false, 'message' => 'No email address is associated with your account.'];
            flash($result['success'] ? 'success' : 'error', $result['success'] ? 'Password reset instructions were sent to your email.' : $result['message']);
        } catch (Throwable $exception) {
            flash('error', 'Unable to send password reset instructions.');
        }
        redirect(url('views/admin/profile.php'));
    }
}

$userName = trim((string) ($user['name'] ?? 'Administrator')) ?: 'Administrator';
$nameParts = preg_split('/\s+/', $userName, -1, PREG_SPLIT_NO_EMPTY);
$initials = strtoupper(substr($nameParts[0] ?? 'A', 0, 1) . (count($nameParts) > 1 ? substr($nameParts[count($nameParts) - 1], 0, 1) : ''));
$firebase = FirebaseService::getInstance();
$systemMetrics = [
    'users' => count($firebase->getCollection(COL_USERS, [], 500)),
    'students' => count($firebase->getCollection(COL_STUDENTS, [], 1000)),
    'houses' => count($firebase->getCollection(COL_HOUSES, [], 100)),
    'rooms' => count($firebase->getCollection(COL_ROOMS, [], 500)),
];
$recentActivity = $firebase->getCollection(COL_ACTIVITY_LOGS, [], 8);
usort($recentActivity, static fn(array $first, array $second): int => strcmp((string) ($second['timestamp'] ?? $second['createdAt'] ?? ''), (string) ($first['timestamp'] ?? $first['createdAt'] ?? '')));
$pageTitle = 'My Profile';
$navItems = [
    ['icon' => 'bi-speedometer2', 'label' => 'Dashboard', 'href' => url('views/admin/dashboard.php')],
    ['icon' => 'bi-people', 'label' => 'Users', 'href' => url('views/admin/users/index.php')],
    ['icon' => 'bi-gear', 'label' => 'Settings', 'href' => url('views/admin/settings/index.php')],
    ['icon' => 'bi-person-circle', 'label' => 'Profile', 'href' => url('views/admin/profile.php'), 'active' => true],
];

require APP_ROOT . '/app/views/components/header.php';
require APP_ROOT . '/app/views/components/sidebar.php';
?>
<div class="main-content">
    <?php require APP_ROOT . '/app/views/components/navbar.php'; ?>
    <?php require APP_ROOT . '/app/views/components/alerts.php'; ?>
    <div class="content-wrapper">
        <section class="admin-profile-hero">
            <div class="admin-profile-avatar" aria-hidden="true"><?= e($initials) ?></div>
            <div><span class="admin-kicker">Administrator account</span><h1><?= e($userName) ?></h1><p>Manage system users, settings, and operational access.</p><span class="badge bg-success"><i class="bi bi-shield-check me-1"></i>Active account</span></div>
            <i class="bi bi-sliders admin-profile-mark" aria-hidden="true"></i>
        </section>
        <div class="row g-4 mt-1">
            <div class="col-lg-8"><section class="admin-profile-card h-100"><div class="admin-card-header"><div><span class="admin-kicker">Identity</span><h2>Personal details</h2><p>Keep your administrator contact details current.</p></div><i class="bi bi-person-vcard admin-profile-icon"></i></div><form method="POST" class="admin-profile-form"><input type="hidden" name="action" value="update_profile"><input type="hidden" name="csrf_token" value="<?= e($csrfToken) ?>"><div class="row g-3"><div class="col-md-6"><label class="form-label" for="admin-name">Full name</label><input id="admin-name" name="name" class="form-control" value="<?= e($userName) ?>" required></div><div class="col-md-6"><label class="form-label" for="admin-email">Email address</label><input id="admin-email" class="form-control" value="<?= e($user['email'] ?? 'Not specified') ?>" disabled></div><div class="col-md-6"><label class="form-label" for="admin-phone">Phone number</label><input id="admin-phone" name="phone" class="form-control" value="<?= e($user['phone'] ?? '') ?>"></div><div class="col-md-6"><label class="form-label" for="admin-role">Role</label><input id="admin-role" class="form-control text-capitalize" value="<?= e(str_replace('_', ' ', (string) ($user['role'] ?? 'admin'))) ?>" disabled></div></div><button class="btn btn-primary mt-4" type="submit"><i class="bi bi-check2 me-1"></i>Save profile</button></form></section></div>
            <div class="col-lg-4"><aside class="admin-profile-card h-100"><span class="admin-kicker">Security</span><h2>Account access</h2><div class="admin-profile-list"><div><i class="bi bi-shield-lock"></i><span>Administrator access enabled</span></div><div><i class="bi bi-person-badge"></i><span>Account ID: <?= e($user['uid'] ?? $user['id'] ?? 'Assigned') ?></span></div><div><i class="bi bi-clock-history"></i><span>Session active</span></div></div><form method="POST" class="mt-4"><input type="hidden" name="action" value="send_password_reset"><input type="hidden" name="csrf_token" value="<?= e($csrfToken) ?>"><button class="btn btn-outline-danger w-100" type="submit"><i class="bi bi-key me-1"></i>Reset password</button></form></aside></div>
        </div>
        <section class="admin-profile-card admin-profile-actions mt-4"><div><span class="admin-kicker">Administration</span><h2>System controls</h2></div><div class="d-flex flex-wrap gap-2"><a class="btn btn-outline-primary" href="<?= url('views/admin/users/index.php') ?>"><i class="bi bi-people me-1"></i>Manage users</a><a class="btn btn-outline-secondary" href="<?= url('views/admin/settings/index.php') ?>"><i class="bi bi-gear me-1"></i>Settings</a><a class="btn btn-primary" href="<?= url('views/admin/dashboard.php') ?>"><i class="bi bi-speedometer2 me-1"></i>Dashboard</a></div></section>
        <div class="row g-3 mt-1"><div class="col-md-3"><div class="admin-system-stat"><i class="bi bi-people"></i><small>Users</small><strong><?= e((string) $systemMetrics['users']) ?></strong></div></div><div class="col-md-3"><div class="admin-system-stat"><i class="bi bi-mortarboard"></i><small>Students</small><strong><?= e((string) $systemMetrics['students']) ?></strong></div></div><div class="col-md-3"><div class="admin-system-stat"><i class="bi bi-building"></i><small>Houses</small><strong><?= e((string) $systemMetrics['houses']) ?></strong></div></div><div class="col-md-3"><div class="admin-system-stat"><i class="bi bi-door-closed"></i><small>Rooms</small><strong><?= e((string) $systemMetrics['rooms']) ?></strong></div></div></div>
        <section class="admin-profile-card mt-4"><div class="admin-card-header"><div><span class="admin-kicker">Audit trail</span><h2>Recent activity</h2><p>The latest actions recorded across the system.</p></div><a class="btn btn-outline-secondary btn-sm" href="<?= url('views/admin/activity-logs/index.php') ?>"><i class="bi bi-clock-history me-1"></i>View all</a></div><?php if ($recentActivity): ?><div class="admin-activity-list"><?php foreach ($recentActivity as $activity): ?><div><span class="admin-activity-icon"><i class="bi bi-activity"></i></span><div><strong><?= e($activity['event'] ?? $activity['action'] ?? 'System activity') ?></strong><small><?= e($activity['description'] ?? 'Administrative action recorded') ?></small></div><time><?= e($activity['timestamp'] ?? $activity['createdAt'] ?? '—') ?></time></div><?php endforeach; ?></div><?php else: ?><div class="admin-empty-state"><i class="bi bi-clock-history"></i><p>No recent activity recorded.</p></div><?php endif; ?></section>
    </div>
</div>
<?php require APP_ROOT . '/app/views/components/footer.php'; ?>
