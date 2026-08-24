<?php
require_once dirname(__DIR__, 2) . '/bootstrap.php';
$allowedRoles = [ROLE_ADMIN, ROLE_HOUSE_MASTER, ROLE_HOUSE_MISTRESS, ROLE_HOUSEPARENT];
require APP_ROOT . '/app/middleware/RoleMiddleware.php';

use App\Services\ParentMessageService;
use App\Services\StudentService;

$currentUser = current_user();
$role = current_role();
$houseId = $role === ROLE_HOUSEPARENT ? ($currentUser['houseId'] ?? null) : null;
$students = StudentService::all($houseId);
$studentIds = array_map(fn($student) => (string) ($student['id'] ?? ''), $students);
$id = sanitize($_GET['id'] ?? '');
$message = (new ParentMessageService())->find($id);
if (!$message || !in_array((string) ($message['studentId'] ?? ''), $studentIds, true)) {
    flash('error', 'Parent message not found.');
    redirect(url('views/parent-messages/create.php'));
}

$pageTitle = 'Parent Message Details';
$navItems = [['icon' => 'bi-chat-left-text', 'label' => 'Message Parents', 'href' => url('views/parent-messages/create.php'), 'active' => true]];
require APP_ROOT . '/app/views/components/header.php';
require APP_ROOT . '/app/views/components/sidebar.php';
?>
<div class="main-content">
    <?php require APP_ROOT . '/app/views/components/navbar.php'; ?>
    <div class="content-wrapper">
        <div class="card stat-card p-4" style="max-width:820px">
            <div class="d-flex justify-content-between align-items-start">
                <div><h5><?= e($message['subject'] ?? 'Parent message') ?></h5><div class="text-muted">Sent to <?= e($message['guardianName'] ?? '-') ?></div></div>
                <a class="btn btn-outline-secondary btn-sm" href="<?= url('views/parent-messages/create.php') ?>">Back</a>
            </div>
            <dl class="row mt-4">
                <dt class="col-sm-4">Student</dt><dd class="col-sm-8"><?= e($message['studentName'] ?? $message['studentId'] ?? '-') ?></dd>
                <dt class="col-sm-4">Parent / guardian</dt><dd class="col-sm-8"><?= e($message['guardianName'] ?? '-') ?></dd>
                <dt class="col-sm-4">Email status</dt><dd class="col-sm-8"><?= e(str_replace('_', ' ', $message['emailStatus'] ?? 'not configured')) ?></dd>
                <dt class="col-sm-4">Sent by</dt><dd class="col-sm-8"><?= e($message['sentByName'] ?? '-') ?></dd>
                <dt class="col-sm-4">Sent at</dt><dd class="col-sm-8"><?= e($message['createdAt'] ?? '-') ?></dd>
            </dl>
            <div class="border rounded p-3 mb-4" style="white-space:pre-line"><?= e($message['message'] ?? '') ?></div>
            <div><a class="btn btn-primary" href="<?= url('views/parent-messages/edit.php?id=' . urlencode($id)) ?>">Edit message</a> <a class="btn btn-outline-danger" href="<?= url('views/parent-messages/delete.php?id=' . urlencode($id)) ?>">Delete message</a></div>
        </div>
    </div>
</div>
<?php require APP_ROOT . '/app/views/components/footer.php'; ?>
