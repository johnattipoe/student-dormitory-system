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
$students = StudentService::all($houseId);
$studentIds = array_map(fn($student) => (string) ($student['id'] ?? ''), $students);
$messages = (new ParentMessageService())->all();

if ($houseId) {
	$messages = array_values(array_filter($messages, fn($message) => in_array((string) ($message['studentId'] ?? ''), $studentIds, true)));
}

usort($messages, fn($first, $second) => strcmp((string) ($second['createdAt'] ?? ''), (string) ($first['createdAt'] ?? '')));

// Search & Channel Filter
$search = strtolower(sanitize($_GET['search'] ?? ''));
$channelFilter = sanitize($_GET['channel'] ?? '');

$filteredMessages = array_values(array_filter($messages, function ($msg) use ($search, $channelFilter) {
    $matchesChannel = $channelFilter === '' || ($msg['channel'] ?? 'mail') === $channelFilter;
    
    $matchesSearch = true;
    if ($search !== '') {
        $stName = strtolower((string) ($msg['studentName'] ?? ''));
        $adm = strtolower((string) ($msg['admissionNo'] ?? ''));
        $parent = strtolower((string) ($msg['guardianName'] ?? ''));
        $subject = strtolower((string) ($msg['subject'] ?? ''));
        $body = strtolower((string) ($msg['message'] ?? ''));
        $matchesSearch = str_contains($stName, $search) || str_contains($adm, $search) || str_contains($parent, $search) || str_contains($subject, $search) || str_contains($body, $search);
    }

    return $matchesChannel && $matchesSearch;
}));

$mailCount = count(array_filter($messages, fn($m) => ($m['channel'] ?? 'mail') === 'mail'));
$smsCount = count(array_filter($messages, fn($m) => ($m['channel'] ?? '') === 'sms'));
$sentCount = count(array_filter($messages, fn($m) => ($m['deliveryStatus'] ?? $m['emailStatus'] ?? '') === 'sent'));

$pageTitle = 'Message Parents';
$navItems = [
	['icon' => 'bi-speedometer2', 'label' => 'Dashboard', 'href' => url(ROLE_DASHBOARD[$role] ?? 'index.php')],
	['icon' => 'bi-chat-left-text', 'label' => 'Message Parents', 'href' => url('views/parent-messages/create/create.php'), 'active' => true],
];
require APP_ROOT . '/app/views/components/header/header.php';
require APP_ROOT . '/app/views/components/sidebar/sidebar.php';
?>
<div class="main-content">
	<?php require APP_ROOT . '/app/views/components/navbar/navbar.php'; ?>
	<?php require APP_ROOT . '/app/views/components/alerts/alerts.php'; ?>
	<div class="content-wrapper">
		<div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
			<div>
				<h5 class="mb-1">Parent & Guardian Messages</h5>
				<p class="text-muted mb-0">Direct communications sent to parents via Email and SMS.</p>
			</div>
			<div class="d-flex gap-2">
				<a class="btn btn-primary btn-sm" href="<?= url('views/parent-messages/mail/mail.php') ?>">
					<i class="bi bi-envelope me-1"></i> Send Email
				</a>
				<a class="btn btn-success btn-sm" href="<?= url('views/parent-messages/sms/sms.php') ?>">
					<i class="bi bi-chat-dots me-1"></i> Send SMS
				</a>
			</div>
		</div>

		<div class="row g-3 mb-4">
			<div class="col-sm-6 col-xl-3">
				<div class="card stat-card p-3">
					<div class="text-muted small">Total Messages</div>
					<div class="fs-3 fw-bold"><?= e((string) count($messages)) ?></div>
				</div>
			</div>
			<div class="col-sm-6 col-xl-3">
				<div class="card stat-card p-3">
					<div class="text-muted small">Email Messages</div>
					<div class="fs-3 fw-bold text-primary"><?= e((string) $mailCount) ?></div>
				</div>
			</div>
			<div class="col-sm-6 col-xl-3">
				<div class="card stat-card p-3">
					<div class="text-muted small">SMS Messages</div>
					<div class="fs-3 fw-bold text-success"><?= e((string) $smsCount) ?></div>
				</div>
			</div>
			<div class="col-sm-6 col-xl-3">
				<div class="card stat-card p-3">
					<div class="text-muted small">Delivered / Sent</div>
					<div class="fs-3 fw-bold text-info"><?= e((string) $sentCount) ?></div>
				</div>
			</div>
		</div>

		<div class="card stat-card p-3 mb-3">
			<form method="GET" class="row g-2">
				<div class="col-md-7">
					<input name="search" class="form-control form-control-sm" placeholder="Search student name, admission no, parent, or message..." value="<?= e($search) ?>">
				</div>
				<div class="col-md-3">
					<select name="channel" class="form-select form-select-sm">
						<option value="">All channels (Email & SMS)</option>
						<option value="mail" <?= $channelFilter === 'mail' ? 'selected' : '' ?>>Email only</option>
						<option value="sms" <?= $channelFilter === 'sms' ? 'selected' : '' ?>>SMS only</option>
					</select>
				</div>
				<div class="col-md-2 d-flex gap-1">
					<button class="btn btn-primary btn-sm flex-fill">Filter</button>
					<a class="btn btn-outline-secondary btn-sm" href="<?= url('views/parent-messages/create/create.php') ?>">Reset</a>
				</div>
			</form>
		</div>

		<div class="card stat-card p-3">
			<div class="table-responsive">
				<table class="table table-hover data-table w-100">
					<thead>
						<tr>
							<th>Student</th>
							<th>Parent / Guardian</th>
							<th>Channel</th>
							<th>Subject</th>
							<th>Message Preview</th>
							<th>Delivery</th>
							<th>Sent By</th>
							<th>Date</th>
							<th>Actions</th>
						</tr>
					</thead>
					<tbody>
						<?php if (empty($filteredMessages)): ?>
							<tr>
								<td colspan="9" class="text-center text-muted py-4">No parent messages found.</td>
							</tr>
						<?php else: ?>
							<?php foreach ($filteredMessages as $message): ?>
								<?php 
									$channel = $message['channel'] ?? 'mail'; 
									$status = $message['deliveryStatus'] ?? $message['emailStatus'] ?? 'not configured';
								?>
								<tr>
									<td>
										<strong><?= e($message['studentName'] ?? $message['studentId'] ?? '-') ?></strong>
										<?php if (!empty($message['admissionNo'])): ?>
											<div class="small text-muted">[<?= e($message['admissionNo']) ?>]</div>
										<?php endif; ?>
									</td>
									<td>
										<div><?= e($message['guardianName'] ?? '-') ?></div>
										<div class="small text-muted"><?= e($channel === 'sms' ? ($message['guardianPhone'] ?? '') : ($message['guardianEmail'] ?? '')) ?></div>
									</td>
									<td>
										<span class="badge bg-<?= $channel === 'sms' ? 'success' : 'primary' ?>">
											<i class="bi <?= $channel === 'sms' ? 'bi-chat-dots' : 'bi-envelope' ?> me-1"></i><?= strtoupper($channel) ?>
										</span>
									</td>
									<td><strong><?= e($message['subject'] ?? '-') ?></strong></td>
									<td><?= e(mb_strimwidth((string)($message['message'] ?? '-'), 0, 45, '...')) ?></td>
									<td>
										<span class="badge bg-<?= $status === 'sent' ? 'success' : ($status === 'failed' ? 'danger' : 'secondary') ?>">
											<?= e(ucfirst(str_replace('_', ' ', $status))) ?>
										</span>
									</td>
									<td><?= e($message['sentByName'] ?? '-') ?></td>
									<td class="text-nowrap"><span class="small text-muted"><?= e(substr((string)($message['createdAt'] ?? '-'), 0, 16)) ?></span></td>
									<td class="text-nowrap">
										<a class="btn btn-sm btn-outline-secondary" href="<?= url('views/parent-messages/view/view.php?id=' . urlencode((string) ($message['id'] ?? ''))) ?>" title="View Details"><i class="bi bi-eye"></i></a> 
										<a class="btn btn-sm btn-outline-primary" href="<?= url('views/parent-messages/edit/edit.php?id=' . urlencode((string) ($message['id'] ?? ''))) ?>" title="Edit Record"><i class="bi bi-pencil"></i></a> 
										<a class="btn btn-sm btn-outline-danger" href="<?= url('views/parent-messages/delete/delete.php?id=' . urlencode((string) ($message['id'] ?? ''))) ?>" title="Delete Record"><i class="bi bi-trash"></i></a>
									</td>
								</tr>
							<?php endforeach; ?>
						<?php endif; ?>
					</tbody>
				</table>
			</div>
		</div>
	</div>
</div>
<?php require APP_ROOT . '/app/views/components/footer/footer.php'; ?>
