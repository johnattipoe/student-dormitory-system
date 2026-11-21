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
use App\Services\UserService;

$id = sanitize($_GET['id'] ?? '');
if ($id === '') {
    flash('error', 'Announcement ID is required.');
    redirect(url('views/senior-houseparent/announcements/index.php'));
}

$announcement = FirebaseService::getInstance()->getDocument('announcements', $id);
if (!$announcement) {
    flash('error', 'Announcement not found.');
    redirect(url('views/senior-houseparent/announcements/index.php'));
}

// Preload author name
$author = (string) ($announcement['createdByName'] ?? '');
if ($author === '' || $author === 'default-admin' || str_starts_with($author, 'Staff/User')) {
    $rawAuthor = (string) ($announcement['createdBy'] ?? '');
    if ($rawAuthor === 'default-admin') {
        $author = 'Administrator (Admin)';
    } elseif ($rawAuthor !== '') {
        try {
            $u = FirebaseService::getInstance()->getDocument('users', $rawAuthor);
            if ($u) {
                $name = trim(($u['name'] ?? '') ?: (($u['fullName'] ?? '') ?: (($u['firstName'] ?? '') . ' ' . ($u['lastName'] ?? ''))));
                if ($name !== '') {
                    $roleLabel = !empty($u['role']) ? ' (' . ucfirst(str_replace(['_', '-'], ' ', (string) $u['role'])) . ')' : '';
                    $author = $name . $roleLabel;
                }
            }
        } catch (\Throwable $e) {}
    }
}
if ($author === '') $author = 'Administrator (Admin)';

$type = strtolower((string) ($announcement['type'] ?? 'info'));
$typeColor = match($type) {
    'danger' => 'danger',
    'warning' => 'warning',
    'success' => 'success',
    default => 'primary'
};
$audience = (string) ($announcement['audience'] ?? 'all');
$targetRole = (string) ($announcement['targetRole'] ?? '');

$pageTitle = 'Announcement Details';
$navItems = [
    ['icon' => 'bi-speedometer2', 'label' => 'Dashboard', 'href' => url('views/senior-houseparent/dashboard/index.php')],
    ['icon' => 'bi-megaphone', 'label' => 'Announcements', 'href' => url('views/senior-houseparent/announcements/index.php'), 'active' => true],
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
                <p class="text-muted mb-0">Official house communication record.</p>
            </div>
            <div class="d-flex gap-2">
                <a class="btn btn-outline-secondary btn-sm" href="<?= url('views/senior-houseparent/announcements/index.php') ?>">
                    <i class="bi bi-arrow-left me-1"></i> Back to List
                </a>
                <button type="button" class="btn btn-outline-primary btn-sm" data-bs-toggle="modal" data-bs-target="#seniorEditAnnouncementModal">
                    <i class="bi bi-pencil me-1"></i> Edit
                </button>
                <button type="button" class="btn btn-outline-danger btn-sm" data-bs-toggle="modal" data-bs-target="#seniorDeleteAnnouncementModal">
                    <i class="bi bi-trash me-1"></i> Delete
                </button>
            </div>
        </div>

        <div class="card stat-card p-4" style="max-width: 850px;">
            <div class="d-flex justify-content-between align-items-start border-bottom pb-3 mb-3 flex-wrap gap-2">
                <div>
                    <h4 class="mb-1 fw-bold"><?= e($announcement['title'] ?? 'Untitled Bulletin') ?></h4>
                    <div class="small text-muted">
                        <span><i class="bi bi-person me-1"></i> Posted by: <strong><?= e($author) ?></strong></span>
                        <span class="mx-2">•</span>
                        <span><i class="bi bi-clock me-1"></i> <?= e(date('F d, Y - H:i', strtotime((string)($announcement['publishedAt'] ?? $announcement['createdAt'] ?? 'now')))) ?></span>
                    </div>
                </div>
                <div class="d-flex gap-2">
                    <span class="badge bg-<?= e($typeColor) ?> px-3 py-2 fs-6">
                        <?= e(ucfirst($type)) ?>
                    </span>
                    <?php if (!empty($announcement['isUrgent'])): ?>
                        <span class="badge bg-danger px-3 py-2 fs-6">Urgent</span>
                    <?php endif; ?>
                </div>
            </div>

            <div class="row g-3 mb-4 bg-light rounded p-3">
                <div class="col-sm-6">
                    <span class="text-muted small d-block">Target Audience</span>
                    <strong><?= e($audience === 'all' ? 'All Dormitory (Staff & Students)' : ucfirst(str_replace('_', ' ', $targetRole ?: $audience))) ?></strong>
                </div>
                <div class="col-sm-6">
                    <span class="text-muted small d-block">Publication Status</span>
                    <span class="badge bg-success"><?= e(ucfirst((string)($announcement['status'] ?? 'published'))) ?></span>
                </div>
            </div>

            <div class="announcement-content p-2" style="font-size: 1.05rem; line-height: 1.8; white-space: pre-line;">
                <?= e($announcement['message'] ?? '') ?>
            </div>
        </div>
    </div>
</div>
<div class="modal fade" id="seniorEditAnnouncementModal" tabindex="-1" aria-hidden="true"><div class="modal-dialog modal-dialog-centered modal-dialog-scrollable modal-lg"><div class="modal-content">
    <form method="POST" action="<?= url('views/senior-houseparent/announcements/edit/edit.php?id=' . urlencode($id)) ?>">
        <div class="modal-header"><h5 class="modal-title">Edit Announcement</h5><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button></div>
        <div class="modal-body"><input type="hidden" name="id" value="<?= e($id) ?>">
            <label class="form-label">Title</label><input name="title" class="form-control mb-3" value="<?= e($announcement['title'] ?? '') ?>" required>
            <label class="form-label">Message</label><textarea name="message" class="form-control mb-3" rows="6" required><?= e($announcement['message'] ?? '') ?></textarea>
            <div class="row g-3">
                <div class="col-md-6"><label class="form-label">Target Audience</label><select name="audience" class="form-select"><option value="all" <?= $audience !== 'role' ? 'selected' : '' ?>>All Dormitory</option><option value="house_master" <?= $audience === 'role' && $targetRole !== ROLE_STUDENT ? 'selected' : '' ?>>House Masters &amp; Mistresses</option><option value="student" <?= $targetRole === ROLE_STUDENT ? 'selected' : '' ?>>Dormitory Students</option></select></div>
                <div class="col-md-6"><label class="form-label">Category / Priority</label><select name="type" class="form-select"><?php foreach (['info', 'warning', 'danger', 'success'] as $announcementType): ?><option value="<?= e($announcementType) ?>" <?= $type === $announcementType ? 'selected' : '' ?>><?= ucfirst($announcementType) ?></option><?php endforeach; ?></select></div>
                <div class="col-md-6"><label class="form-label">Publication Status</label><select name="status" class="form-select"><?php foreach (['published', 'draft', 'archived'] as $status): ?><option value="<?= e($status) ?>" <?= ($announcement['status'] ?? 'published') === $status ? 'selected' : '' ?>><?= ucfirst($status) ?></option><?php endforeach; ?></select></div>
                <div class="col-md-6 d-flex align-items-center"><div class="form-check form-switch mt-4"><input class="form-check-input" type="checkbox" name="isUrgent" value="1" id="seniorEditAnnouncementUrgent" <?= !empty($announcement['isUrgent']) ? 'checked' : '' ?>><label class="form-check-label text-danger" for="seniorEditAnnouncementUrgent">Mark as urgent</label></div></div>
            </div>
        </div>
        <div class="modal-footer"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button><button type="submit" class="btn btn-primary">Save changes</button></div>
    </form>
</div></div></div>
<div class="modal fade" id="seniorDeleteAnnouncementModal" tabindex="-1" aria-hidden="true"><div class="modal-dialog modal-dialog-centered"><div class="modal-content">
    <div class="modal-header"><h5 class="modal-title text-danger">Delete Announcement</h5><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button></div>
    <div class="modal-body"><p>Are you sure you want to permanently delete <strong><?= e($announcement['title'] ?? 'this announcement') ?></strong>?</p><p class="mb-0 text-muted"><?= e(mb_strimwidth((string) ($announcement['message'] ?? ''), 0, 150, '...')) ?></p></div>
    <div class="modal-footer"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button><form method="POST" action="<?= url('views/senior-houseparent/announcements/delete/delete.php?id=' . urlencode($id)) ?>"><input type="hidden" name="id" value="<?= e($id) ?>"><button type="submit" class="btn btn-danger"><i class="bi bi-trash me-1"></i>Confirm Delete</button></form></div>
</div></div></div>
<?php require APP_ROOT . '/app/views/components/footer/footer.php'; ?>

