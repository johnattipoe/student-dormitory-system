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
$allowedRoles = [ROLE_ADMIN];
require APP_ROOT . '/app/middleware/RoleMiddleware.php';

use App\Services\NotificationService;
use App\Services\UserService;

$notificationService = new NotificationService();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (($_POST['action'] ?? '') === 'mark_all_read') {
        foreach ($notificationService->all() as $notification) {
            if (!empty($notification['id'])) $notificationService->markAsReadById((string) $notification['id']);
        }
        flash('success', 'All notifications marked as read.');
        redirect(base_url('index.php?route=/views/admin/notifications/index.php'));
    }
    if (($_POST['action'] ?? '') === 'mark_read') {
        $result = $notificationService->markAsReadById(sanitize($_POST['id'] ?? ''));
        flash($result['success'] ? 'success' : 'error', $result['message']);
        redirect(base_url('index.php?route=/views/admin/notifications/index.php'));
    }

    $data = [
        'title' => sanitize($_POST['title'] ?? ''),
        'message' => sanitize($_POST['message'] ?? ''),
        'type' => sanitize($_POST['type'] ?? 'info'),
        'recipientType' => sanitize($_POST['recipientType'] ?? 'user'),
        'role' => sanitize($_POST['role'] ?? ''),
        'userId' => sanitize($_POST['userId'] ?? ''),
    ];

    $result = $notificationService->create($data);
    flash($result['success'] ? 'success' : 'error', $result['message']);
    redirect(base_url('index.php?route=/views/admin/notifications/index.php'));
}

$pageTitle = 'Notifications';
$notifications = $notificationService->all();
$search = strtolower(sanitize($_GET['search'] ?? ''));
$readFilter = sanitize($_GET['read'] ?? '');
if ($search !== '' || $readFilter !== '') {
    $notifications = array_values(array_filter($notifications, function ($notification) use ($search, $readFilter) {
        return ($search === '' || str_contains(strtolower((string) ($notification['title'] ?? '')), $search) || str_contains(strtolower((string) ($notification['message'] ?? '')), $search))
            && ($readFilter === '' || ($readFilter === 'unread' ? empty($notification['read']) : !empty($notification['read'])));
    }));
}
$users = (new UserService())->all();
$userMap = [];
foreach ($users as $user) {
    $userName = $user['name'] ?? $user['email'] ?? null;
    if ($userName) {
        foreach ([$user['id'] ?? null, $user['uid'] ?? null] as $userId) {
            if ($userId !== null && $userId !== '') {
                $userMap[(string) $userId] = $userName;
            }
        }
    }
}
$unreadCount = count(array_filter($notifications, fn($notification) => empty($notification['read'])));
$navItems = [
    ['icon' => 'bi-speedometer2', 'label' => 'Dashboard', 'href' => url('views/admin/dashboard.php')],
    ['icon' => 'bi-bell', 'label' => 'Notifications', 'href' => url('views/admin/notifications/index.php'), 'active' => true],
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
            <div><span class="badge bg-secondary bg-opacity-10 text-secondary"><?= count($notifications) ?> items</span> <span class="badge bg-warning text-dark"><?= $unreadCount ?> unread</span><?php if ($unreadCount > 0): ?><form method="POST" class="d-inline ms-2"><input type="hidden" name="action" value="mark_all_read"><button class="btn btn-sm btn-outline-primary">Mark all read</button></form><?php endif; ?></div>
        </div>
        <div class="card stat-card p-3 mb-3"><form method="GET" class="row g-2"><div class="col-md-8"><input name="search" class="form-control form-control-sm" placeholder="Search notifications" value="<?= e($search) ?>"></div><div class="col-md-2"><select name="read" class="form-select form-select-sm"><option value="">All</option><option value="unread" <?= $readFilter === 'unread' ? 'selected' : '' ?>>Unread</option><option value="read" <?= $readFilter === 'read' ? 'selected' : '' ?>>Read</option></select></div><div class="col-md-2"><button class="btn btn-primary btn-sm">Filter</button></div></form></div>

        <div class="card stat-card p-4 mb-4">
            <h6 class="mb-3">Create notification</h6>
            <form method="POST" action="<?= url('views/admin/notifications/index.php') ?>" class="row g-3">
                <div class="col-md-4">
                    <label class="form-label">Title</label>
                    <input name="title" class="form-control" required>
                </div>
                <div class="col-md-3">
                    <label class="form-label">Type</label>
                    <select name="type" class="form-select">
                        <option value="info">Info</option>
                        <option value="success">Success</option>
                        <option value="warning">Warning</option>
                        <option value="danger">Danger</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label">Send to</label>
                    <select name="recipientType" class="form-select" id="recipientType">
                        <option value="user">Single user</option>
                        <option value="role">Role</option>
                        <option value="all">All users</option>
                    </select>
                </div>
                <div class="col-md-2 d-flex align-items-end">
                    <button class="btn btn-primary w-100">Send</button>
                </div>
                <div class="col-md-6">
                    <label class="form-label">User</label>
                    <select name="userId" class="form-select">
                        <option value="">Select user</option>
                        <?php foreach ($users as $user): ?>
                            <option value="<?= e((string) ($user['uid'] ?? $user['id'] ?? '')) ?>"><?= e((string) ($user['name'] ?? ($user['email'] ?? 'Unknown'))) ?> (<?= e((string) ($user['role'] ?? '')) ?>)</option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Role</label>
                    <select name="role" class="form-select">
                        <option value="">Select role</option>
                        <?php foreach (['admin', 'house_master', 'houseparent', 'security', 'nurse', 'student'] as $role): ?>
                            <option value="<?= e($role) ?>"><?= ucfirst(str_replace('_', ' ', $role)) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-12">
                    <label class="form-label">Message</label>
                    <textarea name="message" class="form-control" rows="3" required></textarea>
                </div>
            </form>
        </div>

        <div class="card stat-card p-3">
            <table class="table table-hover data-table w-100">
                <thead>
                <tr>
                    <th>Title</th>
                    <th>Type</th>
                    <th>Message</th>
                    <th>Read</th>
                    <th>User</th>
                    <th>Action</th>
                </tr>
                </thead>
                <tbody>
                <?php foreach ($notifications as $note): ?>
                    <?php $notificationUserId = (string) ($note['userId'] ?? ''); ?>
                    <tr>
                        <td><a class="fw-semibold text-decoration-none" href="<?= url('views/admin/notifications/view.php?id=' . urlencode((string) ($note['id'] ?? ''))) ?>"><?= e($note['title'] ?? '') ?></a></td>
                        <td><?= e($note['type'] ?? 'info') ?></td>
                        <td><?= e($note['message'] ?? '') ?></td>
                        <td><?= !empty($note['read']) ? 'Yes' : 'No' ?></td>
                        <td><?= e($userMap[$notificationUserId] ?? ($note['userId'] ?? '-')) ?></td>
                        <td>
                            <a class="btn btn-sm btn-outline-secondary" href="<?= url('views/admin/notifications/view.php?id=' . urlencode((string) ($note['id'] ?? ''))) ?>">View</a>
                            <a class="btn btn-sm btn-outline-primary" href="<?= url('views/admin/notifications/edit.php?id=' . urlencode((string) ($note['id'] ?? ''))) ?>">Edit</a>
                            <a class="btn btn-sm btn-outline-danger" href="<?= url('views/admin/notifications/delete.php?id=' . urlencode((string) ($note['id'] ?? ''))) ?>">Delete</a>
                            <?php if (empty($note['read'])): ?>
                                <form method="POST" action="<?= url('views/admin/notifications/index.php') ?>" class="d-inline">
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
                </tbody>
            </table>
        </div>
    </div>
</div>
<?php require APP_ROOT . '/app/views/components/footer.php'; ?>