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

$notificationService = new NotificationService();
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $currentUser = current_user();
    $currentUserId = $currentUser['uid'] ?? $currentUser['id'] ?? null;
    $action = sanitize($_POST['action'] ?? '');

    if ($action === 'mark_read') {
        $result = $notificationService->markAsRead(
            sanitize($_POST['id'] ?? ''),
            $currentUserId
        );
        flash($result['success'] ? 'success' : 'error', $result['message']);
        redirect(base_url('index.php?route=/views/houseparent/notifications/index.php'));
    }

    if ($action === 'mark_all_read') {
        $result = $notificationService->markAllAsRead($currentUserId);
        flash($result['success'] ? 'success' : 'error', $result['message']);
        redirect(base_url('index.php?route=/views/houseparent/notifications/index.php'));
    }
}

$pageTitle = 'Houseparent Notifications';
$currentUserId = current_user()['uid'] ?? current_user()['id'] ?? null;
$notifications = $currentUserId ? $notificationService->forUser($currentUserId) : [];
$unreadNotifications = array_values(array_filter($notifications, fn($note) => empty($note['read'])));
$readNotifications = array_values(array_filter($notifications, fn($note) => !empty($note['read'])));
$notificationCount = count($notifications);
$unreadCount = count($unreadNotifications);
$readCount = count($readNotifications);
$unreadRate = $notificationCount > 0 ? round(($unreadCount / $notificationCount) * 100) : 0;

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
                <h5 class="mb-0">Notifications</h5>
                <span class="badge bg-secondary bg-opacity-10 text-secondary"><?= $notificationCount ?> items</span>
            </div>
            <?php if ($unreadCount > 0): ?>
                <form method="POST" class="d-inline">
                    <input type="hidden" name="action" value="mark_all_read">
                    <button type="submit" class="btn btn-outline-primary btn-sm">
                        <i class="bi bi-check2-all me-1" aria-hidden="true"></i> Mark all read
                    </button>
                </form>
            <?php endif; ?>
        </div>

        <div class="row g-3 mb-4">
            <div class="col-md-4">
                <div class="card stat-card p-3 h-100">
                    <div class="d-flex justify-content-between align-items-start">
                        <div>
                            <div class="text-muted small">Total notifications</div>
                            <div class="fs-2 fw-bold"><?= e((string) $notificationCount) ?></div>
                        </div>
                        <span class="rounded-circle bg-primary bg-opacity-10 text-primary p-2" aria-hidden="true">
                            <i class="bi bi-bell fs-5"></i>
                        </span>
                    </div>
                    <div class="small text-muted mt-2">All messages assigned to you</div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card stat-card p-3 h-100">
                    <div class="d-flex justify-content-between align-items-start">
                        <div>
                            <div class="text-muted small">Unread</div>
                            <div class="fs-2 fw-bold text-warning"><?= e((string) $unreadCount) ?></div>
                        </div>
                        <span class="rounded-circle bg-warning bg-opacity-10 text-warning p-2" aria-hidden="true">
                            <i class="bi bi-envelope-exclamation fs-5"></i>
                        </span>
                    </div>
                    <div class="small text-muted mt-2">Requires your attention</div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card stat-card p-3 h-100">
                    <div class="d-flex justify-content-between align-items-start">
                        <div>
                            <div class="text-muted small">Read</div>
                            <div class="fs-2 fw-bold text-success"><?= e((string) $readCount) ?></div>
                        </div>
                        <span class="rounded-circle bg-success bg-opacity-10 text-success p-2" aria-hidden="true">
                            <i class="bi bi-check2-all fs-5"></i>
                        </span>
                    </div>
                    <div class="small text-muted mt-2"><?= e((string) $unreadRate) ?>% of your messages are unread</div>
                </div>
            </div>
        </div>

        <div class="card stat-card p-3">
            <table class="table table-hover data-table w-100">
                <thead>
                    <tr>
                        <th>Title</th>
                        <th>Type</th>
                        <th>Message</th>
                        <th>Read</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!empty($notifications)): ?>
                        <?php foreach ($notifications as $note): ?>
                            <tr>
                                <td>
                                    <a href="<?= e(url('views/houseparent/notifications/detail.php?id=' . urlencode((string) ($note['id'] ?? '')))) ?>" class="fw-semibold text-decoration-none">
                                        <?= e($note['title'] ?? 'Untitled notification') ?>
                                    </a>
                                </td>
                                <td><?= e($note['type'] ?? 'info') ?></td>
                                <td><?= e($note['message'] ?? '') ?></td>
                                <td><?= !empty($note['read']) ? 'Yes' : 'No' ?></td>
                                <td>
                                    <?php if (empty($note['read'])): ?>
                                        <form method="POST" action="<?= url('views/houseparent/notifications/index.php') ?>" class="d-inline">
                                            <input type="hidden" name="action" value="mark_read">
                                            <input type="hidden" name="id" value="<?= e((string) ($note['id'] ?? '')) ?>">
                                            <button type="submit" class="btn btn-sm btn-outline-primary">Mark read</button>
                                        </form>
                                    <?php else: ?>
                                        <span class="text-muted">Read</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="5" class="text-center text-muted">No notifications available for your account.</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
<?php require APP_ROOT . '/app/views/components/footer.php'; ?>