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
$id = sanitize($_GET['id'] ?? $_POST['id'] ?? '');
$service = new NotificationService();
$notification = $service->findForUser($id, $currentUserId);
if (!$notification) {
    flash('error', 'Notification not found for your account.');
    redirect(url('views/senior-houseparent/notifications/index/index.php'));
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $result = $service->deleteForUser($id, $currentUserId);
    flash($result['success'] ? 'success' : 'error', $result['message']);
    redirect(url('views/senior-houseparent/notifications/index/index.php'));
}

$pageTitle = 'Delete Notification';
$navItems = [['icon' => 'bi-bell', 'label' => 'Notifications', 'href' => url('views/senior-houseparent/notifications/index/index.php'), 'active' => true]];
require APP_ROOT . '/app/views/components/header/header.php';
require APP_ROOT . '/app/views/components/sidebar/sidebar.php';
?>
<div class="main-content">
    <?php require APP_ROOT . '/app/views/components/navbar/navbar.php'; ?>
    <div class="content-wrapper">
        <div class="card stat-card p-4" style="max-width:760px">
            <h5 class="mb-3">Delete Notification</h5>
            <p>Delete <strong><?= e($notification['title'] ?? 'this notification') ?></strong>?</p>
            <form method="POST">
                <input type="hidden" name="id" value="<?= e($id) ?>">
                <button class="btn btn-danger">Delete notification</button>
                <a class="btn btn-outline-secondary" href="<?= url('views/senior-houseparent/notifications/view/view.php?id=' . urlencode($id)) ?>">Cancel</a>
            </form>
        </div>
    </div>
</div>
<?php require APP_ROOT . '/app/views/components/footer/footer.php'; ?>
