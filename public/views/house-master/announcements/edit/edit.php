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
$announcement = $firebase->getDocument('announcements', $id);

if (!$announcement) {
    flash('error', 'Announcement not found.');
    redirect(url('views/house-master/announcements/index.php'));
}

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title = trim((string) sanitize($_POST['title'] ?? ''));
    $message = trim((string) sanitize($_POST['message'] ?? ''));
    $type = sanitize($_POST['type'] ?? 'info');
    $isUrgent = !empty($_POST['isUrgent']);
    $status = sanitize($_POST['status'] ?? 'published');

    if ($title === '') $errors['title'] = 'Title is required.';
    if ($message === '') $errors['message'] = 'Message content is required.';

    if (empty($errors)) {
        try {
            $firebase->updateDocument('announcements', $id, [
                'title' => $title,
                'message' => $message,
                'type' => $type,
                'status' => $status,
                'isUrgent' => $isUrgent,
                'updatedAt' => date(DATE_ATOM),
            ]);

            flash('success', 'Announcement updated successfully.');
            redirect(url('views/house-master/announcements/index.php'));
        } catch (\Throwable $e) {
            $errors['general'] = 'Failed to update announcement: ' . $e->getMessage();
        }
    }
}

$titleVal = $_POST['title'] ?? $announcement['title'] ?? '';
$messageVal = $_POST['message'] ?? $announcement['message'] ?? $announcement['content'] ?? '';
$typeVal = $_POST['type'] ?? $announcement['type'] ?? 'info';
$isUrgentVal = isset($_POST['isUrgent']) ? !empty($_POST['isUrgent']) : !empty($announcement['isUrgent']);
$statusVal = $_POST['status'] ?? $announcement['status'] ?? 'published';

$pageTitle = 'Edit Announcement';
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
                <h4 class="mb-1 fw-bold text-dark"><i class="bi bi-pencil-square text-warning me-2"></i>Edit Announcement</h4>
                <p class="text-muted mb-0">Modify announcement title, message content, and publication status</p>
            </div>
            <div class="d-flex gap-2 flex-wrap">
                <a class="btn btn-outline-secondary btn-sm" href="<?= url('views/house-master/announcements/view/view.php?id=' . urlencode($id)) ?>">
                    <i class="bi bi-eye me-1"></i>View Notice
                </a>
                <a class="btn btn-outline-secondary btn-sm" href="<?= url('views/house-master/announcements/index.php') ?>">
                    <i class="bi bi-arrow-left me-1"></i>Back
                </a>
            </div>
        </div>

        <?php if (!empty($errors['general'])): ?>
            <div class="alert alert-danger mb-4"><i class="bi bi-exclamation-triangle me-2"></i><?= e($errors['general']) ?></div>
        <?php endif; ?>

        <!-- Form Card -->
        <div class="card stat-card shadow-sm border-0" style="max-width: 860px;">
            <div class="card-header bg-white py-3">
                <h6 class="mb-0 fw-bold"><i class="bi bi-megaphone me-2 text-primary"></i>Update Announcement</h6>
            </div>
            <div class="card-body p-4">
                <form method="POST" class="row g-3">
                    <input type="hidden" name="id" value="<?= e($id) ?>">

                    <div class="col-md-8">
                        <label class="form-label fw-semibold" for="title">Title / Subject <span class="text-danger">*</span></label>
                        <div class="input-group">
                            <span class="input-group-text bg-white"><i class="bi bi-type"></i></span>
                            <input class="form-control <?= !empty($errors['title']) ? 'is-invalid' : '' ?>" id="title" name="title" value="<?= e($titleVal) ?>" required>
                        </div>
                        <?php if (!empty($errors['title'])): ?>
                            <div class="text-danger small mt-1"><?= e($errors['title']) ?></div>
                        <?php endif; ?>
                    </div>

                    <div class="col-md-4">
                        <label class="form-label fw-semibold" for="type">Category / Style</label>
                        <div class="input-group">
                            <span class="input-group-text bg-white"><i class="bi bi-tag"></i></span>
                            <select class="form-select" id="type" name="type">
                                <option value="info" <?= $typeVal === 'info' ? 'selected' : '' ?>>Info (Blue)</option>
                                <option value="warning" <?= $typeVal === 'warning' ? 'selected' : '' ?>>Warning (Yellow)</option>
                                <option value="danger" <?= $typeVal === 'danger' ? 'selected' : '' ?>>Urgent (Red)</option>
                                <option value="success" <?= $typeVal === 'success' ? 'selected' : '' ?>>Success (Green)</option>
                            </select>
                        </div>
                    </div>

                    <div class="col-12">
                        <label class="form-label fw-semibold" for="message">Message Body <span class="text-danger">*</span></label>
                        <textarea class="form-control <?= !empty($errors['message']) ? 'is-invalid' : '' ?>" id="message" name="message" rows="6" required><?= e($messageVal) ?></textarea>
                        <?php if (!empty($errors['message'])): ?>
                            <div class="text-danger small mt-1"><?= e($errors['message']) ?></div>
                        <?php endif; ?>
                    </div>

                    <div class="col-md-6">
                        <label class="form-label fw-semibold" for="status">Publication Status</label>
                        <div class="input-group">
                            <span class="input-group-text bg-white"><i class="bi bi-check2-circle"></i></span>
                            <select class="form-select" id="status" name="status">
                                <option value="published" <?= $statusVal === 'published' ? 'selected' : '' ?>>Published (Live)</option>
                                <option value="draft" <?= $statusVal === 'draft' ? 'selected' : '' ?>>Draft</option>
                                <option value="archived" <?= $statusVal === 'archived' ? 'selected' : '' ?>>Archived</option>
                            </select>
                        </div>
                    </div>

                    <div class="col-md-6 d-flex align-items-center">
                        <div class="form-check mt-3">
                            <input class="form-check-input" type="checkbox" id="isUrgent" name="isUrgent" value="1" <?= $isUrgentVal ? 'checked' : '' ?>>
                            <label class="form-check-label fw-semibold text-danger" for="isUrgent">
                                <i class="bi bi-exclamation-triangle me-1"></i>Mark as High Priority / Urgent
                            </label>
                        </div>
                    </div>

                    <div class="col-12 d-flex justify-content-end gap-2 pt-3 border-top">
                        <a class="btn btn-outline-secondary" href="<?= url('views/house-master/announcements/index.php') ?>">Cancel</a>
                        <button type="submit" class="btn btn-primary">
                            <i class="bi bi-check2 me-1"></i>Save Changes
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
<?php require APP_ROOT . '/app/views/components/footer/footer.php'; ?>
