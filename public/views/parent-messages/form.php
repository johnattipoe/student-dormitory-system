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

$allowedRoles = [ROLE_ADMIN, ROLE_HOUSE_MASTER, ROLE_HOUSE_MISTRESS, ROLE_HOUSEPARENT];
require APP_ROOT . '/app/middleware/RoleMiddleware.php';

use App\Services\StudentService;
use App\Services\ParentMessageService;

$currentUser = current_user();
$role = current_role();
$channel = $messageChannel === 'sms' ? 'sms' : 'mail';
$channelLabel = $channel === 'sms' ? 'SMS' : 'Mail';
$channelRoute = $channel === 'sms' ? 'sms.php' : 'mail.php';
$houseId = $role === ROLE_HOUSEPARENT ? ($currentUser['houseId'] ?? null) : null;
$students = array_values(array_filter(StudentService::all($houseId), fn($student) => ($student['status'] ?? 'active') === 'active'));
$messageService = new ParentMessageService();
$selectedId = sanitize($_POST['studentId'] ?? $_GET['studentId'] ?? '');
$selectedStudent = null;
foreach ($students as $student) {
    if ((string) ($student['id'] ?? '') === $selectedId) {
        $selectedStudent = $student;
        break;
    }
}
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $result = $selectedStudent
        ? $messageService->send($selectedStudent, sanitize($_POST['subject'] ?? ''), sanitize($_POST['message'] ?? ''), $currentUser, $channel)
        : ['success' => false, 'message' => 'Select a valid student.'];
    flash($result['success'] ? 'success' : 'error', $result['message']);
    redirect(base_url('index.php?route=/views/parent-messages/' . $channelRoute));
}

$pageTitle = $channelLabel . ' Student Parents';
$navItems = [
    ['icon' => 'bi-speedometer2', 'label' => 'Dashboard', 'href' => url(ROLE_DASHBOARD[$role] ?? 'index.php')],
    ['icon' => 'bi-chat-left-text', 'label' => 'Message Parents', 'href' => url('views/parent-messages/' . $channelRoute), 'active' => true],
];
require APP_ROOT . '/app/views/components/header.php';
require APP_ROOT . '/app/views/components/sidebar.php';
?>
<div class="main-content">
    <?php require APP_ROOT . '/app/views/components/navbar.php'; ?>
    <?php require APP_ROOT . '/app/views/components/alerts.php'; ?>
    <div class="content-wrapper">
        <div class="mb-4 d-flex justify-content-between align-items-start gap-3 flex-wrap">
            <div>
                <h5 class="mb-1"><?= e($channelLabel) ?> Student Parents</h5>
                <p class="text-muted mb-0"><?= $channel === 'sms' ? 'Send a short text message to the guardian phone number.' : 'Send an email with a subject and full message to the guardian email address.' ?></p>
            </div>
            <a class="btn btn-outline-secondary" href="<?= url('views/parent-messages/create.php') ?>"><i class="bi bi-arrow-left me-1" aria-hidden="true"></i> Message Parents</a>
        </div>
        <div class="row g-4">
            <div class="col-lg-8">
                <div class="card stat-card p-4 h-100">
                    <form method="POST" class="row g-3">
                        <div class="col-12">
                            <label class="form-label" for="studentId">Student</label>
                            <select class="form-select" id="studentId" name="studentId" required onchange="window.location.href='<?= e(url('views/parent-messages/' . $channelRoute)) ?>?channel=<?= e($channel) ?>&studentId=' + encodeURIComponent(this.value)">
                                <option value="">Select a student</option>
                                <?php foreach ($students as $student): ?>
                                    <option value="<?= e((string) ($student['id'] ?? '')) ?>" <?= $selectedId === (string) ($student['id'] ?? '') ? 'selected' : '' ?>><?= e(trim(($student['firstName'] ?? '') . ' ' . ($student['lastName'] ?? ''))) ?> (<?= e((string) ($student['admissionNo'] ?? '')) ?>)</option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-12">
                            <label class="form-label" for="subject">Subject</label>
                            <input class="form-control" id="subject" name="subject" maxlength="160" value="<?= $channel === 'sms' ? 'Parent message' : '' ?>" required>
                        </div>
                        <div class="col-12">
                            <label class="form-label" for="message">Message</label>
                            <textarea class="form-control" id="message" name="message" rows="<?= $channel === 'sms' ? '4' : '7' ?>" maxlength="<?= $channel === 'sms' ? '160' : '4000' ?>" required></textarea>
                            <?php if ($channel === 'sms'): ?><div class="d-flex justify-content-between form-text"><span>Maximum 160 characters</span><span><span id="smsCharacterCount">0</span>/160 characters</span></div><?php endif; ?>
                        </div>
                        <div class="col-12 d-flex justify-content-end"><button type="submit" class="btn btn-primary" <?= $selectedStudent ? '' : 'disabled' ?>><i class="bi <?= $channel === 'sms' ? 'bi-chat-dots' : 'bi-envelope' ?> me-1" aria-hidden="true"></i> Send <?= e($channelLabel) ?></button></div>
                    </form>
                </div>
            </div>
            <div class="col-lg-4">
                <div class="card stat-card p-4 h-100"><h6 class="mb-3">Parent contact</h6><?php if ($selectedStudent): ?><div class="mb-3"><div class="text-muted small">Student</div><strong><?= e(trim(($selectedStudent['firstName'] ?? '') . ' ' . ($selectedStudent['lastName'] ?? ''))) ?></strong></div><div class="mb-3"><div class="text-muted small">Parent / guardian</div><strong><?= e((string) ($selectedStudent['guardianName'] ?? 'Not provided')) ?></strong></div><div><div class="text-muted small"><?= $channel === 'sms' ? 'SMS phone number' : 'Email address' ?></div><span><?= e((string) ($selectedStudent[$channel === 'sms' ? 'guardianPhone' : 'guardianEmail'] ?? 'Not provided')) ?></span></div><?php else: ?><p class="text-muted mb-0">Choose a student to view their parent or guardian contact details.</p><?php endif; ?></div>
            </div>
        </div>
        <?php if ($channel === 'sms'): ?>
            <div class="row g-4 mt-1">
                <div class="col-lg-4">
                    <div class="card stat-card p-4 h-100">
                        <h6 class="mb-3">SMS delivery</h6>
                        <div class="d-flex justify-content-between border-bottom py-2"><span class="text-muted">Recipient</span><strong><?= e($selectedStudent['guardianName'] ?? 'Not selected') ?></strong></div>
                        <div class="d-flex justify-content-between border-bottom py-2"><span class="text-muted">Phone</span><strong><?= e($selectedStudent['guardianPhone'] ?? 'Not provided') ?></strong></div>
                        <div class="d-flex justify-content-between py-2"><span class="text-muted">Status</span><span class="badge bg-secondary">Provider required</span></div>
                        <p class="small text-muted mb-0 mt-3">The message will be recorded. Connect an SMS provider to deliver it automatically.</p>
                    </div>
                </div>
                <div class="col-lg-8">
                    <div class="card stat-card p-4 h-100">
                        <h6 class="mb-3">Message preview</h6>
                        <div class="border rounded p-3 bg-light" style="min-height:110px; white-space:pre-line" id="smsPreview">Your SMS message will appear here.</div>
                        <div class="small text-muted mt-2"><span id="smsSegmentCount">0</span> SMS segment</div>
                    </div>
                </div>
            </div>
        <?php endif; ?>
    </div>
</div>
<?php require APP_ROOT . '/app/views/components/footer.php'; ?>
<?php if ($channel === 'sms'): ?><script>document.addEventListener('DOMContentLoaded',function(){const message=document.getElementById('message');const count=document.getElementById('smsCharacterCount');const preview=document.getElementById('smsPreview');const segments=document.getElementById('smsSegmentCount');if(!message)return;const update=()=>{const length=message.value.length;count.textContent=length;count.classList.toggle('text-danger',length>160);preview.textContent=message.value||'Your SMS message will appear here.';segments.textContent=Math.max(1,Math.ceil(length/160));};message.addEventListener('input',update);update();});</script><?php endif; ?>
