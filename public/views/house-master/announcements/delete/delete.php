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
$allowedRoles = [ROLE_HOUSE_MASTER, ROLE_HOUSE_MISTRESS];
require APP_ROOT . '/app/middleware/RoleMiddleware/RoleMiddleware.php';

use App\Services\FirebaseService;

$id = sanitize($_GET['id'] ?? $_POST['id'] ?? '');
if ($id === '') {
    flash('error', 'Announcement ID is required.');
    redirect(url('views/house-master/announcements/index.php'));
}

$firebase = FirebaseService::getInstance();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $firebase->deleteDocument('announcements', $id);
        flash('success', 'Announcement deleted successfully.');
        redirect(url('views/house-master/announcements/index.php'));
    } catch (\Throwable $e) {
        flash('error', 'Failed to delete announcement: ' . $e->getMessage());
        redirect(url('views/house-master/announcements/index.php'));
    }
}

$announcement = $firebase->getDocument('announcements', $id);
if (!$announcement) {
    flash('error', 'Announcement not found.');
    redirect(url('views/house-master/announcements/index.php'));
}

$pageTitle = 'Delete Announcement';
$navItems = [
    ['icon' => 'bi-speedometer2', 'label' => 'Dashboard', 'href' => url('views/house-master/dashboard/index.php')],
    ['icon' => 'bi-megaphone', 'label' => 'Announcements', 'href' => url('views/house-master/announcements/index.php'), 'active' => true],
];

require APP_ROOT . '/app/views/components/header/header.php';
require APP_ROOT . '/app/views/components/sidebar/sidebar.php';
?>
<div class="main-content">
    <?php require APP_ROOT . '/app/views/components/navbar/navbar.php'; ?>
    <div class="content-wrapper">
        <?php require APP_ROOT . '/app/views/components/alerts/alerts.php'; ?>

        <!-- Hero Header -->
        <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-3">
            <div>
                <h4 class="mb-1 fw-bold text-dark"><i class="bi bi-trash text-danger me-2"></i>Delete Announcement</h4>
                <p class="text-muted mb-0">Confirm deletion of bulletin notice</p>
            </div>
            <div class="d-flex gap-2 flex-wrap">
                <a class="btn btn-outline-secondary btn-sm" href="<?= url('views/house-master/announcements/index.php') ?>">
                    <i class="bi bi-arrow-left me-1"></i>Back
                </a>
            </div>
        </div>

        <!-- Confirmation Card -->
        <div class="card stat-card shadow-sm border-0 border-top border-4 border-danger mx-auto" style="max-width: 540px;">
            <div class="card-body p-4 text-center">
                <div class="rounded-circle bg-danger bg-opacity-10 text-danger d-inline-flex align-items-center justify-content-center mb-3" style="width: 72px; height: 72px;">
                    <i class="bi bi-exclamation-triangle-fill fs-2"></i>
                </div>
                <h5 class="fw-bold text-dark mb-2">Are you sure?</h5>
                <p class="text-muted mb-3">
                    You are about to permanently delete the announcement <strong>"<?= e($announcement['title'] ?? 'Untitled') ?>"</strong>.
                </p>
                <div class="alert alert-danger small text-start mb-4">
                    <i class="bi bi-info-circle me-1"></i> This action cannot be reversed. The announcement will be permanently removed from all notice feeds.
                </div>
                <form method="POST" class="d-flex justify-content-center gap-2">
                    <input type="hidden" name="id" value="<?= e($id) ?>">
                    <a class="btn btn-outline-secondary" href="<?= url('views/house-master/announcements/index.php') ?>">
                        Cancel
                    </a>
                    <button type="submit" class="btn btn-danger">
                        <i class="bi bi-trash me-1"></i>Confirm Delete
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>
<?php require APP_ROOT . '/app/views/components/footer/footer.php'; ?>
