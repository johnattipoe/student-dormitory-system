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
$users = (new UserService())->all();
$navItems = [
    ['icon' => 'bi-speedometer2', 'label' => 'Dashboard', 'href' => url('views/admin/dashboard.php')],
    ['icon' => 'bi-bell', 'label' => 'Notifications', 'href' => url('views/admin/notifications/index.php'), 'active' => true],
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
            <span class="badge bg-secondary bg-opacity-10 text-secondary"><?= count($notifications) ?> items</span>
        </div>

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
                    <tr>
                        <td><?= e($note['title'] ?? '') ?></td>
                        <td><?= e($note['type'] ?? 'info') ?></td>
                        <td><?= e($note['message'] ?? '') ?></td>
                        <td><?= !empty($note['read']) ? 'Yes' : 'No' ?></td>
                        <td><?= e($note['userId'] ?? '-') ?></td>
                        <td>
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