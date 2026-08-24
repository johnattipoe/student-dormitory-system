<?php
require_once dirname(__DIR__, 3) . '/bootstrap.php';
$allowedRoles = [ROLE_HOUSEPARENT];
require APP_ROOT . '/app/middleware/RoleMiddleware.php';

use App\Services\NotificationService;

$currentUserId = current_user()['uid'] ?? current_user()['id'] ?? null;
$id = sanitize($_GET['id'] ?? $_POST['id'] ?? '');
$service = new NotificationService();
$notification = $service->findForUser($id, $currentUserId);
if (!$notification) {
    flash('error', 'Notification not found for your account.');
    redirect(url('views/houseparent/notifications/index.php'));
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $result = $service->updateForUser($id, $currentUserId, [
        'title' => sanitize($_POST['title'] ?? ''),
        'message' => sanitize($_POST['message'] ?? ''),
        'type' => sanitize($_POST['type'] ?? 'info'),
    ]);
    flash($result['success'] ? 'success' : 'error', $result['message']);
    if ($result['success']) {
        redirect(url('views/houseparent/notifications/view.php?id=' . urlencode($id)));
    }
    $notification = array_merge($notification, [
        'title' => $_POST['title'] ?? '',
        'message' => $_POST['message'] ?? '',
        'type' => $_POST['type'] ?? 'info',
    ]);
}

$pageTitle = 'Edit Notification';
$navItems = [['icon' => 'bi-bell', 'label' => 'Notifications', 'href' => url('views/houseparent/notifications/index.php'), 'active' => true]];
require APP_ROOT . '/app/views/components/header.php';
require APP_ROOT . '/app/views/components/sidebar.php';
?>
<div class="main-content">
    <?php require APP_ROOT . '/app/views/components/navbar.php'; ?>
    <div class="content-wrapper">
        <div class="card stat-card p-4" style="max-width:760px">
            <h5 class="mb-3">Edit Notification</h5>
            <form method="POST">
                <input type="hidden" name="id" value="<?= e($id) ?>">
                <label class="form-label">Title</label>
                <input name="title" class="form-control mb-3" value="<?= e($notification['title'] ?? '') ?>" required>
                <label class="form-label">Type</label>
                <select name="type" class="form-select mb-3">
                    <?php foreach (['info', 'success', 'warning', 'danger'] as $type): ?>
                        <option value="<?= e($type) ?>" <?= ($notification['type'] ?? 'info') === $type ? 'selected' : '' ?>><?= ucfirst($type) ?></option>
                    <?php endforeach; ?>
                </select>
                <label class="form-label">Message</label>
                <textarea name="message" class="form-control" rows="5" required><?= e($notification['message'] ?? '') ?></textarea>
                <div class="mt-4"><button class="btn btn-primary">Save changes</button> <a class="btn btn-outline-secondary" href="<?= url('views/houseparent/notifications/view.php?id=' . urlencode($id)) ?>">Cancel</a></div>
            </form>
        </div>
    </div>
</div>
<?php require APP_ROOT . '/app/views/components/footer.php'; ?>
