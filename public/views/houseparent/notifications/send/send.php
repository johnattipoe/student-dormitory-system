<?php
require_once dirname(__DIR__, 4) . '/bootstrap.php';
$allowedRoles = [ROLE_HOUSEPARENT];
require APP_ROOT . '/app/middleware/RoleMiddleware.php';

use App\Services\NotificationService;
use App\Services\UserService;

$service = new NotificationService();
$allowedRecipientRoles = ['house_master', 'student', 'nurse', 'security'];
$users = array_values(array_filter((new UserService())->all(), static function ($user) use ($allowedRecipientRoles) {
    return in_array((string) ($user['role'] ?? ''), $allowedRecipientRoles, true);
}));

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $userId = sanitize($_POST['userId'] ?? '');
    $selectedUser = null;
    foreach ($users as $user) {
        if ((string) ($user['uid'] ?? $user['id'] ?? '') === $userId) {
            $selectedUser = $user;
            break;
        }
    }

    $result = $selectedUser
        ? $service->create([
            'recipientType' => 'user',
            'userId' => $userId,
            'title' => sanitize($_POST['title'] ?? ''),
            'message' => sanitize($_POST['message'] ?? ''),
            'type' => sanitize($_POST['type'] ?? 'info'),
        ])
        : ['success' => false, 'message' => 'Select a valid recipient.'];
    flash($result['success'] ? 'success' : 'error', $result['message']);
    if ($result['success']) {
        redirect(url('views/houseparent/notifications/index.php'));
    }
}

$pageTitle = 'Send Notification';
$navItems = [
    ['icon' => 'bi-bell', 'label' => 'Notifications', 'href' => url('views/houseparent/notifications/index.php')],
    ['icon' => 'bi-send', 'label' => 'Send Notification', 'href' => url('views/houseparent/notifications/send/send.php'), 'active' => true],
];
require APP_ROOT . '/app/views/components/header.php';
require APP_ROOT . '/app/views/components/sidebar.php';
?>
<div class="main-content">
    <?php require APP_ROOT . '/app/views/components/navbar.php'; ?>
    <?php require APP_ROOT . '/app/views/components/alerts.php'; ?>
    <div class="content-wrapper">
        <div class="card stat-card p-4" style="max-width:760px">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <h5 class="mb-0">Send Notification</h5>
                <a class="btn btn-outline-secondary btn-sm" href="<?= url('views/houseparent/notifications/index.php') ?>">Back</a>
            </div>
            <p class="text-muted">Send a normal in-system notification to one person.</p>
            <form method="POST">
                <label class="form-label">Recipient</label>
                <select name="userId" class="form-select mb-3" required>
                    <option value="">Select person</option>
                    <?php foreach ($users as $user): ?>
                        <?php $userId = (string) ($user['uid'] ?? $user['id'] ?? ''); ?>
                        <option value="<?= e($userId) ?>" <?= ($_POST['userId'] ?? '') === $userId ? 'selected' : '' ?>><?= e($user['name'] ?? $user['email'] ?? 'Unknown') ?> (<?= e(ucwords(str_replace('_', ' ', $user['role'] ?? ''))) ?>)</option>
                    <?php endforeach; ?>
                </select>
                <label class="form-label">Type</label>
                <select name="type" class="form-select mb-3">
                    <?php foreach (['info', 'success', 'warning', 'danger'] as $type): ?>
                        <option value="<?= e($type) ?>" <?= ($_POST['type'] ?? 'info') === $type ? 'selected' : '' ?>><?= ucfirst($type) ?></option>
                    <?php endforeach; ?>
                </select>
                <label class="form-label">Title</label>
                <input name="title" class="form-control mb-3" value="<?= e($_POST['title'] ?? '') ?>" required>
                <label class="form-label">Message</label>
                <textarea name="message" class="form-control" rows="5" required><?= e($_POST['message'] ?? '') ?></textarea>
                <div class="mt-4"><button class="btn btn-primary"><i class="bi bi-send me-1" aria-hidden="true"></i> Send notification</button> <a class="btn btn-outline-secondary" href="<?= url('views/houseparent/notifications/index.php') ?>">Cancel</a></div>
            </form>
        </div>
    </div>
</div>
<?php require APP_ROOT . '/app/views/components/footer.php'; ?>
