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
$messages = (new ParentMessageService())->all();
if ($houseId) {
	$messages = array_values(array_filter($messages, fn($message) => in_array((string) ($message['studentId'] ?? ''), $studentIds, true)));
}
usort($messages, fn($first, $second) => strcmp((string) ($second['createdAt'] ?? ''), (string) ($first['createdAt'] ?? '')));
$mailMessages = array_values(array_filter($messages, fn($message) => ($message['channel'] ?? 'mail') === 'mail'));
$smsMessages = array_values(array_filter($messages, fn($message) => ($message['channel'] ?? '') === 'sms'));

$renderMessages = static function (array $items): void {
	if (empty($items)) {
		echo '<tr><td colspan="8" class="text-center text-muted">No messages in this channel yet.</td></tr>';
		return;
	}
	foreach ($items as $message): ?>
		<tr>
			<td><?= e($message['studentName'] ?? $message['studentId'] ?? '-') ?></td>
			<td><?= e($message['guardianName'] ?? '-') ?></td>
			<td><?= e($message['subject'] ?? '-') ?></td>
			<td><?= e($message['message'] ?? '-') ?></td>
			<td><span class="badge bg-<?= ($message['deliveryStatus'] ?? $message['emailStatus'] ?? '') === 'sent' ? 'success' : (($message['deliveryStatus'] ?? $message['emailStatus'] ?? '') === 'failed' ? 'danger' : 'secondary') ?>"><?= e(str_replace('_', ' ', $message['deliveryStatus'] ?? $message['emailStatus'] ?? 'not configured')) ?></span></td>
			<td><?= e($message['sentByName'] ?? '-') ?></td>
			<td><?= e($message['createdAt'] ?? '-') ?></td>
			<td class="text-nowrap"><a class="btn btn-sm btn-outline-secondary" href="<?= url('views/parent-messages/view.php?id=' . urlencode((string) ($message['id'] ?? ''))) ?>">View</a> <a class="btn btn-sm btn-outline-primary" href="<?= url('views/parent-messages/edit.php?id=' . urlencode((string) ($message['id'] ?? ''))) ?>">Edit</a> <a class="btn btn-sm btn-outline-danger" href="<?= url('views/parent-messages/delete.php?id=' . urlencode((string) ($message['id'] ?? ''))) ?>">Delete</a></td>
		</tr>
	<?php endforeach;
};

$pageTitle = 'Message Parents';
$navItems = [
	['icon' => 'bi-speedometer2', 'label' => 'Dashboard', 'href' => url(ROLE_DASHBOARD[$role] ?? 'index.php')],
	['icon' => 'bi-chat-left-text', 'label' => 'Message Parents', 'href' => url('views/parent-messages/create.php'), 'active' => true],
];
require APP_ROOT . '/app/views/components/header.php';
require APP_ROOT . '/app/views/components/sidebar.php';
?>
<div class="main-content">
	<?php require APP_ROOT . '/app/views/components/navbar.php'; ?>
	<div class="content-wrapper">
		<div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-3">
			<div><h5 class="mb-1">Message Student Parents</h5><p class="text-muted mb-0">Choose a channel or review previously sent messages.</p></div>
			<div class="d-flex gap-2"><a class="btn btn-primary" href="<?= url('views/parent-messages/mail.php') ?>"><i class="bi bi-envelope me-1" aria-hidden="true"></i> Mail</a><a class="btn btn-success" href="<?= url('views/parent-messages/sms.php') ?>"><i class="bi bi-chat-dots me-1" aria-hidden="true"></i> SMS</a></div>
		</div>

		<?php foreach ([['Mail messages', $mailMessages, 'primary'], ['SMS messages', $smsMessages, 'success']] as [$title, $items, $color]): ?>
			<div class="card stat-card p-3 mb-4">
				<div class="d-flex justify-content-between align-items-center mb-3"><h6 class="mb-0"><?= e($title) ?></h6><span class="badge bg-<?= e($color) ?>"><?= count($items) ?></span></div>
				<div class="table-responsive"><table class="table table-hover align-middle mb-0" data-no-data-table="true"><thead><tr><th>Student</th><th>Parent / guardian</th><th>Subject</th><th>Message</th><th>Delivery</th><th>Sent by</th><th>Sent at</th><th>Actions</th></tr></thead><tbody><?php $renderMessages($items); ?></tbody></table></div>
			</div>
		<?php endforeach; ?>
	</div>
</div>
<?php require APP_ROOT . '/app/views/components/footer.php'; ?>
