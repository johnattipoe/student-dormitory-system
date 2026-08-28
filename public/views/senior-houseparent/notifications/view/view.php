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

$currentUserId = current_user()['uid'] ?? current_user()['id'] ?? null;
$id = sanitize($_GET['id'] ?? '');
$service = new NotificationService();
$notification = $service->findForUser($id, $currentUserId);
if (!$notification) {
    flash('error', 'Notification not found for your account.');
    redirect(url('views/senior-houseparent/notifications/index/index.php'));
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && empty($notification['read'])) {
    $result = $service->markAsRead($id, $currentUserId);
    flash($result['success'] ? 'success' : 'error', $result['message']);
    redirect(url('views/senior-houseparent/notifications/view/view.php?id=' . urlencode($id)));
}

$pageTitle = 'Notification Details';
$navItems = [['icon' => 'bi-bell', 'label' => 'Notifications', 'href' => url('views/senior-houseparent/notifications/index/index.php'), 'active' => true]];
require APP_ROOT . '/app/views/components/header/header.php';
require APP_ROOT . '/app/views/components/sidebar/sidebar.php';
?>
<div class="main-content">
    <?php require APP_ROOT . '/app/views/components/navbar/navbar.php'; ?>
    <div class="content-wrapper">
        <div class="card stat-card p-4" style="max-width:760px">
            <div class="d-flex justify-content-between">
                <div><span class="badge bg-info mb-2"><?= e($notification['type'] ?? 'info') ?></span><h5><?= e($notification['title'] ?? 'Notification') ?></h5></div>
                <a class="btn btn-outline-secondary btn-sm" href="<?= url('views/senior-houseparent/notifications/index/index.php') ?>">Back</a>
            </div>
            <hr>
            <p><?= nl2br(e($notification['message'] ?? '')) ?></p>
            <div class="d-flex justify-content-between align-items-center">
                <span class="badge <?= !empty($notification['read']) ? 'bg-success' : 'bg-warning text-dark' ?>"><?= !empty($notification['read']) ? 'Read' : 'Unread' ?></span>
                <div>
                    <a class="btn btn-sm btn-outline-primary" href="<?= url('views/senior-houseparent/notifications/edit/edit.php?id=' . urlencode($id)) ?>">Edit</a>
                    <a class="btn btn-sm btn-outline-danger" href="<?= url('views/senior-houseparent/notifications/delete/delete.php?id=' . urlencode($id)) ?>">Delete</a>
                    <?php if (empty($notification['read'])): ?><form method="POST" class="d-inline"><button class="btn btn-primary btn-sm">Mark as read</button></form><?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>
<?php require APP_ROOT . '/app/views/components/footer/footer.php'; ?>
