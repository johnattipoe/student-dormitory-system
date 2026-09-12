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

$id = sanitize($_GET['id'] ?? '');
if ($id === '') {
    flash('error', 'Announcement ID is required.');
    redirect(url('views/house-master/announcements/index.php'));
}

$firebase = FirebaseService::getInstance();
$announcement = $firebase->getDocument('announcements', $id);

if (!$announcement) {
    flash('error', 'Announcement not found.');
    redirect(url('views/house-master/announcements/index.php'));
}

$author = (string) ($announcement['createdByName'] ?? $announcement['author'] ?? '');
$authorId = (string) ($announcement['createdBy'] ?? '');
if ($authorId === 'default-admin' || $author === 'default-admin') {
    $author = 'Administrator (Admin)';
} elseif ($author === '') {
    $author = 'House Master / Staff';
}

$rawDate = (string) ($announcement['publishedAt'] ?? $announcement['createdAt'] ?? '');
$formattedDate = $rawDate !== '' ? (date('F d, Y \a\t h:i A', strtotime($rawDate)) ?: $rawDate) : 'Not specified';

$type = $announcement['type'] ?? 'info';
$typeBadge = match($type) {
    'danger' => 'bg-danger text-white',
    'warning' => 'bg-warning text-dark',
    'success' => 'bg-success text-white',
    default => 'bg-primary text-white',
};

$pageTitle = 'View Announcement';
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
                <h4 class="mb-1 fw-bold text-dark"><i class="bi bi-megaphone-fill text-primary me-2"></i>Announcement Details</h4>
                <p class="text-muted mb-0">Review bulletin message, audience scope, and publishing info</p>
            </div>
            <div class="d-flex gap-2 flex-wrap">
                <a class="btn btn-outline-secondary btn-sm" href="<?= url('views/house-master/announcements/index.php') ?>">
                    <i class="bi bi-arrow-left me-1"></i>Back
                </a>
                <a class="btn btn-warning btn-sm" href="<?= url('views/house-master/announcements/edit/edit.php?id=' . urlencode($id)) ?>">
                    <i class="bi bi-pencil me-1"></i>Edit
                </a>
                <a class="btn btn-outline-danger btn-sm" href="<?= url('views/house-master/announcements/delete/delete.php?id=' . urlencode($id)) ?>">
                    <i class="bi bi-trash me-1"></i>Delete
                </a>
            </div>
        </div>

        <!-- Detail Card -->
        <div class="card stat-card shadow-sm border-0 mb-4" style="max-width: 880px;">
            <div class="card-body p-4">
                <div class="d-flex align-items-center gap-2 mb-3">
                    <span class="badge <?= $typeBadge ?>"><?= ucfirst(e($type)) ?></span>
                    <?php if (!empty($announcement['isUrgent'])): ?>
                        <span class="badge bg-danger"><i class="bi bi-exclamation-triangle-fill me-1"></i>Urgent</span>
                    <?php endif; ?>
                </div>
                <h3 class="fw-bold text-dark mb-2"><?= e($announcement['title'] ?? 'Untitled') ?></h3>
                <div class="small text-muted mb-4 pb-3 border-bottom d-flex flex-wrap gap-3">
                    <span><i class="bi bi-person me-1"></i>Posted by <strong><?= e($author) ?></strong></span>
                    <span><i class="bi bi-clock me-1"></i><?= e($formattedDate) ?></span>
                    <span><i class="bi bi-people me-1"></i>Audience: <strong><?= e(ucfirst((string)($announcement['audience'] ?? 'All'))) ?></strong></span>
                </div>

                <div class="announcement-content text-dark mb-4" style="font-size: 1.05rem; line-height: 1.8; white-space: pre-line;">
                    <?= e($announcement['message'] ?? $announcement['content'] ?? '') ?>
                </div>

                <div class="pt-3 border-top d-flex justify-content-between align-items-center">
                    <span class="small text-muted">ID: <code><?= e($id) ?></code></span>
                    <a class="btn btn-outline-secondary btn-sm" href="<?= url('views/house-master/announcements/index.php') ?>">
                        <i class="bi bi-arrow-left me-1"></i>Back to Announcements
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>
<?php require APP_ROOT . '/app/views/components/footer/footer.php'; ?>
