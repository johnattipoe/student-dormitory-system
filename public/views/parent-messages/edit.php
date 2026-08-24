<?php
require_once dirname(__DIR__, 2) . '/bootstrap.php';
$allowedRoles = [ROLE_ADMIN, ROLE_HOUSE_MASTER, ROLE_HOUSE_MISTRESS, ROLE_HOUSEPARENT];
require APP_ROOT . '/app/middleware/RoleMiddleware.php';

use App\Services\ParentMessageService;
use App\Services\StudentService;

$currentUser = current_user();
$role = current_role();
$houseId = $role === ROLE_HOUSEPARENT ? ($currentUser['houseId'] ?? null) : null;
$studentIds = array_map(fn($student) => (string) ($student['id'] ?? ''), StudentService::all($houseId));
$id = sanitize($_GET['id'] ?? $_POST['id'] ?? '');
$service = new ParentMessageService();
$message = $service->find($id);
if (!$message || !in_array((string) ($message['studentId'] ?? ''), $studentIds, true)) {
    flash('error', 'Parent message not found.');
    redirect(url('views/parent-messages/create.php'));
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $result = $service->update($id, sanitize($_POST['subject'] ?? ''), sanitize($_POST['message'] ?? ''));
    flash($result['success'] ? 'success' : 'error', $result['message']);
    if ($result['success']) {
        redirect(url('views/parent-messages/view.php?id=' . urlencode($id)));
    }
    $message['subject'] = $_POST['subject'] ?? '';
    $message['message'] = $_POST['message'] ?? '';
}

$pageTitle = 'Edit Parent Message';
$navItems = [['icon' => 'bi-chat-left-text', 'label' => 'Message Parents', 'href' => url('views/parent-messages/create.php'), 'active' => true]];
require APP_ROOT . '/app/views/components/header.php';
require APP_ROOT . '/app/views/components/sidebar.php';
?>
<div class="main-content">
    <?php require APP_ROOT . '/app/views/components/navbar.php'; ?>
    <div class="content-wrapper">
        <div class="card stat-card p-4" style="max-width:820px">
            <h5 class="mb-3">Edit Parent Message</h5>
            <div class="text-muted mb-3">Student: <?= e($message['studentName'] ?? $message['studentId'] ?? '-') ?> | Parent: <?= e($message['guardianName'] ?? '-') ?></div>
            <form method="POST">
                <input type="hidden" name="id" value="<?= e($id) ?>">
                <label class="form-label">Subject</label>
                <input name="subject" class="form-control mb-3" value="<?= e($message['subject'] ?? '') ?>" required>
                <label class="form-label">Message</label>
                <textarea name="message" class="form-control" rows="7" required><?= e($message['message'] ?? '') ?></textarea>
                <div class="mt-4"><button class="btn btn-primary">Save changes</button> <a class="btn btn-outline-secondary" href="<?= url('views/parent-messages/view.php?id=' . urlencode($id)) ?>">Cancel</a></div>
            </form>
        </div>
    </div>
</div>
<?php require APP_ROOT . '/app/views/components/footer.php'; ?>
