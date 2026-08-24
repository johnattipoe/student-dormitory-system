<?php
require_once dirname(__DIR__, 3) . '/bootstrap.php';
$allowedRoles = [ROLE_HOUSEPARENT];
require APP_ROOT . '/app/middleware/RoleMiddleware.php';

use App\Services\NotificationService;

$currentUserId = current_user()['uid'] ?? current_user()['id'] ?? null;
$id = sanitize($_GET['id'] ?? '');
$service = new NotificationService();
$notification = $service->findForUser($id, $currentUserId);
if (!$notification) {
    flash('error', 'Notification not found for your account.');
    redirect(url('views/houseparent/notifications/index.php'));
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && empty($notification['read'])) {
    $result = $service->markAsRead($id, $currentUserId);
    flash($result['success'] ? 'success' : 'error', $result['message']);
    redirect(url('views/houseparent/notifications/view.php?id=' . urlencode($id)));
}

$pageTitle = 'Notification Details';
$navItems = [['icon' => 'bi-bell', 'label' => 'Notifications', 'href' => url('views/houseparent/notifications/index.php'), 'active' => true]];
require APP_ROOT . '/app/views/components/header.php';
require APP_ROOT . '/app/views/components/sidebar.php';
?>
<div class="main-content">
    <?php require APP_ROOT . '/app/views/components/navbar.php'; ?>
    <div class="content-wrapper">
        <div class="card stat-card p-4" style="max-width:760px">
            <div class="d-flex justify-content-between">
                <div><span class="badge bg-info mb-2"><?= e($notification['type'] ?? 'info') ?></span><h5><?= e($notification['title'] ?? 'Notification') ?></h5></div>
                <a class="btn btn-outline-secondary btn-sm" href="<?= url('views/houseparent/notifications/index.php') ?>">Back</a>
            </div>
            <hr>
            <p><?= nl2br(e($notification['message'] ?? '')) ?></p>
            <div class="d-flex justify-content-between align-items-center">
                <span class="badge <?= !empty($notification['read']) ? 'bg-success' : 'bg-warning text-dark' ?>"><?= !empty($notification['read']) ? 'Read' : 'Unread' ?></span>
                <div>
                    <a class="btn btn-sm btn-outline-primary" href="<?= url('views/houseparent/notifications/edit.php?id=' . urlencode($id)) ?>">Edit</a>
                    <a class="btn btn-sm btn-outline-danger" href="<?= url('views/houseparent/notifications/delete.php?id=' . urlencode($id)) ?>">Delete</a>
                    <?php if (empty($notification['read'])): ?><form method="POST" class="d-inline"><button class="btn btn-primary btn-sm">Mark as read</button></form><?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>
<?php require APP_ROOT . '/app/views/components/footer.php'; ?>
