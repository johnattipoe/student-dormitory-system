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
                    <a class="btn btn-sm btn-outline-primary" href="<?= url('views/admin/notifications/edit/edit.php?id=' . urlencode($id)) ?>"><i class="bi bi-pencil me-1"></i>Edit</a>
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
<?php require APP_ROOT . '/app/views/components/footer/footer.php'; ?>