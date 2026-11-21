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

use App\Services\FirebaseService;

$id = sanitize($_GET['id'] ?? '');
$notification = $id ? FirebaseService::getInstance()->getDocument(COL_NOTIFICATIONS, $id) : null;
if (!$notification) {
    flash('error', 'Notification not found.');
    redirect(url('views/admin/notifications/index/index.php'));
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    FirebaseService::getInstance()->updateDocument(COL_NOTIFICATIONS, $id, ['read' => true]);
    flash('success', 'Notification marked read.');
    redirect(url('views/admin/notifications/view/view.php?id=' . urlencode($id)));
}

$pageTitle = 'Notification Details';
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
            <div class="d-flex justify-content-between">
                <div>
                    <span class="badge bg-info mb-2"><?= e($notification['type'] ?? 'info') ?></span>
                    <h5><?= e($notification['title'] ?? 'Notification') ?></h5>
                </div>
                <a class="btn btn-outline-secondary btn-sm" href="<?= url('views/admin/notifications/index/index.php') ?>">Back</a>
            </div>
            <hr>
            <p><?= nl2br(e($notification['message'] ?? '')) ?></p>
            <div class="d-flex justify-content-between align-items-center mt-3">
                <span class="badge <?= !empty($notification['read']) ? 'bg-success' : 'bg-warning text-dark' ?>"><?= !empty($notification['read']) ? 'Read' : 'Unread' ?></span>
                <div>
                    <button type="button" class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#adminEditNotificationModal"><i class="bi bi-pencil me-1"></i>Edit</button>
                    <?php if (empty($notification['read'])): ?>
                        <form method="POST" class="d-inline ms-1">
                            <button class="btn btn-primary btn-sm">Mark as read</button>
                        </form>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>
<div class="modal fade" id="adminEditNotificationModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg"><div class="modal-content">
        <form method="POST" action="<?= url('views/admin/notifications/edit/edit.php?id=' . urlencode($id)) ?>">
            <input type="hidden" name="id" value="<?= e($id) ?>">
            <div class="modal-header"><h5 class="modal-title">Edit Notification</h5><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button></div>
            <div class="modal-body">
                <label class="form-label">Title</label><input name="title" class="form-control mb-3" value="<?= e($notification['title'] ?? '') ?>" required>
                <label class="form-label">Type</label><select name="type" class="form-select mb-3"><?php foreach (['info', 'success', 'warning', 'danger'] as $notificationType): ?><option value="<?= e($notificationType) ?>" <?= ($notification['type'] ?? 'info') === $notificationType ? 'selected' : '' ?>><?= ucfirst($notificationType) ?></option><?php endforeach; ?></select>
                <label class="form-label">Message</label><textarea name="message" class="form-control" rows="5" required><?= e($notification['message'] ?? '') ?></textarea>
            </div>
            <div class="modal-footer"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button><button type="submit" class="btn btn-primary">Save changes</button></div>
        </form>
    </div></div>
</div>
<?php require APP_ROOT . '/app/views/components/footer/footer.php'; ?>