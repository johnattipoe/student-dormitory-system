<?php
require_once dirname(__DIR__, 3) . '/bootstrap.php';
$allowedRoles = [ROLE_ADMIN];
require APP_ROOT . '/app/middleware/RoleMiddleware.php';

use App\Services\NotificationService;

$id = sanitize($_GET['id'] ?? $_POST['id'] ?? '');
$service = new NotificationService();
$notification = $service->findForAdmin($id);
if (!$notification) {
    flash('error', 'Notification not found.');
    redirect(url('views/admin/notifications/index.php'));
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $result = $service->delete($id);
    flash($result['success'] ? 'success' : 'error', $result['message']);
    redirect(url('views/admin/notifications/index.php'));
}

$pageTitle = 'Delete Notification';
$navItems = [
    ['icon' => 'bi-bell', 'label' => 'Notifications', 'href' => url('views/admin/notifications/index.php')],
    ['icon' => 'bi-trash', 'label' => 'Delete Notification', 'href' => url('views/admin/notifications/delete.php?id=' . urlencode($id)), 'active' => true],
];
require APP_ROOT . '/app/views/components/header.php';
require APP_ROOT . '/app/views/components/sidebar.php';
?>
<div class="main-content">
    <?php require APP_ROOT . '/app/views/components/navbar.php'; ?>
    <div class="content-wrapper">
        <div class="card stat-card p-4" style="max-width:760px">
            <h5 class="mb-3">Delete Notification</h5>
            <p>Delete <strong><?= e($notification['title'] ?? 'this notification') ?></strong>?</p>
            <form method="POST">
                <input type="hidden" name="id" value="<?= e($id) ?>">
                <button class="btn btn-danger">Delete notification</button>
                <a class="btn btn-outline-secondary" href="<?= url('views/admin/notifications/view.php?id=' . urlencode($id)) ?>">Cancel</a>
            </form>
        </div>
    </div>
</div>
<?php require APP_ROOT . '/app/views/components/footer.php'; ?>
