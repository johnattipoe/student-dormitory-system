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
$allowedRoles = [ROLE_SENIOR_HOUSEPARENT];
require APP_ROOT . '/app/middleware/RoleMiddleware/RoleMiddleware.php';

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
        redirect(base_url('index.php?route=/views/senior-houseparent/notifications/index/index.php'));
    }

    if ($action === 'mark_all_read') {
        $result = $notificationService->markAllAsRead($currentUserId);
        flash($result['success'] ? 'success' : 'error', $result['message']);
        redirect(base_url('index.php?route=/views/senior-houseparent/notifications/index/index.php'));
    }
}

$pageTitle = 'Senior Houseparent Notifications';
$currentUserId = current_user()['uid'] ?? current_user()['id'] ?? null;
$notifications = $currentUserId ? $notificationService->forUser($currentUserId) : [];
$notificationSearch = strtolower(sanitize($_GET['search'] ?? ''));
$notificationRead = sanitize($_GET['read'] ?? '');
if ($notificationSearch !== '' || $notificationRead !== '') {
    $notifications = array_values(array_filter($notifications, function ($notification) use ($notificationSearch, $notificationRead) {
        return ($notificationSearch === '' || str_contains(strtolower((string) ($notification['title'] ?? '')), $notificationSearch) || str_contains(strtolower((string) ($notification['message'] ?? '')), $notificationSearch))
            && ($notificationRead === '' || ($notificationRead === 'unread' ? empty($notification['read']) : !empty($notification['read'])));
    }));
}
$page = max(1, (int) ($_GET['page'] ?? 1));
$limit = max(1, min(150, (int) ($_GET['limit'] ?? app_config()['pagination_per_page'] ?? 25)));
$totalNotifications = count($notifications);
$totalPages = max(1, (int) ceil($totalNotifications / $limit));
$page = min($page, $totalPages);
$notifications = array_slice($notifications, ($page - 1) * $limit, $limit);
$unreadNotifications = array_values(array_filter($notifications, fn($note) => empty($note['read'])));
$readNotifications = array_values(array_filter($notifications, fn($note) => !empty($note['read'])));
$notificationCount = count($notifications);
$unreadCount = count($unreadNotifications);
$readCount = count($readNotifications);
$unreadRate = $notificationCount > 0 ? round(($unreadCount / $notificationCount) * 100) : 0;

$navItems = [
    ['icon' => 'bi-speedometer2', 'label' => 'Dashboard', 'href' => url('views/senior-houseparent/dashboard/index.php')],
    ['icon' => 'bi-mortarboard', 'label' => 'Students', 'href' => url('views/senior-houseparent/students/index/index.php')],
    ['icon' => 'bi-calendar-check', 'label' => 'Attendance', 'href' => url('views/senior-houseparent/attendance/index/index.php')],
    ['icon' => 'bi-door-open', 'label' => 'Rooms', 'href' => url('views/senior-houseparent/rooms/index/index.php')],
    ['icon' => 'bi-people', 'label' => 'Visitors', 'href' => url('views/senior-houseparent/visitors/index/index.php')],
    ['icon' => 'bi-shield-exclamation', 'label' => 'Incidents', 'href' => url('views/senior-houseparent/incidents/index/index.php')],
    ['icon' => 'bi-bell', 'label' => 'Notifications', 'href' => url('views/senior-houseparent/notifications/index/index.php'), 'active' => true],
    ['icon' => 'bi-chat-left-text', 'label' => 'Message Parents', 'href' => url('views/parent-messages/create/create.php')],
];

require APP_ROOT . '/app/views/components/header/header.php';
require APP_ROOT . '/app/views/components/sidebar/sidebar.php';
?>
<div class="main-content">
    <?php require APP_ROOT . '/app/views/components/navbar/navbar.php'; ?>
    <?php require APP_ROOT . '/app/views/components/alerts/alerts.php'; ?>
    <div class="content-wrapper">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <div class="d-flex align-items-center gap-2">
                <h5 class="mb-0">Notifications</h5>
                <span class="badge bg-secondary bg-opacity-10 text-secondary"><?= $notificationCount ?> items</span>
            </div>
            <div class="d-flex align-items-center gap-2">
                <a href="<?= url('views/senior-houseparent/notifications/send/send.php') ?>" class="btn btn-primary btn-sm"><i class="bi bi-send me-1" aria-hidden="true"></i> Send message</a>
                <?php if ($unreadCount > 0): ?>
                    <form method="POST" class="d-inline">
                        <input type="hidden" name="action" value="mark_all_read">
                        <button type="submit" class="btn btn-outline-primary btn-sm">
                            <i class="bi bi-check2-all me-1" aria-hidden="true"></i> Mark all read
                        </button>
                    </form>
                <?php endif; ?>
            </div>
        </div>

        <div class="card stat-card p-3 mb-3"><form method="GET" class="row g-2"><div class="col-md-7"><input name="search" class="form-control form-control-sm" placeholder="Search title or message" value="<?= e($notificationSearch) ?>"></div><div class="col-md-3"><select name="read" class="form-select form-select-sm"><option value="">All notifications</option><option value="unread" <?= $notificationRead === 'unread' ? 'selected' : '' ?>>Unread</option><option value="read" <?= $notificationRead === 'read' ? 'selected' : '' ?>>Read</option></select></div><div class="col-md-2"><button class="btn btn-primary btn-sm">Filter</button></div></form></div>

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
            <div class="d-flex justify-content-between align-items-center mb-2">
                <small class="text-muted">Showing <?= e((string) count($notifications)) ?> of <?= e((string) $totalNotifications) ?> notifications</small>
            </div>
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
                                    <button type="button" class="btn btn-link p-0 fw-semibold text-decoration-none" data-bs-toggle="modal" data-bs-target="#seniorNotificationView-<?= e((string) ($note['id'] ?? '')) ?>">
                                        <?= e($note['title'] ?? 'Untitled notification') ?>
                                    </button>
                                </td>
                                <td><?= e($note['type'] ?? 'info') ?></td>
                                <td><?= e($note['message'] ?? '') ?></td>
                                <td><?= !empty($note['read']) ? 'Yes' : 'No' ?></td>
                                <td>
                                    <button type="button" class="btn btn-sm btn-outline-secondary" data-bs-toggle="modal" data-bs-target="#seniorNotificationView-<?= e((string) ($note['id'] ?? '')) ?>">View</button>
                                    <button type="button" class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#seniorNotificationEdit-<?= e((string) ($note['id'] ?? '')) ?>">Edit</button>
                                    <button type="button" class="btn btn-sm btn-outline-danger" data-bs-toggle="modal" data-bs-target="#seniorNotificationDelete-<?= e((string) ($note['id'] ?? '')) ?>">Delete</button>
                                    <?php if (empty($note['read'])): ?>
                                        <form method="POST" action="<?= url('views/senior-houseparent/notifications/index/index.php') ?>" class="d-inline">
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

        <?php require APP_ROOT . '/app/views/components/pagination/pagination.php'; ?>
    </div>
</div>
<?php foreach ($notifications as $note): ?>
    <?php $modalNotificationId = (string) ($note['id'] ?? ''); $modalNotificationType = (string) ($note['type'] ?? 'info'); ?>
    <div class="modal fade" id="seniorNotificationView-<?= e($modalNotificationId) ?>" tabindex="-1" aria-hidden="true"><div class="modal-dialog modal-dialog-centered"><div class="modal-content"><div class="modal-header"><h5 class="modal-title">Notification Details</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div><div class="modal-body"><h5><?= e($note['title'] ?? 'Notification') ?></h5><p style="white-space:pre-line;"><?= e($note['message'] ?? '') ?></p></div><div class="modal-footer"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button></div></div></div></div>
    <div class="modal fade" id="seniorNotificationEdit-<?= e($modalNotificationId) ?>" tabindex="-1" aria-hidden="true"><div class="modal-dialog modal-dialog-centered modal-lg"><div class="modal-content"><form method="POST" action="<?= url('views/senior-houseparent/notifications/edit/edit.php?id=' . urlencode($modalNotificationId)) ?>"><div class="modal-header"><h5 class="modal-title">Edit Notification</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div><div class="modal-body"><input type="hidden" name="id" value="<?= e($modalNotificationId) ?>"><label class="form-label">Title</label><input name="title" class="form-control mb-3" value="<?= e($note['title'] ?? '') ?>" required><label class="form-label">Type</label><select name="type" class="form-select mb-3"><option value="info" <?= $modalNotificationType === 'info' ? 'selected' : '' ?>>Info</option><option value="success" <?= $modalNotificationType === 'success' ? 'selected' : '' ?>>Success</option><option value="warning" <?= $modalNotificationType === 'warning' ? 'selected' : '' ?>>Warning</option><option value="danger" <?= $modalNotificationType === 'danger' ? 'selected' : '' ?>>Danger</option></select><label class="form-label">Message</label><textarea name="message" class="form-control" rows="4" required><?= e($note['message'] ?? '') ?></textarea></div><div class="modal-footer"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button><button type="submit" class="btn btn-primary">Save changes</button></div></form></div></div></div>
    <div class="modal fade" id="seniorNotificationDelete-<?= e($modalNotificationId) ?>" tabindex="-1" aria-hidden="true"><div class="modal-dialog modal-dialog-centered"><div class="modal-content"><div class="modal-header border-0"><h5 class="modal-title text-danger">Delete Notification</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div><div class="modal-body">Delete <strong><?= e($note['title'] ?? 'this notification') ?></strong>?</div><div class="modal-footer border-0"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button><form method="POST" action="<?= url('views/senior-houseparent/notifications/delete/delete.php?id=' . urlencode($modalNotificationId)) ?>"><input type="hidden" name="id" value="<?= e($modalNotificationId) ?>"><button type="submit" class="btn btn-danger">Delete</button></form></div></div></div></div>
<?php endforeach; ?>
<?php require APP_ROOT . '/app/views/components/footer/footer.php'; ?>