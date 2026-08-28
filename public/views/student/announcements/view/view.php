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

use App\Services\FirebaseService;

$id = sanitize($_GET['id'] ?? '');
if ($id === '') {
    flash('error', 'Announcement ID is required.');
    redirect(url('views/student/announcements/index.php'));
}

$firebase = FirebaseService::getInstance();
$announcement = $firebase->getDocument('announcements', $id);

if (!$announcement) {
    flash('error', 'Announcement not found.');
    redirect(url('views/student/announcements/index.php'));
}

$author = (string) ($announcement['createdByName'] ?? 'Dormitory Staff');
if ($author === 'default-admin') $author = 'Administrator (Admin)';

$rawDate = (string) ($announcement['publishedAt'] ?? $announcement['createdAt'] ?? '');
$formattedDate = $rawDate !== '' ? (date('F d, Y \a\t h:i A', strtotime($rawDate)) ?: $rawDate) : 'Recently';

$type = $announcement['type'] ?? 'info';
$typeClass = match($type) {
    'danger' => 'bg-danger text-white',
    'warning' => 'bg-warning text-dark',
    'success' => 'bg-success text-white',
    default => 'bg-primary text-white',
};

$pageTitle = 'Announcement Details';
$navItems = [
    ['icon' => 'bi-speedometer2', 'label' => 'Dashboard', 'href' => url('views/student/dashboard/index.php')],
    ['icon' => 'bi-megaphone', 'label' => 'Announcements', 'href' => url('views/student/announcements/index.php'), 'active' => true],
];

require APP_ROOT . '/app/views/components/header/header.php';
require APP_ROOT . '/app/views/components/sidebar/sidebar.php';
?>
<div class="main-content">
    <?php require APP_ROOT . '/app/views/components/navbar/navbar.php'; ?>
    <?php require APP_ROOT . '/app/views/components/alerts/alerts.php'; ?>
    <div class="content-wrapper">
        <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
            <div>
                <h5 class="mb-1">Announcement Bulletin</h5>
                <p class="text-muted mb-0">Official dormitory notice.</p>
            </div>
            <a class="btn btn-outline-secondary btn-sm" href="<?= url('views/student/announcements/index.php') ?>">
                <i class="bi bi-arrow-left me-1"></i> Back to Bulletin Board
            </a>
        </div>

        <div class="card stat-card p-4" style="max-width: 800px;">
            <div class="d-flex align-items-center gap-2 mb-2">
                <span class="badge <?= $typeClass ?>"><?= ucfirst(e($type)) ?></span>
                <?php if (!empty($announcement['isUrgent'])): ?>
                    <span class="badge bg-danger"><i class="bi bi-exclamation-triangle-fill me-1"></i> Urgent</span>
                <?php endif; ?>
            </div>
            <h4 class="fw-bold mb-2"><?= e($announcement['title'] ?? 'Notice') ?></h4>
            <div class="small text-muted mb-4 pb-3 border-bottom">
                <i class="bi bi-person me-1"></i> Posted by <strong><?= e($author) ?></strong> • <i class="bi bi-clock me-1"></i> <?= e($formattedDate) ?>
            </div>

            <div class="announcement-body py-2" style="font-size: 1.05rem; line-height: 1.8; white-space: pre-line;">
                <?= e($announcement['message'] ?? $announcement['content'] ?? '') ?>
            </div>

            <div class="d-flex justify-content-end mt-4 pt-3 border-top">
                <a class="btn btn-outline-secondary" href="<?= url('views/student/announcements/index.php') ?>">
                    Back to All Notices
                </a>
            </div>
        </div>
    </div>
</div>
<?php require APP_ROOT . '/app/views/components/footer/footer.php'; ?>

