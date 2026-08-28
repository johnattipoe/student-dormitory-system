<?php
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
use App\Services\NotificationService;

$errors = [];
$old = $_SESSION['_old'] ?? [];
unset($_SESSION['_old']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title = trim((string) sanitize($_POST['title'] ?? ''));
    $message = trim((string) sanitize($_POST['message'] ?? ''));
    $audience = sanitize($_POST['audience'] ?? 'all');
    $type = sanitize($_POST['type'] ?? 'info');
    $isUrgent = !empty($_POST['isUrgent']);
    $sendNotification = !empty($_POST['sendNotification']);
    $old = compact('title', 'message', 'audience', 'type', 'isUrgent', 'sendNotification');

    if ($title === '') $errors['title'] = 'Title is required.';
    if ($message === '') $errors['message'] = 'Message is required.';
    if (!in_array($audience, ['all', 'house_master'], true)) $audience = 'all';
    if (!in_array($type, ['info', 'success', 'warning', 'danger'], true)) $type = 'info';

    if (empty($errors)) {
        try {
            $firebase = FirebaseService::getInstance();
            $firebase->addDocument('announcements', [
                'title' => $title,
                'message' => $message,
                'audience' => $audience === 'house_master' ? 'role' : 'all',
                'targetRole' => $audience === 'house_master' ? ROLE_HOUSE_MASTER : '',
                'type' => $type,
                'status' => 'published',
                'isUrgent' => $isUrgent,
                'createdBy' => current_user_id(),
                'publishedAt' => date(DATE_ATOM),
            ]);

            if ($sendNotification) {
                $notification = [
                    'title' => $title,
                    'message' => $message,
                    'type' => $type,
                    'isUrgent' => $isUrgent,
                ];
                $result = $audience === 'house_master'
                    ? (new NotificationService())->notifyRole(ROLE_HOUSE_MASTER, $notification)
                    : (new NotificationService())->notifyAll($notification);
                flash($result['success'] ? 'success' : 'warning', 'Announcement sent. ' . ($result['message'] ?? ''));
            } else {
                flash('success', 'Announcement published.');
            }

            redirect(url('views/house-master/announcements/index.php'));
        } catch (Throwable $e) {
            $errors['general'] = 'Unable to send announcement: ' . $e->getMessage();
        }
    }
}

$pageTitle = 'Post Announcement';
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
                <h4 class="mb-1 fw-bold text-dark"><i class="bi bi-megaphone-fill text-primary me-2"></i>Post House Announcement</h4>
                <p class="text-muted mb-0">Share notices, curfew alerts, or official bulletins with your house</p>
            </div>
            <div class="d-flex gap-2 flex-wrap">
                <a href="<?= url('views/house-master/announcements/index.php') ?>" class="btn btn-outline-secondary btn-sm">
                    <i class="bi bi-arrow-left me-1"></i>Back to Announcements
                </a>
            </div>
        </div>

        <?php if (!empty($errors['general'])): ?>
            <div class="alert alert-danger mb-4"><i class="bi bi-exclamation-triangle me-2"></i><?= e($errors['general']) ?></div>
        <?php endif; ?>

        <!-- Form Card -->
        <div class="card stat-card shadow-sm border-0" style="max-width: 860px;">
            <div class="card-header bg-white py-3">
                <h6 class="mb-0 fw-bold"><i class="bi bi-pencil-square me-2 text-primary"></i>Announcement Details</h6>
            </div>
            <div class="card-body p-4">
                <form method="POST" action="<?= url('views/house-master/announcements/create/create.php') ?>" class="row g-3">
                    <div class="col-12">
                        <label class="form-label fw-semibold">Title / Subject <span class="text-danger">*</span></label>
                        <div class="input-group">
                            <span class="input-group-text bg-white"><i class="bi bi-type"></i></span>
                            <input name="title" class="form-control <?= !empty($errors['title']) ? 'is-invalid' : '' ?>" value="<?= e($old['title'] ?? '') ?>" placeholder="e.g. General Dormitory Inspection Notice" required>
                        </div>
                        <?php if (!empty($errors['title'])): ?>
                            <div class="text-danger small mt-1"><?= e($errors['title']) ?></div>
                        <?php endif; ?>
                    </div>

                    <div class="col-md-6">
                        <label class="form-label fw-semibold">Target Audience</label>
                        <div class="input-group">
                            <span class="input-group-text bg-white"><i class="bi bi-people"></i></span>
                            <select name="audience" class="form-select">
                                <option value="all" <?= ($old['audience'] ?? 'all') === 'all' ? 'selected' : '' ?>>All House Members & Staff</option>
                                <option value="house_master" <?= ($old['audience'] ?? '') === 'house_master' ? 'selected' : '' ?>>House Masters Only</option>
                            </select>
                        </div>
                    </div>

                    <div class="col-md-6">
                        <label class="form-label fw-semibold">Category / Alert Level</label>
                        <div class="input-group">
                            <span class="input-group-text bg-white"><i class="bi bi-tag"></i></span>
                            <select name="type" class="form-select">
                                <option value="info" <?= ($old['type'] ?? 'info') === 'info' ? 'selected' : '' ?>>General Information</option>
                                <option value="success" <?= ($old['type'] ?? '') === 'success' ? 'selected' : '' ?>>Success / Positive Notice</option>
                                <option value="warning" <?= ($old['type'] ?? '') === 'warning' ? 'selected' : '' ?>>Warning / Advisory</option>
                                <option value="danger" <?= ($old['type'] ?? '') === 'danger' ? 'selected' : '' ?>>Urgent / Critical Notice</option>
                            </select>
                        </div>
                    </div>

                    <div class="col-12">
                        <label class="form-label fw-semibold">Message Content <span class="text-danger">*</span></label>
                        <textarea name="message" class="form-control <?= !empty($errors['message']) ? 'is-invalid' : '' ?>" rows="6" placeholder="Write full announcement message here..." required><?= e($old['message'] ?? '') ?></textarea>
                        <?php if (!empty($errors['message'])): ?>
                            <div class="text-danger small mt-1"><?= e($errors['message']) ?></div>
                        <?php endif; ?>
                    </div>

                    <div class="col-12">
                        <div class="card bg-light border p-3">
                            <div class="d-flex flex-column gap-2">
                                <div class="form-check">
                                    <input type="checkbox" name="sendNotification" class="form-check-input" id="sendNotification" <?= !isset($old['sendNotification']) || $old['sendNotification'] ? 'checked' : '' ?>>
                                    <label class="form-check-label fw-semibold" for="sendNotification">
                                        <i class="bi bi-bell me-1 text-primary"></i>Broadcast in-app push notification
                                    </label>
                                </div>
                                <div class="form-check">
                                    <input type="checkbox" name="isUrgent" class="form-check-input" id="isUrgent" <?= !empty($old['isUrgent']) ? 'checked' : '' ?>>
                                    <label class="form-check-label fw-semibold text-danger" for="isUrgent">
                                        <i class="bi bi-exclamation-triangle me-1"></i>Flag as urgent notice
                                    </label>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="col-12 d-flex justify-content-end gap-2 pt-3 border-top">
                        <a href="<?= url('views/house-master/announcements/index.php') ?>" class="btn btn-outline-secondary">
                            Cancel
                        </a>
                        <button type="submit" class="btn btn-primary">
                            <i class="bi bi-send me-1"></i>Publish Announcement
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
<?php require APP_ROOT . '/app/views/components/footer/footer.php'; ?>