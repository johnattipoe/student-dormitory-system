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
if ($id === '') {
    flash('error', 'Announcement ID is required.');
    redirect(url('views/admin/announcements/index.php'));
}

$firebase = FirebaseService::getInstance();
$announcement = $firebase->getDocument('announcements', $id);

if (!$announcement) {
    flash('error', 'Announcement not found.');
    redirect(url('views/admin/announcements/index.php'));
}

$author = (string) ($announcement['createdByName'] ?? $announcement['author'] ?? '');
$authorId = (string) ($announcement['createdBy'] ?? '');
if ($authorId === 'default-admin' || $author === 'default-admin') {
    $author = 'Administrator (Admin)';
} elseif ($author === '') {
    $author = 'Administrator';
}

$rawDate = (string) ($announcement['publishedAt'] ?? $announcement['createdAt'] ?? '');
$formattedDate = $rawDate !== '' ? (date('F d, Y \a\t h:i A', strtotime($rawDate)) ?: $rawDate) : 'Not specified';

$type = $announcement['type'] ?? 'info';
$typeClass = match($type) {
    'danger' => 'bg-danger text-white',
    'warning' => 'bg-warning text-dark',
    'success' => 'bg-success text-white',
    default => 'bg-primary text-white',
};

$pageTitle = 'View Announcement';
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
        <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
            <div>
                <h5 class="mb-1">Announcement Details</h5>
                <p class="text-muted mb-0">Official institutional bulletin reader.</p>
            </div>
            <div class="d-flex gap-2">
                <a class="btn btn-outline-secondary btn-sm" href="<?= url('views/admin/announcements/index.php') ?>">
                    <i class="bi bi-arrow-left me-1"></i> Back
                </a>
                <button type="button" class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#adminEditAnnouncementModal">
                    <i class="bi bi-pencil me-1"></i> Edit
                </button>
            </div>
        </div>

        <div class="card stat-card p-4" style="max-width: 850px;">
            <div class="d-flex justify-content-between align-items-start mb-3 flex-wrap gap-2">
                <div>
                    <div class="d-flex align-items-center gap-2 mb-2">
                        <span class="badge <?= $typeClass ?>"><?= ucfirst(e($type)) ?></span>
                        <?php if (!empty($announcement['isUrgent'])): ?>
                            <span class="badge bg-danger"><i class="bi bi-exclamation-triangle-fill me-1"></i> Urgent</span>
                        <?php endif; ?>
                    </div>
                    <h4 class="fw-bold mb-1"><?= e($announcement['title'] ?? 'Untitled') ?></h4>
                    <div class="small text-muted">
                        <i class="bi bi-person me-1"></i> Posted by <strong><?= e($author) ?></strong> • <i class="bi bi-clock me-1"></i> <?= e($formattedDate) ?>
                    </div>
                </div>
            </div>

            <hr>

            <div class="announcement-body py-3" style="font-size: 1.05rem; line-height: 1.7; white-space: pre-line;">
                <?= e($announcement['message'] ?? $announcement['content'] ?? '') ?>
            </div>

            <div class="d-flex justify-content-end mt-4 pt-3 border-top gap-2">
                <button type="button" class="btn btn-outline-danger btn-sm" data-bs-toggle="modal" data-bs-target="#adminDeleteAnnouncementModal">
                    <i class="bi bi-trash me-1"></i> Delete
                </button>
                <a class="btn btn-outline-secondary btn-sm" href="<?= url('views/admin/announcements/index.php') ?>">
                    Close
                </a>
            </div>
        </div>
    </div>
</div>
<div class="modal fade" id="adminEditAnnouncementModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable modal-lg"><div class="modal-content">
        <form method="POST" action="<?= url('views/admin/announcements/edit/edit.php?id=' . urlencode($id)) ?>">
            <input type="hidden" name="id" value="<?= e($id) ?>">
            <div class="modal-header"><h5 class="modal-title">Edit Campus Announcement</h5><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button></div>
            <div class="modal-body">
                <div class="row g-3">
                    <div class="col-md-8"><label class="form-label">Title</label><input name="title" class="form-control" value="<?= e($announcement['title'] ?? '') ?>" required></div>
                    <div class="col-md-4"><label class="form-label">Category</label><select name="type" class="form-select"><?php foreach (['info', 'success', 'warning', 'danger'] as $announcementType): ?><option value="<?= e($announcementType) ?>" <?= $type === $announcementType ? 'selected' : '' ?>><?= ucfirst($announcementType) ?></option><?php endforeach; ?></select></div>
                    <div class="col-md-6"><label class="form-label">Target Audience</label><select name="audience" class="form-select"><?php $audienceValue = ($announcement['audience'] ?? 'all') === 'all' ? 'all' : match((string) ($announcement['targetRole'] ?? '')) { ROLE_HOUSE_MASTER, ROLE_HOUSE_MISTRESS => 'house_master', ROLE_SENIOR_HOUSEPARENT => 'senior_houseparent', ROLE_STUDENT => 'student', ROLE_NURSE => 'nurse', ROLE_SECURITY => 'security', default => 'all' }; ?><option value="all" <?= $audienceValue === 'all' ? 'selected' : '' ?>>Entire Institution</option><option value="student" <?= $audienceValue === 'student' ? 'selected' : '' ?>>Students</option><option value="house_master" <?= $audienceValue === 'house_master' ? 'selected' : '' ?>>House Masters &amp; Mistresses</option><option value="senior_houseparent" <?= $audienceValue === 'senior_houseparent' ? 'selected' : '' ?>>Senior Houseparents</option><option value="nurse" <?= $audienceValue === 'nurse' ? 'selected' : '' ?>>Nurses</option><option value="security" <?= $audienceValue === 'security' ? 'selected' : '' ?>>Security</option></select></div>
                    <div class="col-md-6"><label class="form-label">Publication Status</label><select name="status" class="form-select"><?php foreach (['published', 'draft', 'archived'] as $status): ?><option value="<?= e($status) ?>" <?= ($announcement['status'] ?? 'published') === $status ? 'selected' : '' ?>><?= ucfirst($status) ?></option><?php endforeach; ?></select></div>
                    <div class="col-12"><label class="form-label">Message</label><textarea name="message" class="form-control" rows="6" required><?= e($announcement['message'] ?? $announcement['content'] ?? '') ?></textarea></div>
                    <div class="col-12"><div class="form-check form-switch"><input class="form-check-input" type="checkbox" name="isUrgent" value="1" id="adminEditAnnouncementUrgent" <?= !empty($announcement['isUrgent']) ? 'checked' : '' ?>><label class="form-check-label text-danger" for="adminEditAnnouncementUrgent">Mark as urgent</label></div></div>
                </div>
            </div>
            <div class="modal-footer"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button><button type="submit" class="btn btn-primary">Save changes</button></div>
        </form>
    </div></div>
</div>
<div class="modal fade" id="adminDeleteAnnouncementModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered"><div class="modal-content">
        <div class="modal-header"><h5 class="modal-title text-danger">Delete Announcement</h5><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button></div>
        <div class="modal-body">Delete <strong><?= e($announcement['title'] ?? 'this announcement') ?></strong>? This action cannot be undone.</div>
        <div class="modal-footer"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button><form method="POST" action="<?= url('views/admin/announcements/delete/delete.php?id=' . urlencode($id)) ?>"><input type="hidden" name="id" value="<?= e($id) ?>"><button type="submit" class="btn btn-danger">Delete</button></form></div>
    </div></div>
</div>
<?php require APP_ROOT . '/app/views/components/footer/footer.php'; ?>

