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

$id = sanitize($_GET['id'] ?? $_POST['id'] ?? '');
if ($id === '') {
    flash('error', 'Announcement ID is required.');
    redirect(url('views/admin/announcements/index.php'));
}

$firebase = FirebaseService::getInstance();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $firebase->deleteDocument('announcements', $id);
        flash('success', 'Announcement deleted successfully.');
        redirect(url('views/admin/announcements/index.php'));
    } catch (\Throwable $e) {
        flash('error', 'Failed to delete announcement: ' . $e->getMessage());
        redirect(url('views/admin/announcements/index.php'));
    }
}

$announcement = $firebase->getDocument('announcements', $id);
if (!$announcement) {
    flash('error', 'Announcement not found.');
    redirect(url('views/admin/announcements/index.php'));
}

$pageTitle = 'Delete Announcement';
$navItems = [
    ['icon' => 'bi-speedometer2', 'label' => 'Dashboard', 'href' => url('views/admin/dashboard.php')],
    ['icon' => 'bi-megaphone', 'label' => 'Announcements', 'href' => url('views/admin/announcements/index.php'), 'active' => true],
];

require APP_ROOT . '/app/views/components/header/header.php';
require APP_ROOT . '/app/views/components/sidebar/sidebar.php';
?>
<div class="main-content">
    <?php require APP_ROOT . '/app/views/components/navbar/navbar.php'; ?>
    <?php require APP_ROOT . '/app/views/components/alerts/alerts.php'; ?>
    <div class="content-wrapper">
        <div class="card stat-card p-4 mx-auto" style="max-width: 600px;">
            <div class="text-center mb-3">
                <i class="bi bi-exclamation-triangle text-danger fs-1"></i>
                <h5 class="fw-bold mt-2">Delete Announcement?</h5>
                <p class="text-muted">Are you sure you want to permanently delete "<strong><?= e($announcement['title'] ?? '') ?></strong>"? This action cannot be undone.</p>
            </div>
            <form method="POST" class="d-flex justify-content-center gap-2">
                <input type="hidden" name="id" value="<?= e($id) ?>">
                <a class="btn btn-outline-secondary" href="<?= url('views/admin/announcements/index.php') ?>">Cancel</a>
                <button type="submit" class="btn btn-danger">Yes, Delete Announcement</button>
            </form>
        </div>
    </div>
</div>
<?php require APP_ROOT . '/app/views/components/footer/footer.php'; ?>

