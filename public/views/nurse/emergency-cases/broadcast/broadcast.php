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

$allowedRoles = [ROLE_NURSE];
require APP_ROOT . '/app/middleware/RoleMiddleware/RoleMiddleware.php';

use App\Services\EmergencyBroadcastService;
use App\Services\MedicalService;
use App\Services\StudentService;

$pageTitle = 'Emergency Broadcast';
$medicalService = new MedicalService();
$broadcastService = new EmergencyBroadcastService();

$students = [];
foreach (StudentService::all() as $student) {
    $studentId = (string) ($student['id'] ?? '');
    if ($studentId === '') continue;

    $name = trim(($student['firstName'] ?? '') . ' ' . ($student['lastName'] ?? ''));
    $students[$studentId] = [
        'name' => $name !== '' ? $name : 'Unnamed student',
        'admissionNo' => $student['admissionNo'] ?? $student['studentId'] ?? $studentId,
    ];
}

$urgentRecords = array_values(array_filter($medicalService->all(), static function (array $record): bool {
    return in_array(strtolower((string) ($record['severity'] ?? '')), ['severe', 'critical', 'emergency'], true);
}));

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $message = trim((string) ($_POST['message'] ?? ''));
    $recipients = array_values(array_filter(array_map('strval', $_POST['recipients'] ?? []), static fn (string $value): bool => $value !== ''));
    $studentId = trim((string) ($_POST['studentId'] ?? ''));

    $result = $broadcastService->create($message, $recipients, $studentId, current_user_id());
    flash($result['success'] ? 'success' : 'error', $result['message']);

    redirect(url('views/nurse/emergency-cases/broadcast/broadcast.php'));
}

$history = $broadcastService->all();
$navItems = [
    ['icon' => 'bi-speedometer2', 'label' => 'Dashboard', 'href' => url('views/nurse/dashboard/dashboard.php')],
    ['icon' => 'bi-people', 'label' => 'Students', 'href' => url('views/nurse/students/students.php')],
    ['icon' => 'bi-journal-medical', 'label' => 'Medical Records', 'href' => url('views/nurse/medical-records/medical-records.php')],
    ['icon' => 'bi-exclamation-triangle', 'label' => 'Emergency Cases', 'href' => url('views/nurse/emergency-cases/emergency-cases.php'), 'active' => true],
    ['icon' => 'bi-bell', 'label' => 'Notifications', 'href' => url('views/nurse/notifications/notifications.php')],
];

require APP_ROOT . '/app/views/components/header/header.php';
require APP_ROOT . '/app/views/components/sidebar/sidebar.php';
?>
<div class="main-content">
    <?php require APP_ROOT . '/app/views/components/navbar/navbar.php'; ?>
    <div class="content-wrapper">
        <?php require APP_ROOT . '/app/views/components/alerts/alerts.php'; ?>

        <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-3">
            <div>
                <h4 class="mb-1 fw-bold text-dark"><i class="bi bi-megaphone-fill text-warning me-2"></i>Emergency Broadcast</h4>
                <p class="text-muted mb-0">Send a quick urgent update to the relevant care and support contacts.</p>
            </div>
            <a class="btn btn-outline-secondary btn-sm" href="<?= url('views/nurse/emergency-cases/emergency-cases.php') ?>">
                <i class="bi bi-arrow-left me-1"></i>Back to cases
            </a>
        </div>

        <div class="row g-3 mb-4">
            <div class="col-md-4">
                <div class="card stat-card h-100 p-3 border-start border-4 border-danger shadow-sm">
                    <div class="d-flex justify-content-between align-items-start">
                        <div>
                            <span class="text-muted small text-uppercase fw-semibold">Urgent records</span>
                            <h3 class="fw-bold my-1 text-danger"><?= e((string) count($urgentRecords)) ?></h3>
                            <span class="small text-muted">Open critical queue</span>
                        </div>
                        <span class="rounded-3 bg-danger bg-opacity-10 text-danger p-2"><i class="bi bi-exclamation-octagon fs-4"></i></span>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card stat-card h-100 p-3 border-start border-4 border-warning shadow-sm">
                    <div class="d-flex justify-content-between align-items-start">
                        <div>
                            <span class="text-muted small text-uppercase fw-semibold">Target groups</span>
                            <h3 class="fw-bold my-1 text-warning">3</h3>
                            <span class="small text-muted">House, parents, clinical</span>
                        </div>
                        <span class="rounded-3 bg-warning bg-opacity-10 text-warning p-2"><i class="bi bi-people fs-4"></i></span>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card stat-card h-100 p-3 border-start border-4 border-success shadow-sm">
                    <div class="d-flex justify-content-between align-items-start">
                        <div>
                            <span class="text-muted small text-uppercase fw-semibold">Queued sends</span>
                            <h3 class="fw-bold my-1 text-success"><?= e((string) count($history)) ?></h3>
                            <span class="small text-muted">Recent broadcasts</span>
                        </div>
                        <span class="rounded-3 bg-success bg-opacity-10 text-success p-2"><i class="bi bi-broadcast fs-4"></i></span>
                    </div>
                </div>
            </div>
        </div>

        <div class="card stat-card shadow-sm border-0 mb-4">
            <div class="card-header bg-white py-3">
                <h6 class="mb-0 fw-bold"><i class="bi bi-envelope-paper me-2 text-warning"></i>Compose urgent update</h6>
            </div>
            <div class="card-body p-4">
                <form method="POST">
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Send to</label>
                        <div class="row g-2">
                            <div class="col-md-4">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" name="recipients[]" value="house" id="recHouse" checked>
                                    <label class="form-check-label" for="recHouse">House staff</label>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" name="recipients[]" value="parents" id="recParents" checked>
                                    <label class="form-check-label" for="recParents">Parents</label>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" name="recipients[]" value="clinical" id="recClinical" checked>
                                    <label class="form-check-label" for="recClinical">Clinical team</label>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-semibold">Urgent student</label>
                        <select name="studentId" class="form-select">
                            <option value="">Select a student when notifying parents</option>
                            <?php foreach ($urgentRecords as $record): ?>
                                <?php $studentId = (string) ($record['studentId'] ?? ''); ?>
                                <?php if ($studentId !== '' && isset($students[$studentId])): ?>
                                    <option value="<?= e($studentId) ?>"><?= e($students[$studentId]['name'] . ' (' . $students[$studentId]['admissionNo'] . ')') ?></option>
                                <?php endif; ?>
                            <?php endforeach; ?>
                        </select>
                        <small class="text-muted">Required when Parents is selected. The message must be 160 characters or fewer for SMS.</small>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-semibold">Message</label>
                        <textarea name="message" class="form-control" rows="5" placeholder="Example: Student Jane Doe requires immediate follow-up due to severe abdominal pain and has been referred to the clinic.">Student requires urgent medical follow-up. Please check in with the resident and update the clinical team immediately.</textarea>
                    </div>

                    <div class="d-flex justify-content-end">
                        <button type="submit" class="btn btn-warning text-dark">
                            <i class="bi bi-send me-1"></i>Queue Broadcast
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <div class="card stat-card shadow-sm border-0">
            <div class="card-header bg-white py-3">
                <h6 class="mb-0 fw-bold"><i class="bi bi-clock-history me-2 text-primary"></i>Recent broadcast history</h6>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0 w-100">
                        <thead class="table-light">
                            <tr>
                                <th>Sent</th>
                                <th>Recipients</th>
                                <th>Delivery</th>
                                <th>Message</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($history)): ?>
                                <tr>
                                    <td colspan="4" class="text-center text-muted py-4">No broadcasts queued yet.</td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($history as $entry): ?>
                                    <tr>
                                        <td class="text-nowrap small text-muted"><?= e((string) ($entry['createdAt'] ?? '—')) ?></td>
                                        <td><?= e(implode(', ', $entry['recipients'] ?? ['house', 'parents', 'clinical'])) ?></td>
                                        <td>
                                            <span class="badge bg-<?= ($entry['notificationStatus'] ?? '') === 'sent' ? 'success' : 'secondary' ?>">
                                                <?= e((string) ($entry['notificationCount'] ?? 0)) ?> in-app
                                            </span>
                                            <?php if (($entry['parentSmsStatus'] ?? '') !== '' && ($entry['parentSmsStatus'] ?? '') !== 'not_selected'): ?>
                                                <span class="badge bg-<?= ($entry['parentSmsStatus'] ?? '') === 'sent' ? 'success' : 'warning text-dark' ?>">
                                                    Parent SMS: <?= e((string) $entry['parentSmsStatus']) ?>
                                                </span>
                                            <?php endif; ?>
                                        </td>
                                        <td><?= e((string) ($entry['message'] ?? '')) ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>
<?php require APP_ROOT . '/app/views/components/footer/footer.php'; ?>
