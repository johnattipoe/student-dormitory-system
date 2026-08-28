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
$announcement = $firebase->getDocument('announcements', $id);

if (!$announcement) {
    flash('error', 'Announcement not found.');
    redirect(url('views/admin/announcements/index.php'));
}

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title = trim((string) sanitize($_POST['title'] ?? ''));
    $message = trim((string) sanitize($_POST['message'] ?? ''));
    $audience = sanitize($_POST['audience'] ?? 'all');
    $type = sanitize($_POST['type'] ?? 'info');
    $isUrgent = !empty($_POST['isUrgent']);
    $status = sanitize($_POST['status'] ?? 'published');

    if ($title === '') $errors['title'] = 'Title is required.';
    if ($message === '') $errors['message'] = 'Message content is required.';

    if (empty($errors)) {
        try {
            $targetRole = match($audience) {
                'house_master' => ROLE_HOUSE_MASTER,
                'senior_houseparent' => ROLE_SENIOR_HOUSEPARENT,
                'student' => ROLE_STUDENT,
                'nurse' => ROLE_NURSE,
                'security' => ROLE_SECURITY,
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
            ]);

            flash('success', 'Announcement updated successfully.');
            redirect(url('views/admin/announcements/index.php'));
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
$targetRoleVal = $announcement['targetRole'] ?? '';
$audienceVal = $announcement['audience'] === 'all' ? 'all' : match($targetRoleVal) {
    ROLE_HOUSE_MASTER => 'house_master',
    ROLE_SENIOR_HOUSEPARENT => 'senior_houseparent',
    ROLE_STUDENT => 'student',
    ROLE_NURSE => 'nurse',
    ROLE_SECURITY => 'security',
    default => 'all'
};

$pageTitle = 'Edit Announcement';
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
                <h5 class="mb-1">Edit Campus Announcement</h5>
                <p class="text-muted mb-0">Modify announcement title, message body, target audience, and priority.</p>
            </div>
            <a class="btn btn-outline-secondary btn-sm" href="<?= url('views/admin/announcements/index.php') ?>">
                <i class="bi bi-arrow-left me-1"></i> Back to Announcements
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
                    <label class="form-label fw-bold" for="type">Category / Style</label>
                    <select class="form-select" id="type" name="type">
                        <option value="info" <?= $typeVal === 'info' ? 'selected' : '' ?>>Info (Blue)</option>
                        <option value="warning" <?= $typeVal === 'warning' ? 'selected' : '' ?>>Warning (Yellow)</option>
                        <option value="danger" <?= $typeVal === 'danger' ? 'selected' : '' ?>>Urgent / Notice (Red)</option>
                        <option value="success" <?= $typeVal === 'success' ? 'selected' : '' ?>>Success (Green)</option>
                    </select>
                </div>

                <div class="col-md-6">
                    <label class="form-label fw-bold" for="audience">Target Audience</label>
                    <select class="form-select" id="audience" name="audience">
                        <option value="all" <?= $audienceVal === 'all' ? 'selected' : '' ?>>Entire Institution (All Staff & Students)</option>
                        <option value="student" <?= $audienceVal === 'student' ? 'selected' : '' ?>>Students Only</option>
                        <option value="house_master" <?= $audienceVal === 'house_master' ? 'selected' : '' ?>>House Masters & Mistresses Only</option>
                        <option value="senior_houseparent" <?= $audienceVal === 'senior_houseparent' ? 'selected' : '' ?>>Senior Houseparents Only</option>
                        <option value="nurse" <?= $audienceVal === 'nurse' ? 'selected' : '' ?>>Clinic / Nurses Only</option>
                        <option value="security" <?= $audienceVal === 'security' ? 'selected' : '' ?>>Security Staff Only</option>
                    </select>
                </div>

                <div class="col-md-6">
                    <label class="form-label fw-bold" for="status">Publication Status</label>
                    <select class="form-select" id="status" name="status">
                        <option value="published" <?= $statusVal === 'published' ? 'selected' : '' ?>>Published (Live)</option>
                        <option value="draft" <?= $statusVal === 'draft' ? 'selected' : '' ?>>Draft</option>
                        <option value="archived" <?= $statusVal === 'archived' ? 'selected' : '' ?>>Archived</option>
                    </select>
                </div>

                <div class="col-12">
                    <label class="form-label fw-bold" for="message">Announcement Message Body <span class="text-danger">*</span></label>
                    <textarea class="form-control <?= !empty($errors['message']) ? 'is-invalid' : '' ?>" id="message" name="message" rows="6" required><?= e($messageVal) ?></textarea>
                    <?php if (!empty($errors['message'])): ?>
                        <div class="invalid-feedback"><?= e($errors['message']) ?></div>
                    <?php endif; ?>
                </div>

                <div class="col-12">
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" id="isUrgent" name="isUrgent" value="1" <?= $isUrgentVal ? 'checked' : '' ?>>
                        <label class="form-check-label fw-bold text-danger" for="isUrgent">
                            Mark as High Priority / Urgent
                        </label>
                    </div>
                </div>

                <div class="col-12 d-flex justify-content-end gap-2 mt-4">
                    <a class="btn btn-outline-secondary" href="<?= url('views/admin/announcements/index.php') ?>">Cancel</a>
                    <button type="submit" class="btn btn-primary">
                        <i class="bi bi-check2 me-1"></i> Save Changes
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
<?php require APP_ROOT . '/app/views/components/footer/footer.php'; ?>

