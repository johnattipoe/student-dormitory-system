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
$allowedRoles = [ROLE_STUDENT];
require APP_ROOT . '/app/middleware/RoleMiddleware/RoleMiddleware.php';

use App\Services\NotificationService;

$userId = current_user()['uid'] ?? current_user()['id'] ?? null;
$id = sanitize($_GET['id'] ?? '');
$service = new NotificationService();
$notification = $service->findForUser($id, $userId);

if (!$notification) {
    flash('error', 'Notification not found.');
    redirect(url('views/student/notifications/index/index.php'));
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && empty($notification['read'])) {
    $result = $service->markAsRead($id, $userId);
    flash($result['success'] ? 'success' : 'error', $result['message']);
    redirect(url('views/student/notifications/view/view.php?id=' . urlencode($id)));
}

$pageTitle = 'Notification Details';
$navItems = [
    ['icon' => 'bi-speedometer2', 'label' => 'Dashboard', 'href' => url('views/student/dashboard/index.php')],
    ['icon' => 'bi-bell', 'label' => 'Notifications', 'href' => url('views/student/notifications/index/index.php'), 'active' => true],
];
require APP_ROOT . '/app/views/components/header/header.php';
require APP_ROOT . '/app/views/components/sidebar/sidebar.php';
?>
<div class="main-content">
    <?php require APP_ROOT . '/app/views/components/navbar/navbar.php'; ?>
    <div class="content-wrapper">
        <div class="card stat-card p-4" style="max-width:760px">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <div>
                    <span class="badge bg-info mb-2"><?= e(ucfirst($notification['type'] ?? 'info')) ?></span>
                    <h5 class="mb-0"><?= e($notification['title'] ?? 'Notification') ?></h5>
                </div>
                <a class="btn btn-outline-secondary btn-sm" href="<?= url('views/student/notifications/index/index.php') ?>">Back</a>
            </div>
            <hr>
            <p style="white-space: pre-wrap;"><?= e($notification['message'] ?? '') ?></p>
            <div class="d-flex justify-content-between align-items-center mt-4">
                <span class="badge <?= !empty($notification['read']) ? 'bg-success' : 'bg-warning text-dark' ?>">
                    <?= !empty($notification['read']) ? 'Read' : 'Unread' ?>
                </span>
                <?php if (empty($notification['read'])): ?>
                    <form method="POST">
                        <button class="btn btn-primary btn-sm"><i class="bi bi-check2 me-1"></i> Mark as read</button>
                    </form>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>
<?php require APP_ROOT . '/app/views/components/footer/footer.php'; ?>