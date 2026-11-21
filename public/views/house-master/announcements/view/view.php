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

$currentHouseId = (string) (current_user()['houseId'] ?? current_user()['house_id'] ?? '');
$canManageAnnouncement = $currentHouseId !== ''
    && ($announcement['audience'] ?? '') === 'house'
    && (string) ($announcement['targetHouseId'] ?? '') === $currentHouseId;

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
                <?php if ($canManageAnnouncement): ?>
                    <button type="button" class="btn btn-warning btn-sm" data-bs-toggle="modal" data-bs-target="#houseAnnouncementEditModal"><i class="bi bi-pencil me-1"></i>Edit</button>
                    <button type="button" class="btn btn-outline-danger btn-sm" data-bs-toggle="modal" data-bs-target="#houseAnnouncementDeleteModal"><i class="bi bi-trash me-1"></i>Delete</button>
                <?php endif; ?>
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
<?php if ($canManageAnnouncement): ?>
<div class="modal fade" id="houseAnnouncementEditModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable modal-lg">
        <div class="modal-content">
            <form method="POST" action="<?= url('views/house-master/announcements/index.php') ?>">
                <input type="hidden" name="action" value="update_announcement"><input type="hidden" name="id" value="<?= e($id) ?>">
                <div class="modal-header"><h5 class="modal-title">Edit Announcement</h5><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button></div>
                <div class="modal-body">
                    <label class="form-label">Title</label><input name="title" class="form-control mb-3" value="<?= e($announcement['title'] ?? '') ?>" required>
                    <label class="form-label">Type</label>
                    <select name="type" class="form-select mb-3">
                        <option value="info" <?= $type === 'info' ? 'selected' : '' ?>>Info</option>
                        <option value="success" <?= $type === 'success' ? 'selected' : '' ?>>Success</option>
                        <option value="warning" <?= $type === 'warning' ? 'selected' : '' ?>>Warning</option>
                        <option value="danger" <?= $type === 'danger' ? 'selected' : '' ?>>Urgent</option>
                    </select>
                    <label class="form-label">Message</label><textarea name="message" class="form-control mb-3" rows="6" required><?= e($announcement['message'] ?? $announcement['content'] ?? '') ?></textarea>
                    <label class="form-label">Status</label>
                    <select name="status" class="form-select mb-3">
                        <option value="published" <?= ($announcement['status'] ?? 'published') === 'published' ? 'selected' : '' ?>>Published</option>
                        <option value="draft" <?= ($announcement['status'] ?? '') === 'draft' ? 'selected' : '' ?>>Draft</option>
                        <option value="archived" <?= ($announcement['status'] ?? '') === 'archived' ? 'selected' : '' ?>>Archived</option>
                    </select>
                    <div class="form-check"><input class="form-check-input" type="checkbox" name="isUrgent" id="houseAnnouncementUrgent" value="1" <?= !empty($announcement['isUrgent']) ? 'checked' : '' ?>><label class="form-check-label" for="houseAnnouncementUrgent">Mark as urgent</label></div>
                </div>
                <div class="modal-footer"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button><button type="submit" class="btn btn-primary">Save changes</button></div>
            </form>
        </div>
    </div>
</div>
<div class="modal fade" id="houseAnnouncementDeleteModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header border-0"><h5 class="modal-title text-danger">Delete Announcement</h5><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button></div>
            <div class="modal-body">Delete <strong><?= e($announcement['title'] ?? 'this announcement') ?></strong>?</div>
            <div class="modal-footer border-0">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <form method="POST" action="<?= url('views/house-master/announcements/index.php') ?>"><input type="hidden" name="action" value="delete_announcement"><input type="hidden" name="id" value="<?= e($id) ?>"><button type="submit" class="btn btn-danger">Delete</button></form>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>
<?php require APP_ROOT . '/app/views/components/footer/footer.php'; ?>
