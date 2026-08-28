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

use App\Services\FirebaseService;

$id = sanitize($_GET['id'] ?? $_POST['id'] ?? '');
if ($id === '') {
    flash('error', 'Announcement ID is required.');
    redirect(url('views/senior-houseparent/announcements/index.php'));
}

$firebase = FirebaseService::getInstance();
$announcement = $firebase->getDocument('announcements', $id);
if (!$announcement) {
    flash('error', 'Announcement not found.');
    redirect(url('views/senior-houseparent/announcements/index.php'));
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $firebase->deleteDocument('announcements', $id);
        flash('success', 'Announcement deleted successfully.');
        redirect(url('views/senior-houseparent/announcements/index.php'));
    } catch (\Throwable $e) {
        flash('error', 'Failed to delete announcement: ' . $e->getMessage());
        redirect(url('views/senior-houseparent/announcements/view/view.php?id=' . urlencode($id)));
    }
}

$pageTitle = 'Delete Announcement';
$navItems = [
    ['icon' => 'bi-speedometer2', 'label' => 'Dashboard', 'href' => url('views/senior-houseparent/dashboard/index.php')],
    ['icon' => 'bi-mortarboard', 'label' => 'Students', 'href' => url('views/senior-houseparent/students/index/index.php')],
    ['icon' => 'bi-megaphone', 'label' => 'Announcements', 'href' => url('views/senior-houseparent/announcements/index.php'), 'active' => true],
];

require APP_ROOT . '/app/views/components/header/header.php';
require APP_ROOT . '/app/views/components/sidebar/sidebar.php';
?>
<div class="main-content">
    <?php require APP_ROOT . '/app/views/components/navbar/navbar.php'; ?>
    <?php require APP_ROOT . '/app/views/components/alerts/alerts.php'; ?>
    <div class="content-wrapper">
        <div class="card stat-card p-4" style="max-width: 600px;">
            <h5 class="mb-3 text-danger"><i class="bi bi-exclamation-triangle me-2"></i> Delete Announcement</h5>
            <p>Are you sure you want to permanently delete the announcement bulletin: <strong>"<?= e($announcement['title'] ?? 'Untitled') ?>"</strong>?</p>
            <div class="border rounded p-3 bg-light mb-4 text-muted small">
                <?= e(mb_strimwidth((string)($announcement['message'] ?? ''), 0, 150, '...')) ?>
            </div>
            <form method="POST">
                <input type="hidden" name="id" value="<?= e($id) ?>">
                <div class="d-flex gap-2">
                    <button type="submit" class="btn btn-danger">
                        <i class="bi bi-trash me-1"></i> Confirm Delete
                    </button>
                    <a class="btn btn-outline-secondary" href="<?= url('views/senior-houseparent/announcements/view/view.php?id=' . urlencode($id)) ?>">Cancel</a>
                </div>
            </form>
        </div>
    </div>
</div>
<?php require APP_ROOT . '/app/views/components/footer/footer.php'; ?>

