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

use App\Services\EmergencyReferralService;
use App\Services\StudentService;

$pageTitle = 'Referral History';
$referralService = new EmergencyReferralService();
$students = [];
foreach (StudentService::all() as $student) {
    $studentId = (string) ($student['id'] ?? '');
    if ($studentId === '') continue;

    $name = trim(($student['firstName'] ?? '') . ' ' . ($student['lastName'] ?? ''));
    $students[$studentId] = $name !== '' ? $name : 'Unnamed student';
}

$referrals = $referralService->all();
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
                <h4 class="mb-1 fw-bold text-dark"><i class="bi bi-file-earmark-medical-fill text-primary me-2"></i>Referral History</h4>
                <p class="text-muted mb-0">Review recent student referrals and follow-up actions.</p>
            </div>
            <div class="d-flex gap-2 flex-wrap">
                <button type="button" class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#nurseReferralCreate">
                    <i class="bi bi-plus-circle me-1"></i>New Referral
                </button>
                <a class="btn btn-outline-secondary btn-sm" href="<?= url('views/nurse/emergency-cases/emergency-cases.php') ?>">
                    <i class="bi bi-arrow-left me-1"></i>Back
                </a>
            </div>
        </div>

        <div class="card stat-card shadow-sm border-0">
            <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
                <h6 class="mb-0 fw-bold"><i class="bi bi-list-check me-2 text-primary"></i>Saved referrals</h6>
                <small class="text-muted"><?= count($referrals) ?> referral(s)</small>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0 w-100">
                        <thead class="table-light">
                            <tr>
                                <th>Student</th>
                                <th>Facility</th>
                                <th>Urgency</th>
                                <th>Reason</th>
                                <th>Doctor</th>
                                <th>Notes</th>
                                <th>Date</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($referrals)): ?>
                                <tr>
                                    <td colspan="7" class="text-center text-muted py-4">No referrals saved yet.</td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($referrals as $referral): ?>
                                    <tr>
                                        <td class="fw-semibold"><?= e($students[(string) ($referral['studentId'] ?? '')] ?? 'Not linked') ?></td>
                                        <td><?= e((string) ($referral['facility'] ?? '—')) ?></td>
                                        <td><span class="badge bg-<?= ($referral['urgency'] ?? 'urgent') === 'urgent' ? 'danger' : 'secondary' ?>"><?= e(ucfirst((string) ($referral['urgency'] ?? 'urgent'))) ?></span></td>
                                        <td><?= e((string) ($referral['reason'] ?? '—')) ?></td>
                                        <td><?= e((string) ($referral['doctor'] ?? '—')) ?></td>
                                        <td><?= e((string) ($referral['notes'] ?? '—')) ?></td>
                                        <td class="text-muted small"><?= e((string) ($referral['createdAt'] ?? '—')) ?></td>
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
<div class="modal fade" id="nurseReferralCreate" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg modal-dialog-scrollable"><div class="modal-content">
        <form method="POST" action="<?= url('views/nurse/emergency-cases/referral/create.php') ?>" class="nurse-modal-form">
            <input type="hidden" name="returnToReferralHistory" value="1">
            <div class="modal-header"><h5 class="modal-title">Create Referral</h5><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button></div>
            <div class="modal-body"><div class="row g-3">
                <div class="col-md-6"><label class="form-label">Student</label><select name="studentId" class="form-select" required><option value="">Select student</option><?php foreach ($students as $studentId => $name): ?><option value="<?= e((string) $studentId) ?>"><?= e($name) ?></option><?php endforeach; ?></select></div>
                <div class="col-md-6"><label class="form-label">Referral facility</label><input name="facility" class="form-control" required></div>
                <div class="col-md-6"><label class="form-label">Attending doctor</label><input name="doctor" class="form-control"></div>
                <div class="col-md-6"><label class="form-label">Urgency</label><select name="urgency" class="form-select"><option value="urgent">Urgent</option><option value="routine">Routine</option><option value="follow-up">Follow-up</option></select></div>
                <div class="col-12"><label class="form-label">Reason for referral</label><textarea name="reason" class="form-control" rows="4" required></textarea></div>
                <div class="col-12"><label class="form-label">Notes</label><textarea name="notes" class="form-control" rows="3"></textarea></div>
            </div></div>
            <div class="modal-footer"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button><button type="submit" class="btn btn-primary">Save Referral</button></div>
        </form>
    </div></div>
</div>
<?php require APP_ROOT . '/app/views/components/footer/footer.php'; ?>
