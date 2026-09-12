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
$allowedRoles = [ROLE_ADMIN, ROLE_HOUSE_MASTER, ROLE_HOUSE_MISTRESS, ROLE_SENIOR_HOUSEPARENT];
require APP_ROOT . '/app/middleware/RoleMiddleware/RoleMiddleware.php';

use App\Services\ParentMessageService;
use App\Services\StudentService;

$currentUser = current_user();
$role = current_role();
$houseId = in_array($role, [ROLE_HOUSE_MASTER, ROLE_HOUSE_MISTRESS, ROLE_SENIOR_HOUSEPARENT], true) ? ($currentUser['houseId'] ?? $currentUser['house_id'] ?? null) : null;
$studentIds = array_map(fn($student) => (string) ($student['id'] ?? ''), StudentService::all($houseId));
$id = sanitize($_GET['id'] ?? $_POST['id'] ?? '');
$service = new ParentMessageService();
$message = $service->find($id);

if (!$message || ($houseId && !in_array((string) ($message['studentId'] ?? ''), $studentIds, true))) {
    flash('error', 'Parent message not found.');
    redirect(url('views/parent-messages/create/create.php'));
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $result = $service->delete($id);
    flash($result['success'] ? 'success' : 'error', $result['message']);
    redirect(url('views/parent-messages/create/create.php'));
}

$pageTitle = 'Delete Parent Message';
$navItems = [['icon' => 'bi-chat-left-text', 'label' => 'Message Parents', 'href' => url('views/parent-messages/create/create.php'), 'active' => true]];
require APP_ROOT . '/app/views/components/header/header.php';
require APP_ROOT . '/app/views/components/sidebar/sidebar.php';
?>
<div class="main-content">
	<?php require APP_ROOT . '/app/views/components/navbar/navbar.php'; ?>
	<?php require APP_ROOT . '/app/views/components/alerts/alerts.php'; ?>
	<div class="content-wrapper">
		<div class="card stat-card p-4" style="max-width:600px">
			<h5 class="mb-3">Delete Message Record</h5>
			<p>Delete message sent to <strong><?= e($message['guardianName'] ?? 'Parent') ?></strong> regarding <strong><?= e($message['studentName'] ?? 'Student') ?></strong>?</p>
			<form method="POST">
				<input type="hidden" name="id" value="<?= e($id) ?>">
				<button class="btn btn-danger"><i class="bi bi-trash me-1"></i> Confirm Delete</button>
				<a class="btn btn-outline-secondary ms-1" href="<?= url('views/parent-messages/create/create.php') ?>">Cancel</a>
			</form>
		</div>
	</div>
</div>
<?php require APP_ROOT . '/app/views/components/footer/footer.php'; ?>
