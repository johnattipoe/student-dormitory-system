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
$allowedRoles = [ROLE_HOUSE_MASTER, ROLE_HOUSE_MISTRESS];
require APP_ROOT . '/app/middleware/RoleMiddleware.php';

use App\Services\NotificationService;

$notificationService = new NotificationService();
if ($_SERVER['REQUEST_METHOD'] === 'POST' && in_array($_POST['action'] ?? '', ['mark_read', 'mark_all_read'], true)) {
    $currentUserId = current_user()['uid'] ?? current_user()['id'] ?? null;
    $result = ($_POST['action'] ?? '') === 'mark_all_read'
        ? $notificationService->markAllAsRead($currentUserId)
        : $notificationService->markAsRead(sanitize($_POST['id'] ?? ''), $currentUserId);
    flash($result['success'] ? 'success' : 'error', $result['message']);
    redirect(base_url('index.php?route=/views/house-master/notifications/index.php'));
}

$pageTitle = 'House Master Notifications';
$currentUserId = current_user()['uid'] ?? current_user()['id'] ?? null;
$notifications = $currentUserId ? $notificationService->forUser($currentUserId) : [];
$notificationType = sanitize($_GET['type'] ?? '');
$notificationRead = sanitize($_GET['read'] ?? '');
$notificationSearch = strtolower(sanitize($_GET['search'] ?? ''));
$notifications = array_values(array_filter($notifications, function ($note) use ($notificationType, $notificationRead, $notificationSearch) {
    return ($notificationType === '' || ($note['type'] ?? '') === $notificationType)
        && ($notificationRead === '' || (($notificationRead === 'unread') === empty($note['read'])))
        && ($notificationSearch === '' || str_contains(strtolower((string) ($note['title'] ?? '')), $notificationSearch) || str_contains(strtolower((string) ($note['message'] ?? '')), $notificationSearch));
}));
$unreadCount = count(array_filter($notifications, fn($note) => empty($note['read'])));
$navItems = [
    ['icon' => 'bi-speedometer2', 'label' => 'Dashboard', 'href' => url('views/house-master/dashboard/index.php')],
    ['icon' => 'bi-mortarboard', 'label' => 'Students', 'href' => url('views/house-master/students/index.php')],
    ['icon' => 'bi-calendar-check', 'label' => 'Attendance', 'href' => url('views/house-master/attendance/index.php')],
    ['icon' => 'bi-door-closed', 'label' => 'Rooms', 'href' => url('views/house-master/rooms/index.php')],
    ['icon' => 'bi-people', 'label' => 'Visitors', 'href' => url('views/house-master/visitors/index.php')],
    ['icon' => 'bi-flag', 'label' => 'Incidents', 'href' => url('views/house-master/incidents/index.php')],
    ['icon' => 'bi-file-earmark-text', 'label' => 'Reports', 'href' => url('views/house-master/reports/index.php')],
    ['icon' => 'bi-bell', 'label' => 'Notifications', 'href' => url('views/house-master/notifications/index.php'), 'active' => true],
    ['icon' => 'bi-chat-left-text', 'label' => 'Message Parents', 'href' => url('views/parent-messages/create.php')],
];

require APP_ROOT . '/app/views/components/header.php';
require APP_ROOT . '/app/views/components/sidebar.php';
?>
<div class="main-content">
    <?php require APP_ROOT . '/app/views/components/navbar.php'; ?>
    <?php require APP_ROOT . '/app/views/components/alerts.php'; ?>
    <div class="content-wrapper">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <h5 class="mb-0">Notifications</h5>
            <div><span class="badge bg-secondary bg-opacity-10 text-secondary"><?= count($notifications) ?> items</span> <span class="badge bg-warning text-dark"><?= $unreadCount ?> unread</span><form method="POST" class="d-inline ms-2"><input type="hidden" name="action" value="mark_all_read"><button class="btn btn-sm btn-outline-primary">Mark all read</button></form></div>
        </div>

        <div class="card stat-card p-3 mb-3"><form method="GET" class="row g-2"><div class="col-md-5"><input name="search" class="form-control form-control-sm" placeholder="Search notifications" value="<?= e($notificationSearch) ?>"></div><div class="col-md-3"><select name="type" class="form-select form-select-sm"><option value="">All types</option><?php foreach (['info','success','warning','danger'] as $type): ?><option value="<?= e($type) ?>" <?= $notificationType === $type ? 'selected' : '' ?>><?= e(ucfirst($type)) ?></option><?php endforeach; ?></select></div><div class="col-md-2"><select name="read" class="form-select form-select-sm"><option value="">All</option><option value="unread" <?= $notificationRead === 'unread' ? 'selected' : '' ?>>Unread</option><option value="read" <?= $notificationRead === 'read' ? 'selected' : '' ?>>Read</option></select></div><div class="col-md-2"><button class="btn btn-primary btn-sm">Filter</button> <a class="btn btn-outline-secondary btn-sm" href="<?= url('views/house-master/notifications/index.php') ?>">Reset</a></div></form></div>

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
                                <td><?= e($note['title'] ?? '') ?></td>
                                <td><?= e($note['type'] ?? 'info') ?></td>
                                <td><?= e($note['message'] ?? '') ?></td>
                                <td><?= !empty($note['read']) ? 'Yes' : 'No' ?></td>
                                <td>
                                    <a class="btn btn-sm btn-outline-secondary" href="<?= url('views/house-master/notifications/view.php?id=' . urlencode((string) ($note['id'] ?? ''))) ?>">View</a>
                                    <?php if (empty($note['read'])): ?>
                                        <form method="POST" action="<?= url('views/house-master/notifications/index.php') ?>" class="d-inline">
                                            <input type="hidden" name="action" value="mark_read">
                                            <input type="hidden" name="id" value="<?= e((string) ($note['id'] ?? '')) ?>">
                                            <button class="btn btn-sm btn-outline-primary">Mark read</button>
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
