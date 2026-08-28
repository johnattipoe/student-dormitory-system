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
$id = sanitize($_GET['id'] ?? '');
$message = (new ParentMessageService())->find($id);

if (!$message || ($houseId && !in_array((string) ($message['studentId'] ?? ''), $studentIds, true))) {
    flash('error', 'Parent message not found.');
    redirect(url('views/parent-messages/create/create.php'));
}

$pageTitle = 'Parent Message Details';
$navItems = [['icon' => 'bi-chat-left-text', 'label' => 'Message Parents', 'href' => url('views/parent-messages/create/create.php'), 'active' => true]];
require APP_ROOT . '/app/views/components/header/header.php';
require APP_ROOT . '/app/views/components/sidebar/sidebar.php';
?>
<div class="main-content">
    <?php require APP_ROOT . '/app/views/components/navbar/navbar.php'; ?>
    <div class="content-wrapper">
        <div class="card stat-card p-4" style="max-width:820px">
            <div class="d-flex justify-content-between align-items-start">
                <div>
                    <h5><?= e($message['subject'] ?? 'Parent message') ?></h5>
                    <div class="text-muted">Sent to <?= e($message['guardianName'] ?? '-') ?></div>
                </div>
                <a class="btn btn-outline-secondary btn-sm" href="<?= url('views/parent-messages/create/create.php') ?>">Back</a>
            </div>
            <dl class="row mt-4">
                <dt class="col-sm-4">Student</dt>
                <dd class="col-sm-8"><?= e($message['studentName'] ?? $message['studentId'] ?? '-') ?></dd>
                
                <dt class="col-sm-4">Parent / Guardian</dt>
                <dd class="col-sm-8"><?= e($message['guardianName'] ?? '-') ?></dd>
                
                <dt class="col-sm-4">Delivery Channel</dt>
                <dd class="col-sm-8"><span class="badge bg-secondary"><?= e(strtoupper((string)($message['channel'] ?? 'mail'))) ?></span></dd>
                
                <dt class="col-sm-4">Delivery Status</dt>
                <dd class="col-sm-8">
                    <span class="badge bg-<?= ($message['deliveryStatus'] ?? $message['emailStatus'] ?? '') === 'sent' ? 'success' : (($message['deliveryStatus'] ?? $message['emailStatus'] ?? '') === 'failed' ? 'danger' : 'secondary') ?>">
                        <?= e(ucfirst(str_replace('_', ' ', (string)($message['deliveryStatus'] ?? $message['emailStatus'] ?? 'not configured')))) ?>
                    </span>
                    <?php if (!empty($message['deliveryNote'])): ?>
                        <div class="small text-muted mt-1"><?= e($message['deliveryNote']) ?></div>
                    <?php endif; ?>
                </dd>
                
                <dt class="col-sm-4">Sent by</dt>
                <dd class="col-sm-8"><?= e($message['sentByName'] ?? '-') ?></dd>
                
                <dt class="col-sm-4">Sent at</dt>
                <dd class="col-sm-8"><?= e($message['createdAt'] ?? '-') ?></dd>
            </dl>
            <div class="border rounded p-3 mb-4" style="white-space:pre-line"><?= e($message['message'] ?? '') ?></div>
            <div class="d-flex gap-2">
                <a class="btn btn-primary" href="<?= url('views/parent-messages/edit/edit.php?id=' . urlencode($id)) ?>"><i class="bi bi-pencil me-1"></i> Edit Message</a>
                <a class="btn btn-outline-danger" href="<?= url('views/parent-messages/delete/delete.php?id=' . urlencode($id)) ?>"><i class="bi bi-trash me-1"></i> Delete Message</a>
            </div>
        </div>
    </div>
</div>
<?php require APP_ROOT . '/app/views/components/footer/footer.php'; ?>
