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

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title = trim((string) sanitize($_POST['title'] ?? ''));
    $message = trim((string) sanitize($_POST['message'] ?? ''));
    $audience = sanitize($_POST['audience'] ?? 'all');
    $type = sanitize($_POST['type'] ?? 'info');
    $status = sanitize($_POST['status'] ?? 'published');
    $isUrgent = !empty($_POST['isUrgent']);

    if ($title === '') $errors['title'] = 'Title is required.';
    if ($message === '') $errors['message'] = 'Message is required.';
    if (!in_array($audience, ['all', 'house_master', 'student'], true)) $audience = 'all';
    if (!in_array($type, ['info', 'success', 'warning', 'danger'], true)) $type = 'info';
    if (!in_array($status, ['published', 'draft', 'archived'], true)) $status = 'published';

    if (empty($errors)) {
        try {
            $targetRole = match($audience) {
                'house_master' => ROLE_HOUSE_MASTER,
                'student' => ROLE_STUDENT,
                default => ''
            };

            $firebase->updateDocument('announcements', $id, [
                'title' => $title,
                'message' => $message,
                'audience' => $audience === 'all' ? 'all' : 'role',
                'targetRole' => $targetRole,
                'type' => $type,
                'status' => $status,
                'isUrgent' => $isUrgent,
                'updatedAt' => date(DATE_ATOM),
                'updatedBy' => current_user_id(),
            ]);

            flash('success', 'Announcement updated successfully.');
            redirect(url('views/senior-houseparent/announcements/view/view.php?id=' . urlencode($id)));
        } catch (\Throwable $e) {
            $errors['general'] = 'Failed to update announcement: ' . $e->getMessage();
        }
    }
}

$titleVal = $_POST['title'] ?? $announcement['title'] ?? '';
$messageVal = $_POST['message'] ?? $announcement['message'] ?? '';
$typeVal = $_POST['type'] ?? $announcement['type'] ?? 'info';
$audienceVal = $_POST['audience'] ?? ($announcement['audience'] === 'role' ? (($announcement['targetRole'] ?? '') === ROLE_STUDENT ? 'student' : 'house_master') : 'all');
$statusVal = $_POST['status'] ?? $announcement['status'] ?? 'published';
$isUrgentVal = isset($_POST['isUrgent']) ? !empty($_POST['isUrgent']) : !empty($announcement['isUrgent']);

$pageTitle = 'Edit Announcement';
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
        <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
            <div>
                <h5 class="mb-1">Edit Announcement</h5>
                <p class="text-muted mb-0">Modify announcement details and target visibility.</p>
            </div>
            <a class="btn btn-outline-secondary btn-sm" href="<?= url('views/senior-houseparent/announcements/view/view.php?id=' . urlencode($id)) ?>">
                <i class="bi bi-arrow-left me-1"></i> Back to Announcement
            </a>
        </div>

        <?php if (!empty($errors['general'])): ?>
            <div class="alert alert-danger mb-3"><?= e($errors['general']) ?></div>
        <?php endif; ?>

        <div class="card stat-card p-4" style="max-width: 850px;">
            <form method="POST" class="row g-3">
                <input type="hidden" name="id" value="<?= e($id) ?>">

                <div class="col-md-8">
                    <label class="form-label fw-bold" for="title">Title <span class="text-danger">*</span></label>
                    <input class="form-control <?= !empty($errors['title']) ? 'is-invalid' : '' ?>" id="title" name="title" value="<?= e($titleVal) ?>" required>
                    <?php if (!empty($errors['title'])): ?>
                        <div class="invalid-feedback"><?= e($errors['title']) ?></div>
                    <?php endif; ?>
                </div>

                <div class="col-md-4">
                    <label class="form-label fw-bold" for="type">Category / Priority</label>
                    <select class="form-select" id="type" name="type">
                        <option value="info" <?= $typeVal === 'info' ? 'selected' : '' ?>>General Information (Blue)</option>
                        <option value="warning" <?= $typeVal === 'warning' ? 'selected' : '' ?>>Warning / Inspection (Yellow)</option>
                        <option value="danger" <?= $typeVal === 'danger' ? 'selected' : '' ?>>Urgent / Emergency (Red)</option>
                        <option value="success" <?= $typeVal === 'success' ? 'selected' : '' ?>>Commendation / Update (Green)</option>
                    </select>
                </div>

                <div class="col-md-6">
                    <label class="form-label fw-bold" for="audience">Target Audience</label>
                    <select class="form-select" id="audience" name="audience">
                        <option value="all" <?= $audienceVal === 'all' ? 'selected' : '' ?>>All Dormitory (Staff & Students)</option>
                        <option value="house_master" <?= $audienceVal === 'house_master' ? 'selected' : '' ?>>House Masters & Mistresses Only</option>
                        <option value="student" <?= $audienceVal === 'student' ? 'selected' : '' ?>>Dormitory Students Only</option>
                    </select>
                </div>

                <div class="col-md-3">
                    <label class="form-label fw-bold" for="status">Status</label>
                    <select class="form-select" id="status" name="status">
                        <option value="published" <?= $statusVal === 'published' ? 'selected' : '' ?>>Published</option>
                        <option value="draft" <?= $statusVal === 'draft' ? 'selected' : '' ?>>Draft</option>
                        <option value="archived" <?= $statusVal === 'archived' ? 'selected' : '' ?>>Archived</option>
                    </select>
                </div>

                <div class="col-md-3 d-flex align-items-center">
                    <div class="mt-4 pt-1">
                        <div class="form-check form-switch">
                            <input class="form-check-input" type="checkbox" id="isUrgent" name="isUrgent" value="1" <?= $isUrgentVal ? 'checked' : '' ?>>
                            <label class="form-check-label fw-semibold text-danger" for="isUrgent">Urgent Bulletin</label>
                        </div>
                    </div>
                </div>

                <div class="col-12">
                    <label class="form-label fw-bold" for="message">Message Content <span class="text-danger">*</span></label>
                    <textarea class="form-control <?= !empty($errors['message']) ? 'is-invalid' : '' ?>" id="message" name="message" rows="6" required><?= e($messageVal) ?></textarea>
                    <?php if (!empty($errors['message'])): ?>
                        <div class="invalid-feedback"><?= e($errors['message']) ?></div>
                    <?php endif; ?>
                </div>

                <div class="col-12 d-flex justify-content-end gap-2 mt-4">
                    <a class="btn btn-outline-secondary" href="<?= url('views/senior-houseparent/announcements/view/view.php?id=' . urlencode($id)) ?>">Cancel</a>
                    <button type="submit" class="btn btn-primary">
                        <i class="bi bi-check-lg me-1"></i> Save Changes
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
<?php require APP_ROOT . '/app/views/components/footer/footer.php'; ?>

