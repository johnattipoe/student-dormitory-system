<?php
// Ensure bootstrap is loaded (safe at any view nesting depth)
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

$allowedRoles = [ROLE_HOUSEPARENT];
require APP_ROOT . '/app/middleware/RoleMiddleware.php';

use App\Services\NotificationService;

$currentUser = current_user();
$currentUserId = $currentUser['uid'] ?? $currentUser['id'] ?? null;
$notificationId = sanitize($_GET['id'] ?? '');
$notificationService = new NotificationService();
$notification = $notificationService->findForUser($notificationId, $currentUserId);

if (!$notification) {
    flash('error', 'Notification not found.');
    redirect(base_url('index.php?route=/views/houseparent/notifications/index.php'));
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'mark_read') {
    $result = $notificationService->markAsRead($notificationId, $currentUserId);
    flash($result['success'] ? 'success' : 'error', $result['message']);
    redirect(base_url('index.php?route=/views/houseparent/notifications/detail.php&id=' . urlencode($notificationId)));
}

if (empty($notification['read'])) {
    $notificationService->markAsRead($notificationId, $currentUserId);
    $notification['read'] = true;
}

$pageTitle = $notification['title'] ?? 'Notification details';
$navItems = [
    ['icon' => 'bi-speedometer2', 'label' => 'Dashboard', 'href' => url('views/houseparent/dashboard/index.php')],
    ['icon' => 'bi-mortarboard', 'label' => 'Students', 'href' => url('views/houseparent/students/index.php')],
    ['icon' => 'bi-calendar-check', 'label' => 'Attendance', 'href' => url('views/houseparent/attendance/index.php')],
    ['icon' => 'bi-door-open', 'label' => 'Rooms', 'href' => url('views/houseparent/rooms/index.php')],
    ['icon' => 'bi-people', 'label' => 'Visitors', 'href' => url('views/houseparent/visitors/index.php')],
    ['icon' => 'bi-shield-exclamation', 'label' => 'Incidents', 'href' => url('views/houseparent/incidents/index.php')],
    ['icon' => 'bi-bell', 'label' => 'Notifications', 'href' => url('views/houseparent/notifications/index.php'), 'active' => true],
];

require APP_ROOT . '/app/views/components/header.php';
require APP_ROOT . '/app/views/components/sidebar.php';
?>
<div class="main-content">
    <?php require APP_ROOT . '/app/views/components/navbar.php'; ?>
    <?php require APP_ROOT . '/app/views/components/alerts.php'; ?>
    <div class="content-wrapper">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <div>
                <a href="<?= e(url('views/houseparent/notifications/index.php')) ?>" class="small text-decoration-none">
                    <i class="bi bi-arrow-left me-1" aria-hidden="true"></i> Back to notifications
                </a>
                <h5 class="mt-2 mb-0"><?= e((string) ($notification['title'] ?? 'Notification details')) ?></h5>
            </div>
            <span class="badge bg-success bg-opacity-10 text-success">Read</span>
        </div>

        <article class="card stat-card p-4">
            <div class="d-flex flex-wrap gap-2 mb-3">
                <span class="badge bg-primary bg-opacity-10 text-primary">
                    <i class="bi bi-tag me-1" aria-hidden="true"></i><?= e((string) ($notification['type'] ?? 'info')) ?>
                </span>
                <?php if (!empty($notification['createdAt'])): ?>
                    <span class="text-muted small">
                        <i class="bi bi-clock me-1" aria-hidden="true"></i><?= e((string) $notification['createdAt']) ?>
                    </span>
                <?php endif; ?>
            </div>
            <p class="mb-4" style="white-space: pre-line;"><?= e((string) ($notification['message'] ?? '')) ?></p>
            <div>
                <a href="<?= e(url('views/houseparent/notifications/index.php')) ?>" class="btn btn-primary btn-sm">
                    <i class="bi bi-list-ul me-1" aria-hidden="true"></i> All notifications
                </a>
            </div>
        </article>
    </div>
</div>
<?php require APP_ROOT . '/app/views/components/footer.php'; ?>
