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
                <a class="btn btn-primary btn-sm" href="<?= url('views/nurse/emergency-cases/referral/create.php') ?>">
                    <i class="bi bi-plus-circle me-1"></i>New Referral
                </a>
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
<?php require APP_ROOT . '/app/views/components/footer/footer.php'; ?>
