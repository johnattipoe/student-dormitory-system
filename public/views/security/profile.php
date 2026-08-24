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
$allowedRoles = [ROLE_SECURITY];
require APP_ROOT . '/app/middleware/RoleMiddleware.php';

use App\Services\FirebaseAuthService;
use App\Services\IncidentService;
use App\Services\NotificationService;
use App\Services\UserService;
use App\Services\VisitorService;

$user = current_user() ?? [];
$userId = current_user_id();
$profile = $userId ? (new UserService())->find($userId) : null;
if (is_array($profile)) $user = array_merge($user, $profile);
$csrfToken = $_SESSION['security_profile_csrf'] ?? bin2hex(random_bytes(32));
$_SESSION['security_profile_csrf'] = $csrfToken;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!hash_equals($csrfToken, (string) ($_POST['csrf_token'] ?? ''))) {
        flash('error', 'The form expired. Please refresh the page and try again.');
        redirect(url('views/security/profile.php'));
    }
    $action = (string) ($_POST['action'] ?? '');
    if ($action === 'update_profile' && $userId) {
        $name = trim((string) sanitize($_POST['name'] ?? ''));
        $phone = trim((string) sanitize($_POST['phone'] ?? ''));
        $result = $name === '' ? ['success' => false, 'message' => 'Your name is required.'] : (new UserService())->update($userId, ['name' => $name, 'phone' => $phone]);
        if ($result['success']) session_put(AUTH_USER_SESSION, array_merge($user, ['name' => $name, 'phone' => $phone]));
        flash($result['success'] ? 'success' : 'error', $result['message']);
        redirect(url('views/security/profile.php'));
    }
    if ($action === 'send_password_reset') {
        try {
            $email = trim((string) ($user['email'] ?? ''));
            $result = $email !== '' ? FirebaseAuthService::sendPasswordResetEmail($email) : ['success' => false, 'message' => 'No email address is associated with your account.'];
            flash($result['success'] ? 'success' : 'error', $result['success'] ? 'Password reset instructions were sent to your email.' : $result['message']);
        } catch (Throwable $exception) { flash('error', 'Unable to send password reset instructions.'); }
        redirect(url('views/security/profile.php'));
    }
}
$userName = trim((string) ($user['name'] ?? 'Security Officer')) ?: 'Security Officer';
$nameParts = preg_split('/\s+/', $userName, -1, PREG_SPLIT_NO_EMPTY);
$initials = strtoupper(substr($nameParts[0] ?? 'S', 0, 1) . (count($nameParts) > 1 ? substr($nameParts[count($nameParts) - 1], 0, 1) : ''));
$visitors = (new VisitorService())->all();
$incidents = (new IncidentService())->all();
$notifications = $userId ? (new NotificationService())->forUser($userId) : [];
$insideCount = count(array_filter($visitors, static fn(array $visitor): bool => ($visitor['status'] ?? '') === 'inside'));
$openIncidentCount = count(array_filter($incidents, static fn(array $incident): bool => ($incident['status'] ?? 'open') === 'open'));
$unreadCount = count(array_filter($notifications, static fn(array $notification): bool => empty($notification['read'])));
$pageTitle = 'My Profile';
$navItems = [
    ['icon' => 'bi-speedometer2', 'label' => 'Dashboard', 'href' => url('views/security/dashboard/dashboard.php')],
    ['icon' => 'bi-people', 'label' => 'Visitors', 'href' => url('views/security/visitors/visitors.php')],
    ['icon' => 'bi-journal-text', 'label' => 'Visitor History', 'href' => url('views/security/visitor-history/visitor-history.php')],
    ['icon' => 'bi-exclamation-triangle', 'label' => 'Incidents', 'href' => url('views/security/incidents/incidents.php')],
    ['icon' => 'bi-bell', 'label' => 'Notifications', 'href' => url('views/security/notifications/notifications.php')],
    ['icon' => 'bi-person-circle', 'label' => 'Profile', 'href' => url('views/security/profile.php'), 'active' => true],
];

require APP_ROOT . '/app/views/components/header.php';
require APP_ROOT . '/app/views/components/sidebar.php';
?>
<div class="main-content security-portal">
    <?php require APP_ROOT . '/app/views/components/navbar.php'; ?>
    <?php require APP_ROOT . '/app/views/components/alerts.php'; ?>
    <div class="content-wrapper">
        <section class="security-hero mb-4">
            <div class="security-hero-icon">
                <span aria-hidden="true"><?= e($initials) ?></span>
            </div>
            <div>
                <span class="security-kicker">Security account</span>
                <h1><?= e($userName) ?></h1>
                <p>Gate operations and visitor safety workspace.</p>
                <span class="badge bg-success"><i class="bi bi-shield-check me-1"></i>Active account</span>
            </div>
            <i class="bi bi-shield-lock security-profile-mark" aria-hidden="true"></i>
        </section>
        <div class="row g-4">
            <div class="col-lg-8">
                <section class="security-card h-100">
                    <div class="security-card-header"><div>
                        <span class="security-kicker">Identity</span>
                        <h2>Personal details</h2>
                        <p>Keep your contact details current for gate operations.</p></div><i class="bi bi-person-vcard security-profile-icon"></i></div><form method="POST" class="security-form"><input type="hidden" name="action" value="update_profile"><input type="hidden" name="csrf_token" value="<?= e($csrfToken) ?>"><div class="row g-3"><div class="col-md-6"><label class="form-label" for="security-name">Full name</label><input id="security-name" name="name" class="form-control" value="<?= e($userName) ?>" required></div><div class="col-md-6"><label class="form-label" for="security-email">Email address</label><input id="security-email" class="form-control" value="<?= e($user['email'] ?? 'Not specified') ?>" disabled></div><div class="col-md-6"><label class="form-label" for="security-phone">Phone number</label><input id="security-phone" name="phone" class="form-control" value="<?= e($user['phone'] ?? '') ?>"></div><div class="col-md-6"><label class="form-label" for="security-role">Role</label><input id="security-role" class="form-control text-capitalize" value="<?= e(str_replace('_', ' ', (string) ($user['role'] ?? 'security'))) ?>" disabled></div></div><button class="btn btn-primary mt-4" type="submit"><i class="bi bi-check2 me-1"></i>Save profile</button></form></section></div>
            <div class="col-lg-4">
                <aside class="security-side-card h-100">
                    <span class="security-kicker">Access</span>
                    <h2>Portal access</h2>
                    <div class="security-checklist">
                        <div>
                            <i class="bi bi-shield-check"></i>
                            <span>Security workspace enabled</span>
                        </div>
                        <div>
                            <i class="bi bi-person-badge"></i>
                            <span>Account ID: <?= e($user['uid'] ?? $user['id'] ?? 'Assigned') ?></span>
                        </div>
                        <div>
                            <i class="bi bi-clock-history"></i>
                            <span>Session active</span>
                        </div>
                    </div>
                    <form method="POST" class="mt-3">
                        <input type="hidden" name="action" value="send_password_reset">
                        <input type="hidden" name="csrf_token" value="<?= e($csrfToken) ?>">
                        <button class="btn btn-outline-danger w-100" type="submit">
                            <i class="bi bi-key me-1"></i>Reset password
                        </button>
                    </form>
                    <a class="btn btn-outline-primary w-100 mt-2" href="<?= url('views/security/dashboard/dashboard.php') ?>">
                        <i class="bi bi-speedometer2 me-1"></i>Return to dashboard
                    </a>
                </aside>
            </div>
        </div>
        <div class="row g-3 mt-1">
            <div class="col-md-4">
                <div class="security-stat">
                    <span class="security-stat-icon green">
                        <i class="bi bi-door-open"></i>
                    </span>
                    <div>
                        <small>Visitors inside</small>
                        <strong><?= e((string) $insideCount) ?></strong>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="security-stat">
                    <span class="security-stat-icon red">
                        <i class="bi bi-exclamation-triangle"></i>
                    </span>
                    <div>
                        <small>Open incidents</small>
                        <strong><?= e((string) $openIncidentCount) ?></strong>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="security-stat">
                    <span class="security-stat-icon orange">
                        <i class="bi bi-bell"></i>
                    </span>
                    <div>
                        <small>Unread notifications</small>
                        <strong><?= e((string) $unreadCount) ?></strong>
                    </div>
                </div>
            </div>
        </div>
        <section class="security-card security-profile-actions mt-4">
            <div>
                <span class="security-kicker">Quick operations</span>
                <h2>Return to the desk</h2>
            </div>
            <div class="security-hero-actions">
                <a class="btn btn-outline-success" href="<?= url('views/security/visitor-check-in/visitor-check-in.php') ?>">
                    <i class="bi bi-box-arrow-in-right me-1"></i>Check in
                </a>
                <a class="btn btn-outline-danger" href="<?= url('views/security/report-incident/report-incident.php') ?>">
                    <i class="bi bi-flag me-1"></i>Report incident
                </a>
                <a class="btn btn-primary" href="<?= url('views/security/reports/index.php') ?>">
                    <i class="bi bi-bar-chart me-1"></i>View reports
                </a>
            </div>
        </section>
        <section class="security-card security-profile-actions mt-4">
            <div>
                <span class="security-kicker">Logout</span>
                <h2>End your session</h2>
            </div>
            <div class="security-hero-actions">
                <form method="POST" class="mt-3">
                    <input type="hidden" name="action" value="logout">
                    <input type="hidden" name="csrf_token" value="<?= e($csrfToken) ?>">
                    <button class="btn btn-outline-danger w-100" type="submit">
                        <i class="bi bi-box-arrow-right me-1"></i>Logout safely
                    </button>
                </form>
            </div>
        </section>
    </div>
</div>
<?php require APP_ROOT . '/app/views/components/footer.php'; ?>
