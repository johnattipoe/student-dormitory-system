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

$allowedRoles = [ROLE_ADMIN, ROLE_HOUSE_MASTER, ROLE_HOUSE_MISTRESS, ROLE_SENIOR_HOUSEPARENT];
require APP_ROOT . '/app/middleware/RoleMiddleware/RoleMiddleware.php';

use App\Services\StudentService;
use App\Services\ParentMessageService;

$currentUser = current_user();
$role = current_role();
$channel = $messageChannel === 'sms' ? 'sms' : 'mail';
$channelLabel = $channel === 'sms' ? 'SMS' : 'Mail';
$channelRoute = $channel === 'sms' ? 'sms/sms.php' : 'mail/mail.php';
$houseId = in_array($role, [ROLE_HOUSE_MASTER, ROLE_HOUSE_MISTRESS, ROLE_SENIOR_HOUSEPARENT], true) ? ($currentUser['houseId'] ?? $currentUser['house_id'] ?? null) : null;
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
    if ($result['success']) {
        redirect(url('views/parent-messages/create/create.php'));
    } else {
        redirect(url('views/parent-messages/' . $channelRoute . '?studentId=' . urlencode($selectedId)));
    }
}

$pageTitle = 'Send ' . $channelLabel . ' to Parent';
$navItems = [
    ['icon' => 'bi-speedometer2', 'label' => 'Dashboard', 'href' => url(ROLE_DASHBOARD[$role] ?? 'index.php')],
    ['icon' => 'bi-chat-left-text', 'label' => 'Message Parents', 'href' => url('views/parent-messages/' . $channelRoute), 'active' => true],
];
require APP_ROOT . '/app/views/components/header/header.php';
require APP_ROOT . '/app/views/components/sidebar/sidebar.php';
?>
<div class="main-content">
    <?php require APP_ROOT . '/app/views/components/navbar/navbar.php'; ?>
    <?php require APP_ROOT . '/app/views/components/alerts/alerts.php'; ?>
    <div class="content-wrapper">
        <div class="mb-4 d-flex justify-content-between align-items-start gap-3 flex-wrap">
            <div>
                <h5 class="mb-1">Compose <?= e($channelLabel) ?> to Guardian</h5>
                <p class="text-muted mb-0"><?= $channel === 'sms' ? 'Send a concise text message to the registered guardian phone number.' : 'Send an official email notification with a subject and message body.' ?></p>
            </div>
            <a class="btn btn-outline-secondary" href="<?= url('views/parent-messages/create/create.php') ?>">
                <i class="bi bi-arrow-left me-1"></i> Back to Message Log
            </a>
        </div>

        <div class="row g-4">
            <div class="col-lg-8">
                <div class="card stat-card p-4 h-100">
                    <!-- Quick Template Buttons -->
                    <div class="mb-3">
                        <label class="form-label text-muted small text-uppercase fw-semibold">Quick Templates</label>
                        <div class="d-flex flex-wrap gap-2">
                            <button type="button" class="btn btn-outline-secondary btn-sm" onclick="applyTemplate('General Update', 'Dear Parent/Guardian,\n\nWe would like to share a brief update regarding your ward. Please feel free to reach out to the house office if you have any questions.\n\nBest regards,\nHouse Office')">General Update</button>
                            <button type="button" class="btn btn-outline-secondary btn-sm" onclick="applyTemplate('Health Notice', 'Dear Parent/Guardian,\n\nThis is to notify you that your ward visited the school infirmary today for routine observation/treatment. They are resting comfortably in the dormitory.\n\nBest regards,\nHouse Master')">Health Notice</button>
                            <button type="button" class="btn btn-outline-secondary btn-sm" onclick="applyTemplate('Exeat / Travel Notice', 'Dear Parent/Guardian,\n\nYour ward has been granted weekend exeat permission and is expected to return to the dormitory by 5:00 PM on Sunday.\n\nBest regards,\nHouse Office')">Exeat Notice</button>
                            <button type="button" class="btn btn-outline-secondary btn-sm" onclick="applyTemplate('Important Reminder', 'Dear Parent/Guardian,\n\nPlease be reminded regarding the upcoming dormitory inspection and mid-term items collection.\n\nBest regards,\nHouse Office')">Reminder</button>
                        </div>
                    </div>

                    <form method="POST" class="row g-3">
                        <div class="col-12">
                            <label class="form-label fw-bold" for="studentId">Select Student <span class="text-danger">*</span></label>
                            <select class="form-select" id="studentId" name="studentId" required onchange="window.location.href='<?= e(url('views/parent-messages/' . $channelRoute)) ?>?studentId=' + encodeURIComponent(this.value)">
                                <option value="">-- Choose student --</option>
                                <?php foreach ($students as $student): ?>
                                    <option value="<?= e((string) ($student['id'] ?? '')) ?>" <?= $selectedId === (string) ($student['id'] ?? '') ? 'selected' : '' ?>>
                                        <?= e(trim(($student['firstName'] ?? '') . ' ' . ($student['lastName'] ?? ''))) ?> (<?= e((string) ($student['admissionNo'] ?? '')) ?>) — Class: <?= e($student['class'] ?? '—') ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-12">
                            <label class="form-label fw-bold" for="subject">Subject / Title <span class="text-danger">*</span></label>
                            <input class="form-control" id="subject" name="subject" maxlength="160" value="<?= $channel === 'sms' ? 'Dormitory Notice' : '' ?>" placeholder="e.g. Health Notice, Exeat Update, General Check-in" required>
                        </div>
                        <div class="col-12">
                            <label class="form-label fw-bold" for="message">Message Body <span class="text-danger">*</span></label>
                            <textarea class="form-control" id="message" name="message" rows="<?= $channel === 'sms' ? '4' : '7' ?>" maxlength="<?= $channel === 'sms' ? '160' : '4000' ?>" placeholder="Type your message to the parent here..." required></textarea>
                            <?php if ($channel === 'sms'): ?>
                                <div class="d-flex justify-content-between form-text mt-1">
                                    <span>Standard SMS limit: 160 characters</span>
                                    <span><span id="smsCharacterCount">0</span> / 160 characters</span>
                                </div>
                            <?php endif; ?>
                        </div>
                        <div class="col-12 d-flex justify-content-end gap-2 mt-4">
                            <a href="<?= url('views/parent-messages/create/create.php') ?>" class="btn btn-outline-secondary">Cancel</a>
                            <button type="submit" class="btn btn-primary" <?= $selectedStudent ? '' : 'disabled' ?>>
                                <i class="bi <?= $channel === 'sms' ? 'bi-chat-dots' : 'bi-envelope' ?> me-1"></i> Send <?= e($channelLabel) ?>
                            </button>
                        </div>
                    </form>
                </div>
            </div>
            <div class="col-lg-4">
                <div class="card stat-card p-4 h-100">
                    <h6 class="mb-3 d-flex align-items-center gap-2">
                        <i class="bi bi-person-lines-fill text-primary"></i> Guardian Contact Info
                    </h6>
                    <?php if ($selectedStudent): ?>
                        <div class="mb-3">
                            <div class="text-muted small">Student Name</div>
                            <strong><?= e(trim(($selectedStudent['firstName'] ?? '') . ' ' . ($selectedStudent['lastName'] ?? ''))) ?></strong>
                            <div class="small text-muted">Admission: <?= e($selectedStudent['admissionNo'] ?? '—') ?></div>
                        </div>
                        <div class="mb-3">
                            <div class="text-muted small">Guardian Name</div>
                            <strong><?= e((string) ($selectedStudent['guardianName'] ?? 'Not provided')) ?></strong>
                        </div>
                        <div class="mb-3">
                            <div class="text-muted small">Guardian Phone</div>
                            <strong><?= e((string) ($selectedStudent['guardianPhone'] ?? 'Not provided')) ?></strong>
                        </div>
                        <div class="mb-3">
                            <div class="text-muted small">Guardian Email</div>
                            <strong><?= e((string) ($selectedStudent['guardianEmail'] ?? 'Not provided')) ?></strong>
                        </div>
                        <div class="alert alert-<?= $channel === 'sms' ? (!empty($selectedStudent['guardianPhone']) ? 'success' : 'danger') : (!empty($selectedStudent['guardianEmail']) ? 'success' : 'danger') ?> small py-2 mb-0">
                            <?php if ($channel === 'sms'): ?>
                                <i class="bi bi-info-circle me-1"></i> <?= !empty($selectedStudent['guardianPhone']) ? 'Valid phone on file for SMS.' : 'No phone number found for guardian.' ?>
                            <?php else: ?>
                                <i class="bi bi-info-circle me-1"></i> <?= !empty($selectedStudent['guardianEmail']) ? 'Valid email on file for delivery.' : 'No email address found for guardian.' ?>
                            <?php endif; ?>
                        </div>
                    <?php else: ?>
                        <div class="text-center text-muted py-4">
                            <i class="bi bi-person-bounding-box fs-1 d-block mb-2"></i>
                            <p class="mb-0">Select a student from the dropdown to review guardian contact details.</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <?php if ($channel === 'sms'): ?>
            <div class="row g-4 mt-2">
                <div class="col-12">
                    <div class="card stat-card p-4">
                        <h6 class="mb-2"><i class="bi bi-phone me-1"></i> SMS Message Preview</h6>
                        <div class="border rounded p-3 bg-light" style="min-height:80px; white-space:pre-line" id="smsPreview">Your SMS message will appear here.</div>
                        <div class="small text-muted mt-2"><span id="smsSegmentCount">0</span> SMS segment(s)</div>
                    </div>
                </div>
            </div>
        <?php endif; ?>
    </div>
</div>

<script>
function applyTemplate(subj, body) {
    const subjEl = document.getElementById('subject');
    const msgEl = document.getElementById('message');
    if (subjEl) subjEl.value = subj;
    if (msgEl) {
        msgEl.value = body;
        msgEl.dispatchEvent(new Event('input'));
    }
}

document.addEventListener('DOMContentLoaded', function() {
    const message = document.getElementById('message');
    const count = document.getElementById('smsCharacterCount');
    const preview = document.getElementById('smsPreview');
    const segments = document.getElementById('smsSegmentCount');
    if (!message) return;

    const update = () => {
        const length = message.value.length;
        if (count) {
            count.textContent = length;
            count.classList.toggle('text-danger', length > 160);
        }
        if (preview) {
            preview.textContent = message.value || 'Your SMS message will appear here.';
        }
        if (segments) {
            segments.textContent = Math.max(1, Math.ceil(length / 160));
        }
    };
    message.addEventListener('input', update);
    update();
});
</script>

<?php require APP_ROOT . '/app/views/components/footer/footer.php'; ?>
