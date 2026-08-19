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

use App\Services\ParentMessageService;
use App\Services\StudentService;

$currentUser = current_user();
$role = current_role();
$houseId = in_array($role, [ROLE_HOUSEPARENT], true) ? ($currentUser['houseId'] ?? null) : null;
$students = StudentService::all($houseId);
$students = array_values(array_filter($students, fn($student) => ($student['status'] ?? 'active') === 'active'));
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
        ? (new ParentMessageService())->send(
            $selectedStudent,
            sanitize($_POST['subject'] ?? ''),
            sanitize($_POST['message'] ?? ''),
            $currentUser
        )
        : ['success' => false, 'message' => 'Select a valid student.'];
    flash($result['success'] ? 'success' : 'error', $result['message']);
    redirect(base_url('index.php?route=/views/parent-messages/create.php'));
}

$pageTitle = 'Message Student Parents';
$navItems = [
    ['icon' => 'bi-speedometer2', 'label' => 'Dashboard', 'href' => url(ROLE_DASHBOARD[$role] ?? 'index.php')],
    ['icon' => 'bi-chat-left-text', 'label' => 'Message Parents', 'href' => url('views/parent-messages/create.php'), 'active' => true],
];

require APP_ROOT . '/app/views/components/header.php';
require APP_ROOT . '/app/views/components/sidebar.php';
?>
<div class="main-content">
    <?php require APP_ROOT . '/app/views/components/navbar.php'; ?>
    <?php require APP_ROOT . '/app/views/components/alerts.php'; ?>
    <div class="content-wrapper">
        <div class="mb-4">
            <h5 class="mb-1">Message Student Parents</h5>
            <p class="text-muted mb-0">Send a message to a student’s parent or guardian.</p>
        </div>

        <div class="row g-4">
            <div class="col-lg-8">
                <div class="card stat-card p-4">
                    <form method="POST" class="row g-3">
                        <div class="col-12">
                            <label class="form-label" for="studentId">Student</label>
                            <select class="form-select" id="studentId" name="studentId" required onchange="window.location.href='<?= e(url('views/parent-messages/create.php')) ?>?studentId=' + encodeURIComponent(this.value)">
                                <option value="">Select a student</option>
                                <?php foreach ($students as $student): ?>
                                    <option value="<?= e((string) ($student['id'] ?? '')) ?>" <?= $selectedId === (string) ($student['id'] ?? '') ? 'selected' : '' ?>>
                                        <?= e(trim(($student['firstName'] ?? '') . ' ' . ($student['lastName'] ?? ''))) ?>
                                        (<?= e((string) ($student['admissionNo'] ?? '')) ?>)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-12">
                            <label class="form-label" for="subject">Subject</label>
                            <input class="form-control" id="subject" name="subject" maxlength="160" required>
                        </div>
                        <div class="col-12">
                            <label class="form-label" for="message">Message</label>
                            <textarea class="form-control" id="message" name="message" rows="7" maxlength="4000" required></textarea>
                        </div>
                        <div class="col-12 d-flex justify-content-end">
                            <button type="submit" class="btn btn-primary" <?= $selectedStudent ? '' : 'disabled' ?>>
                                <i class="bi bi-send me-1" aria-hidden="true"></i> Send message
                            </button>
                        </div>
                    </form>
                </div>
            </div>
            <div class="col-lg-4">
                <div class="card stat-card p-4 h-100">
                    <h6 class="mb-3">Parent contact</h6>
                    <?php if ($selectedStudent): ?>
                        <div class="mb-3"><div class="text-muted small">Student</div><strong><?= e(trim(($selectedStudent['firstName'] ?? '') . ' ' . ($selectedStudent['lastName'] ?? ''))) ?></strong></div>
                        <div class="mb-3"><div class="text-muted small">Parent / guardian</div><strong><?= e((string) ($selectedStudent['guardianName'] ?? 'Not provided')) ?></strong></div>
                        <div><div class="text-muted small">Phone</div><span><?= e((string) ($selectedStudent['guardianPhone'] ?? 'Not provided')) ?></span></div>
                        <?php if (!empty($selectedStudent['guardianEmail'])): ?><div class="mt-2"><div class="text-muted small">Email</div><span><?= e((string) $selectedStudent['guardianEmail']) ?></span></div><?php endif; ?>
                    <?php else: ?>
                        <p class="text-muted mb-0">Choose a student to view their parent or guardian contact details.</p>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>
<?php require APP_ROOT . '/app/views/components/footer.php'; ?>