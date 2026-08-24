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
$allowedRoles = [ROLE_NURSE];
require APP_ROOT . '/app/middleware/RoleMiddleware.php';

use App\Services\NotificationService;

$pageTitle = 'Nurse Notifications';
$notificationService = new NotificationService();
$userId = current_user_id();
$csrfToken = $_SESSION['nurse_notifications_csrf'] ?? bin2hex(random_bytes(32));
$_SESSION['nurse_notifications_csrf'] = $csrfToken;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!hash_equals($csrfToken, (string) ($_POST['csrf_token'] ?? ''))) {
        flash('error', 'The form expired. Please refresh the page and try again.');
    } else {
        $action = (string) ($_POST['action'] ?? '');
        $result = $action === 'mark_all'
            ? $notificationService->markAllAsRead($userId)
            : ($action === 'mark_read' ? $notificationService->markAsRead(sanitize($_POST['id'] ?? ''), $userId) : null);
        if ($result) {
            flash($result['success'] ? 'success' : 'error', $result['message']);
        }
    }
    redirect(url('views/nurse/notifications/notifications.php'));
}

$notifications = $notificationService->forUser($userId);
$filter = strtolower(sanitize($_GET['filter'] ?? 'all'));
$typeFilter = strtolower(sanitize($_GET['type'] ?? 'all'));
$notifications = array_values(array_filter($notifications, static function (array $notification) use ($filter, $typeFilter): bool {
    $isRead = !empty($notification['read']);
    $type = strtolower((string) ($notification['type'] ?? 'info'));
    return ($filter === 'all' || ($filter === 'unread' && !$isRead) || ($filter === 'read' && $isRead))
        && ($typeFilter === 'all' || $type === $typeFilter);
}));
usort($notifications, static fn(array $first, array $second): int => strcmp((string) ($second['createdAt'] ?? ''), (string) ($first['createdAt'] ?? '')));
$allUserNotifications = $notificationService->forUser($userId);
$unreadCount = count(array_filter($allUserNotifications, static fn(array $notification): bool => empty($notification['read'])));
$types = array_values(array_unique(array_filter(array_map(static fn(array $notification): string => strtolower((string) ($notification['type'] ?? 'info')), $allUserNotifications))));
$navItems = [
    ['icon' => 'bi-speedometer2', 'label' => 'Dashboard', 'href' => url('views/nurse/dashboard/dashboard.php')],
    ['icon' => 'bi-people', 'label' => 'Students', 'href' => url('views/nurse/students/students.php')],
    ['icon' => 'bi-journal-medical', 'label' => 'Medical Records', 'href' => url('views/nurse/medical-records/medical-records.php')],
    ['icon' => 'bi-exclamation-triangle', 'label' => 'Emergency Cases', 'href' => url('views/nurse/emergency-cases/emergency-cases.php')],
    ['icon' => 'bi-bell', 'label' => 'Notifications', 'href' => url('views/nurse/notifications/notifications.php'), 'active' => true],
];

require APP_ROOT . '/app/views/components/header.php';
require APP_ROOT . '/app/views/components/sidebar.php';
?>
<div class="main-content">
    <?php require APP_ROOT . '/app/views/components/navbar.php'; ?>
    <?php require APP_ROOT . '/app/views/components/alerts.php'; ?>
    <div class="content-wrapper nurse-portal">
        <section class="nurse-hero mb-4">
            <div class="nurse-hero-icon"><i class="bi bi-bell"></i></div>
            <div>
                <span class="nurse-kicker">Care desk</span>
                <h1>Notifications</h1>
                <p>Stay current with clinical alerts, follow-ups, and dormitory updates.</p>
                <div class="nurse-badges"><span class="badge bg-danger"><i class="bi bi-envelope me-1"></i><?= e((string) $unreadCount) ?> unread</span><span class="badge bg-success"><i class="bi bi-check2-all me-1"></i><?= e((string) count($allUserNotifications)) ?> total</span></div>
            </div>
            <form method="POST" class="nurse-hero-actions">
                <input type="hidden" name="action" value="mark_all">
                <input type="hidden" name="csrf_token" value="<?= e($csrfToken) ?>">
                <button class="btn btn-light" type="submit" <?= $unreadCount === 0 ? 'disabled' : '' ?>><i class="bi bi-check2-all me-1"></i>Mark all read</button>
            </form>
        </section>

        <section class="nurse-card-panel">
            <div class="nurse-card-header">
                <div><span class="nurse-kicker">Inbox</span><h2>Recent updates</h2><p>Review messages assigned to your nurse account.</p></div>
                <form method="GET" class="nurse-filter-bar">
                    <select name="filter" class="form-select form-select-sm" aria-label="Read status filter">
                        <option value="all" <?= $filter === 'all' ? 'selected' : '' ?>>All status</option><option value="unread" <?= $filter === 'unread' ? 'selected' : '' ?>>Unread</option><option value="read" <?= $filter === 'read' ? 'selected' : '' ?>>Read</option>
                    </select>
                    <select name="type" class="form-select form-select-sm" aria-label="Notification type filter"><option value="all">All types</option><?php foreach ($types as $type): ?><option value="<?= e($type) ?>" <?= $typeFilter === $type ? 'selected' : '' ?>><?= e(ucfirst($type)) ?></option><?php endforeach; ?></select>
                    <button class="btn btn-outline-primary btn-sm" type="submit"><i class="bi bi-funnel"></i><span class="visually-hidden">Filter</span></button>
                </form>
            </div>
            <div class="table-responsive">
            <table class="table table-hover align-middle nurse-data-table w-100">
                <thead>
                    <tr>
                        <th>Title</th>
                        <th>Message</th>
                        <th>Type</th>
                        <th>Created</th>
                        <th class="text-end">Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!empty($notifications)): ?>
                        <?php foreach ($notifications as $note): ?>
                            <?php $noteType = strtolower((string) ($note['type'] ?? 'info')); $isRead = !empty($note['read']); ?>
                            <tr class="<?= $isRead ? '' : 'notification-unread' ?>">
                                <td><strong><?= e($note['title'] ?? 'Notification') ?></strong><?php if (!$isRead): ?><span class="notification-dot" aria-label="Unread"></span><?php endif; ?></td>
                                <td class="notification-message"><?= e($note['message'] ?? 'No message provided.') ?></td>
                                <td><span class="badge nurse-type-badge type-<?= e(preg_replace('/[^a-z0-9-]/', '', $noteType)) ?>"><?= e(ucfirst($noteType)) ?></span></td>
                                <td class="text-nowrap"><?= e($note['createdAt'] ?? '—') ?></td>
                                <td class="text-end"><?php if (!$isRead && !empty($note['id'])): ?><form method="POST"><input type="hidden" name="action" value="mark_read"><input type="hidden" name="id" value="<?= e($note['id']) ?>"><input type="hidden" name="csrf_token" value="<?= e($csrfToken) ?>"><button class="btn btn-sm btn-outline-success" type="submit"><i class="bi bi-check2 me-1"></i>Mark read</button></form><?php else: ?><span class="text-muted small"><i class="bi bi-check2-all me-1"></i>Read</span><?php endif; ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="5" class="text-center text-muted py-5"><i class="bi bi-bell-slash fs-3 d-block mb-2"></i>No notifications match this view.</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
            </div>
        </div>
    </div>
</div>
<?php require APP_ROOT . '/app/views/components/footer.php'; ?>
