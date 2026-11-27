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
require APP_ROOT . '/app/middleware/RoleMiddleware/RoleMiddleware.php';

use App\Services\NotificationService;
use App\Services\UserService;

$notificationService = new NotificationService();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = (string) ($_POST['action'] ?? '');
    if ($action === 'mark_all_read') {
        foreach ($notificationService->all() as $notification) {
            if (!empty($notification['id'])) $notificationService->markAsReadById((string) $notification['id']);
        }
        flash('success', 'All notifications marked as read.');
        redirect(base_url('index.php?route=/views/admin/notifications/index/index.php'));
    }
    if ($action === 'mark_read') {
        $result = $notificationService->markAsReadById(sanitize($_POST['id'] ?? ''));
        flash($result['success'] ? 'success' : 'error', $result['message']);
        redirect(base_url('index.php?route=/views/admin/notifications/index/index.php'));
    }

    if ($action === 'update_notification') {
        $id = sanitize($_POST['id'] ?? '');
        if (!$notificationService->findForAdmin($id)) {
            flash('error', 'Notification not found.');
        } else {
            $result = $notificationService->update($id, [
                'title' => sanitize($_POST['title'] ?? ''),
                'message' => sanitize($_POST['message'] ?? ''),
                'type' => sanitize($_POST['type'] ?? 'info'),
            ]);
            flash($result['success'] ? 'success' : 'error', $result['message']);
        }
    } elseif ($action === 'delete_notification') {
        $id = sanitize($_POST['id'] ?? '');
        if (!$notificationService->findForAdmin($id)) {
            flash('error', 'Notification not found.');
        } else {
            $result = $notificationService->delete($id);
            flash($result['success'] ? 'success' : 'error', $result['message']);
        }
    } elseif ($action === 'create_notification') {
        $result = $notificationService->create([
            'title' => sanitize($_POST['title'] ?? ''),
            'message' => sanitize($_POST['message'] ?? ''),
            'type' => sanitize($_POST['type'] ?? 'info'),
            'recipientType' => sanitize($_POST['recipientType'] ?? 'user'),
            'role' => sanitize($_POST['role'] ?? ''),
            'userId' => sanitize($_POST['userId'] ?? ''),
        ]);
        flash($result['success'] ? 'success' : 'error', $result['message']);
    } else {
        flash('error', 'Unknown notification action.');
    }
    redirect(base_url('index.php?route=/views/admin/notifications/index/index.php'));
}

$pageTitle = 'Notifications';
$page = max(1, (int) ($_GET['page'] ?? 1));
$limit = max(1, min(150, (int) ($_GET['limit'] ?? app_config()['pagination_per_page'] ?? 25)));
$notifications = $notificationService->all($limit);
$totalNotifications = $notificationService->count();
$totalPages = max(1, (int) ceil($totalNotifications / $limit));
$userService = new UserService();
$userMap = [];
$getNotificationUserName = function (string $userId) use (&$userMap, $userService): string {
    if ($userId === '') {
        return '-';
    }

    if (!array_key_exists($userId, $userMap)) {
        $user = $userService->find($userId);
        $userName = $user['name'] ?? $user['email'] ?? null;
        if ($userName) {
            foreach ([$user['id'] ?? null, $user['uid'] ?? null] as $userKey) {
                if ($userKey !== null && $userKey !== '') {
                    $userMap[(string) $userKey] = (string) $userName;
                }
            }
        }
        if (!isset($userMap[$userId])) {
            $userMap[$userId] = $userId;
        }
    }

    return $userMap[$userId];
};
$unreadCount = count(array_filter($notifications, fn($notification) => empty($notification['read'])));
$navItems = [
    ['icon' => 'bi-speedometer2', 'label' => 'Dashboard', 'href' => url('views/admin/dashboard.php')],
    ['icon' => 'bi-bell', 'label' => 'Notifications', 'href' => url('views/admin/notifications/index/index.php'), 'active' => true],
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
            <h5 class="mb-0">Notifications</h5>
            <div class="d-flex align-items-center gap-2 flex-wrap justify-content-end">
                <span class="badge bg-secondary bg-opacity-10 text-secondary"><?= count($notifications) ?> items</span>
                <span class="badge bg-warning text-dark"><?= $unreadCount ?> unread</span>
                <button class="btn btn-sm btn-primary" type="button" data-bs-toggle="modal" data-bs-target="#createNotificationModal">
                    <i class="bi bi-plus-lg me-1" aria-hidden="true"></i>Create notification
                </button>
                <?php if ($unreadCount > 0): ?><form method="POST" class="d-inline"><input type="hidden" name="action" value="mark_all_read"><button class="btn btn-sm btn-outline-primary">Mark all read</button></form><?php endif; ?>
            </div>
        </div>
        <div class="modal fade" id="createNotificationModal" tabindex="-1" aria-labelledby="createNotificationModalLabel" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered modal-lg modal-dialog-scrollable">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title" id="createNotificationModalLabel">Create notification</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <form id="createNotificationForm" method="POST" action="<?= url('views/admin/notifications/index/index.php') ?>" class="row g-3">
                <input type="hidden" name="action" value="create_notification">
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
                <div class="col-md-6">
                    <label class="form-label">User</label>
                    <select name="userId" class="form-select" id="notificationUserId" data-admin-notification-user-options="<?= e(url('views/admin/notifications/user-options.php')) ?>">
                        <option value="">Select user</option>
                    </select>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Role</label>
                    <select name="role" class="form-select" id="notificationRole">
                        <option value="">Select role</option>
                        <?php foreach (['admin', 'house_master', 'senior-houseparent', 'security', 'nurse', 'student'] as $role): ?>
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
                    <div class="modal-footer">
                        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button class="btn btn-primary" type="submit" form="createNotificationForm">Send notification</button>
                    </div>
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
                    <th>User</th>
                    <th>Actions</th>
                </tr>
                </thead>
                <tbody>
                <?php foreach ($notifications as $note): ?>
                    <?php $notificationUserId = (string) ($note['userId'] ?? ''); ?>
                    <tr>
                        <td><button type="button" class="btn btn-link p-0 text-decoration-none fw-semibold" data-bs-toggle="modal" data-bs-target="#notificationViewModal-<?= e((string) ($note['id'] ?? '')) ?>"><?= e($note['title'] ?? '') ?></button></td>
                        <td><?= e($note['type'] ?? 'info') ?></td>
                        <td><?= e($note['message'] ?? '') ?></td>
                        <td><?= !empty($note['read']) ? 'Yes' : 'No' ?></td>
                        <td><?= e($getNotificationUserName($notificationUserId)) ?></td>
                        <td class="notification-actions-cell">
                            <div class="notification-actions">
                                <button type="button" class="notification-action notification-action-view" data-bs-toggle="modal" data-bs-target="#notificationViewModal-<?= e((string) ($note['id'] ?? '')) ?>" title="View notification"><i class="bi bi-eye"></i><span>View</span></button>
                                <button type="button" class="notification-action notification-action-edit" data-bs-toggle="modal" data-bs-target="#notificationEditModal-<?= e((string) ($note['id'] ?? '')) ?>" title="Edit notification"><i class="bi bi-pencil-square"></i><span>Edit</span></button>
                                <button type="button" class="notification-action notification-action-delete" data-bs-toggle="modal" data-bs-target="#notificationDeleteModal-<?= e((string) ($note['id'] ?? '')) ?>" title="Delete notification"><i class="bi bi-trash3"></i><span>Delete</span></button>
                                <?php if (empty($note['read'])): ?>
                                    <form method="POST" action="<?= url('views/admin/notifications/index/index.php') ?>" class="d-inline">
                                    <input type="hidden" name="action" value="mark_read">
                                    <input type="hidden" name="id" value="<?= e((string) ($note['id'] ?? '')) ?>">
                                        <button class="notification-action notification-action-read" type="submit" title="Mark as read"><i class="bi bi-check2-circle"></i><span>Mark read</span></button>
                                    </form>
                                <?php else: ?>
                                    <span class="notification-read-state"><i class="bi bi-check2-all"></i> Read</span>
                                <?php endif; ?>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
            <?php require APP_ROOT . '/app/views/components/pagination/pagination.php'; ?>
        </div>
    </div>
</div>

<?php foreach ($notifications as $note): ?>
    <?php
    $notificationId = (string) ($note['id'] ?? '');
    $notificationTitle = (string) ($note['title'] ?? 'Notification');
    $notificationMessage = (string) ($note['message'] ?? '');
    $notificationType = (string) ($note['type'] ?? 'info');
    ?>
    <div class="modal fade" id="notificationViewModal-<?= e($notificationId) ?>" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Notification Details</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <h6><?= e($notificationTitle) ?></h6>
                    <span class="badge bg-light text-dark border mb-3"><?= e(ucfirst($notificationType)) ?></span>
                    <p class="mb-0"><?= e($notificationMessage) ?></p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="notificationEditModal-<?= e($notificationId) ?>" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-lg">
            <div class="modal-content">
                <form method="POST" action="<?= url('views/admin/notifications/index/index.php') ?>">
                    <input type="hidden" name="action" value="update_notification">
                    <input type="hidden" name="id" value="<?= e($notificationId) ?>">
                    <div class="modal-header">
                        <h5 class="modal-title">Edit Notification</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <div class="mb-3">
                            <label class="form-label">Title</label>
                            <input name="title" class="form-control" value="<?= e($notificationTitle) ?>" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Type</label>
                            <select name="type" class="form-select">
                                <?php foreach (['info', 'success', 'warning', 'danger'] as $type): ?>
                                    <option value="<?= e($type) ?>" <?= ($notificationType === $type) ? 'selected' : '' ?>><?= ucfirst($type) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Message</label>
                            <textarea name="message" class="form-control" rows="5" required><?= e($notificationMessage) ?></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Save changes</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <div class="modal fade" id="notificationDeleteModal-<?= e($notificationId) ?>" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header border-0">
                    <h5 class="modal-title text-danger">Delete Notification</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <p class="mb-0">Are you sure you want to delete <strong><?= e($notificationTitle) ?></strong>?</p>
                </div>
                <div class="modal-footer border-0">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <form method="POST" action="<?= url('views/admin/notifications/index/index.php') ?>">
                        <input type="hidden" name="action" value="delete_notification">
                        <input type="hidden" name="id" value="<?= e($notificationId) ?>">
                        <button type="submit" class="btn btn-danger">Delete</button>
                    </form>
                </div>
            </div>
        </div>
    </div>
<?php endforeach; ?>
<script>
const recipientTypeSelect = document.getElementById('recipientType');
if (recipientTypeSelect) {
    const userSelect = document.getElementById('notificationUserId');
    const roleSelect = document.getElementById('notificationRole');
    const updateRecipientFields = function () {
        userSelect.required = recipientTypeSelect.value === 'user';
        userSelect.disabled = recipientTypeSelect.value !== 'user';
        roleSelect.required = recipientTypeSelect.value === 'role';
        roleSelect.disabled = recipientTypeSelect.value !== 'role';
    };
    recipientTypeSelect.addEventListener('change', updateRecipientFields);
    updateRecipientFields();
}
</script>
<?php require APP_ROOT . '/app/views/components/footer/footer.php'; ?>
