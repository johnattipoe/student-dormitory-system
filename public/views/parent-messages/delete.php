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
    $result = $service->delete($id);
    flash($result['success'] ? 'success' : 'error', $result['message']);
    redirect(url('views/parent-messages/create.php'));
}

$pageTitle = 'Delete Parent Message';
$navItems = [['icon' => 'bi-chat-left-text', 'label' => 'Message Parents', 'href' => url('views/parent-messages/create.php'), 'active' => true]];
require APP_ROOT . '/app/views/components/header.php';
require APP_ROOT . '/app/views/components/sidebar.php';
?>
<div class="main-content">
    <?php require APP_ROOT . '/app/views/components/navbar.php'; ?>
    <div class="content-wrapper">
        <div class="card stat-card p-4" style="max-width:700px">
            <h5 class="mb-3">Delete Parent Message</h5>
            <p>Delete the message <strong><?= e($message['subject'] ?? 'this message') ?></strong> sent to <?= e($message['guardianName'] ?? 'the parent') ?>?</p>
            <form method="POST">
                <input type="hidden" name="id" value="<?= e($id) ?>">
                <button class="btn btn-danger">Delete message</button>
                <a class="btn btn-outline-secondary" href="<?= url('views/parent-messages/view.php?id=' . urlencode($id)) ?>">Cancel</a>
            </form>
        </div>
    </div>
</div>
<?php require APP_ROOT . '/app/views/components/footer.php'; ?>
