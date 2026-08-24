<?php
require_once dirname(__DIR__, 3) . '/bootstrap.php';
redirect(url('views/houseparent/notifications/send/send.php'));

use App\Services\NotificationService;

$service = new NotificationService();
$recipientRoles = [
    'house_master' => 'House Master',
    'student' => 'Student',
    'nurse' => 'Nurse',
    'security' => 'Security',
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $recipientRole = sanitize($_POST['role'] ?? '');
    $result = isset($recipientRoles[$recipientRole])
        ? $service->create([
            'recipientType' => 'role',
            'role' => $recipientRole,
            'title' => sanitize($_POST['title'] ?? ''),
            'message' => sanitize($_POST['message'] ?? ''),
            'type' => sanitize($_POST['type'] ?? 'info'),
        ])
        : ['success' => false, 'message' => 'Select a valid recipient role.'];
    flash($result['success'] ? 'success' : 'error', $result['message']);
    if ($result['success']) {
        redirect(url('views/houseparent/notifications/index.php'));
    }
}

$pageTitle = 'Send Notification';
$navItems = [
    ['icon' => 'bi-bell', 'label' => 'Notifications', 'href' => url('views/houseparent/notifications/index.php')],
    ['icon' => 'bi-send', 'label' => 'Send Notification', 'href' => url('views/houseparent/notifications/send.php'), 'active' => true],
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
            <p class="text-muted">Send a normal in-system notification to a selected staff or student group.</p>
            <form method="POST">
                <label class="form-label">Send to</label>
                <select name="role" class="form-select mb-3" required>
                    <option value="">Select recipient</option>
                    <?php foreach ($recipientRoles as $value => $label): ?>
                        <option value="<?= e($value) ?>" <?= ($_POST['role'] ?? '') === $value ? 'selected' : '' ?>><?= e($label) ?></option>
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