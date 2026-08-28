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

$allowedRoles = [ROLE_ADMIN, ROLE_HOUSE_MASTER, ROLE_HOUSE_MISTRESS, ROLE_SENIOR_HOUSEPARENT, ROLE_STUDENT];
require APP_ROOT . '/app/middleware/RoleMiddleware/RoleMiddleware.php';

use App\Services\ExeatService;
use App\Services\StudentService;

$role = current_role() ?? '';
$user = current_user() ?? [];
$userId = current_user_id();
$houseId = current_house_id();
$isStudent = $role === ROLE_STUDENT;

$id = sanitize($_GET['id'] ?? $_POST['id'] ?? '');
$service = new ExeatService();
$exeat = $service->find($id);

if (!$exeat) {
    flash('error', 'Exeat record was not found.');
    redirect(url('views/exeat/index.php'));
}

$studentProfile = $isStudent ? $service->studentForUser($user) : null;
$studentId = $isStudent ? (string) ($studentProfile['id'] ?? '') : null;

// Check visibility for role
$visibleRecords = $service->visibleForRole($role, $userId, $houseId, $studentId);
$visibleIds = array_fill_keys(array_map(static fn(array $r): string => (string) ($r['id'] ?? ''), $visibleRecords), true);

if (!isset($visibleIds[$id])) {
    flash('error', 'You do not have permission to delete this exeat record.');
    redirect(url('views/exeat/index.php'));
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? null)) {
        flash('error', 'The form expired. Please refresh the page and try again.');
        redirect(url('views/exeat/delete/delete.php?id=' . urlencode($id)));
    }

    $result = $service->delete($id);
    flash($result['success'] ? 'success' : 'error', $result['message']);
    redirect(url('views/exeat/index.php'));
}

$recStudentId = (string) ($exeat['studentId'] ?? '');
$student = $recStudentId !== '' ? StudentService::find($recStudentId) : null;
$studentName = trim(($student['firstName'] ?? '') . ' ' . ($student['lastName'] ?? '')) ?: ($exeat['studentName'] ?? 'Student');
$admissionNo = $student['admissionNo'] ?? $student['studentId'] ?? $recStudentId;
$isInternal = ($exeat['exeatType'] ?? $exeat['type'] ?? '') === 'internal';

$dashboardHref = match ($role) {
    ROLE_ADMIN => 'views/admin/dashboard.php',
    ROLE_STUDENT => 'views/student/dashboard/index.php',
    ROLE_SENIOR_HOUSEPARENT => 'views/senior-houseparent/dashboard/index.php',
    default => 'views/house-master/dashboard/index.php',
};

$pageTitle = 'Delete Exeat';
$navItems = [
    ['icon' => 'bi-speedometer2', 'label' => 'Dashboard', 'href' => url($dashboardHref)],
    ['icon' => 'bi-calendar2-week', 'label' => 'Exeat', 'href' => url('views/exeat/index.php'), 'active' => true],
];

require APP_ROOT . '/app/views/components/header/header.php';
require APP_ROOT . '/app/views/components/sidebar/sidebar.php';
?>
<div class="main-content">
    <?php require APP_ROOT . '/app/views/components/navbar/navbar.php'; ?>
    <div class="content-wrapper">
        <?php require APP_ROOT . '/app/views/components/alerts/alerts.php'; ?>

        <div class="row justify-content-center">
            <div class="col-md-7 col-lg-6">
                <div class="card stat-card shadow-sm border-0 border-top border-4 border-danger p-4">
                    <div class="text-center mb-4">
                        <div class="rounded-circle bg-danger bg-opacity-10 text-danger d-inline-flex align-items-center justify-content-center mb-3" style="width: 70px; height: 70px;">
                            <i class="bi bi-trash3-fill fs-2"></i>
                        </div>
                        <h5 class="fw-bold text-danger mb-1">Delete Exeat Record</h5>
                        <p class="text-muted small">Are you sure you want to permanently delete this exeat record? This action cannot be undone.</p>
                    </div>

                    <div class="bg-light p-3 rounded-3 border mb-4">
                        <div class="d-flex justify-content-between mb-2">
                            <span class="text-muted small">Student:</span>
                            <strong><?= e($studentName) ?> (<?= e($admissionNo) ?>)</strong>
                        </div>
                        <div class="d-flex justify-content-between mb-2">
                            <span class="text-muted small">Type:</span>
                            <span class="badge bg-<?= $isInternal ? 'info' : 'primary' ?>"><?= $isInternal ? 'Internal' : 'External' ?></span>
                        </div>
                        <div class="d-flex justify-content-between mb-2">
                            <span class="text-muted small">Schedule:</span>
                            <span>
                                <?php if ($isInternal): ?>
                                    <?= e($exeat['startDate'] ?? $exeat['date'] ?? '—') ?> (<?= e($exeat['startTime'] ?? '--') ?> - <?= e($exeat['closeTime'] ?? $exeat['endTime'] ?? '--') ?>)
                                <?php else: ?>
                                    <?= e($exeat['startDate'] ?? '—') ?> to <?= e($exeat['endDate'] ?? '—') ?>
                                <?php endif; ?>
                            </span>
                        </div>
                        <div class="d-flex justify-content-between mb-2">
                            <span class="text-muted small">Destination:</span>
                            <span><?= e($exeat['destination'] ?? '—') ?></span>
                        </div>
                        <div class="d-flex justify-content-between mb-0">
                            <span class="text-muted small">Status:</span>
                            <span class="badge bg-secondary"><?= e(ucfirst((string)($exeat['status'] ?? 'pending'))) ?></span>
                        </div>
                    </div>

                    <form method="POST" action="<?= url('views/exeat/delete/delete.php?id=' . urlencode($id)) ?>" class="d-flex justify-content-center gap-2">
                        <?= csrf_field() ?>
                        <input type="hidden" name="id" value="<?= e($id) ?>">
                        <a href="<?= url('views/exeat/index.php') ?>" class="btn btn-outline-secondary px-4">Cancel</a>
                        <button type="submit" class="btn btn-danger px-4">
                            <i class="bi bi-trash me-1"></i>Yes, Delete Permanently
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>
<?php require APP_ROOT . '/app/views/components/footer/footer.php'; ?>

