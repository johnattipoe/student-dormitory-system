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

$id = sanitize($_GET['id'] ?? $_POST['id'] ?? '');
$service = new NotificationService();
$notification = $service->findForAdmin($id);
if (!$notification) {
    flash('error', 'Notification not found.');
    redirect(url('views/admin/notifications/index/index.php'));
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $result = $service->update($id, [
        'title' => sanitize($_POST['title'] ?? ''),
        'message' => sanitize($_POST['message'] ?? ''),
        'type' => sanitize($_POST['type'] ?? 'info'),
    ]);
    flash($result['success'] ? 'success' : 'error', $result['message']);
    if ($result['success']) {
        redirect(url('views/admin/notifications/view/view.php?id=' . urlencode($id)));
    }
    $notification = array_merge($notification, [
        'title' => $_POST['title'] ?? '',
        'message' => $_POST['message'] ?? '',
        'type' => $_POST['type'] ?? 'info',
    ]);
}

$pageTitle = 'Edit Notification';
$navItems = [
    ['icon' => 'bi-speedometer2', 'label' => 'Dashboard', 'href' => url('views/admin/dashboard.php')],
    ['icon' => 'bi-bell', 'label' => 'Notifications', 'href' => url('views/admin/notifications/index/index.php'), 'active' => true],
];
require APP_ROOT . '/app/views/components/header/header.php';
require APP_ROOT . '/app/views/components/sidebar/sidebar.php';
?>
<div class="main-content">
    <?php require APP_ROOT . '/app/views/components/navbar/navbar.php'; ?>
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
                <div class="mt-4">
                    <button class="btn btn-primary">Save changes</button>
                    <a class="btn btn-outline-secondary ms-1" href="<?= url('views/admin/notifications/view/view.php?id=' . urlencode($id)) ?>">Cancel</a>
                </div>
            </form>
        </div>
    </div>
</div>
<?php require APP_ROOT . '/app/views/components/footer/footer.php'; ?>
