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
use App\Services\HouseService;
use App\Services\StudentService;

$role = current_role() ?? '';
$user = current_user() ?? [];
$userId = current_user_id();
$houseId = current_house_id();
$isStudent = $role === ROLE_STUDENT;
$isStaff = in_array($role, [ROLE_ADMIN, ROLE_HOUSE_MASTER, ROLE_HOUSE_MISTRESS, ROLE_SENIOR_HOUSEPARENT], true);

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
    flash('error', 'You do not have permission to edit this exeat record.');
    redirect(url('views/exeat/index.php'));
}

$recStudentId = (string) ($exeat['studentId'] ?? '');
$student = $recStudentId !== '' ? StudentService::find($recStudentId) : null;
$studentName = trim(($student['firstName'] ?? '') . ' ' . ($student['lastName'] ?? '')) ?: ($exeat['studentName'] ?? 'Student');
$admissionNo = $student['admissionNo'] ?? $student['studentId'] ?? $recStudentId;
$guardianPhone = trim((string) ($exeat['guardianPhone'] ?? $student['guardianPhone'] ?? $student['phone'] ?? ''));

$currentType = ($exeat['exeatType'] ?? $exeat['type'] ?? '') === 'internal' ? 'internal' : 'external';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? null)) {
        flash('error', 'The form expired. Please refresh the page and try again.');
        redirect(url('views/exeat/edit/edit.php?id=' . urlencode($id)));
    }

    $exeatType = sanitize($_POST['exeatType'] ?? $currentType);

    $updateData = [
        'exeatType' => $exeatType,
        'startDate' => sanitize($_POST['startDate'] ?? $_POST['date'] ?? ''),
        'endDate' => sanitize($_POST['endDate'] ?? $_POST['date'] ?? ''),
        'startTime' => sanitize($_POST['startTime'] ?? ''),
        'closeTime' => sanitize($_POST['closeTime'] ?? ''),
        'destination' => sanitize($_POST['destination'] ?? ''),
        'guardianPhone' => sanitize($_POST['guardianPhone'] ?? $guardianPhone),
        'reason' => sanitize($_POST['reason'] ?? ''),
    ];

    if ($isStaff && !empty($_POST['status'])) {
        $newStatus = sanitize($_POST['status']);
        if (in_array($newStatus, ['pending', 'approved', 'rejected', 'departed', 'returned'], true)) {
            $updateData['status'] = $newStatus;
        }
    }

    $result = $service->update($id, $updateData);
    flash($result['success'] ? 'success' : 'error', $result['message']);

    if ($result['success']) {
        redirect(url('views/exeat/view/view.php?id=' . urlencode($id)));
    }
}

$dashboardHref = match ($role) {
    ROLE_ADMIN => 'views/admin/dashboard.php',
    ROLE_STUDENT => 'views/student/dashboard/index.php',
    ROLE_SENIOR_HOUSEPARENT => 'views/senior-houseparent/dashboard/index.php',
    default => 'views/house-master/dashboard/index.php',
};

$pageTitle = 'Edit Exeat';
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

        <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-3">
            <div>
                <h4 class="mb-1 fw-bold text-dark"><i class="bi bi-pencil-square text-primary me-2"></i>Edit Exeat Pass</h4>
                <p class="text-muted mb-0">Editing request for <strong><?= e($studentName) ?></strong> (<?= e($admissionNo) ?>)</p>
            </div>
            <a href="<?= url('views/exeat/view/view.php?id=' . urlencode($id)) ?>" class="btn btn-outline-secondary btn-sm">
                <i class="bi bi-arrow-left me-1"></i>Back to Details
            </a>
        </div>

        <div class="card stat-card shadow-sm border-0" style="max-width: 820px;">
            <div class="card-header bg-white py-3">
                <ul class="nav nav-pills card-header-pills" id="exeatTypeTabs" role="tablist">
                    <li class="nav-item" role="presentation">
                        <button class="nav-link <?= $currentType === 'internal' ? 'active' : '' ?>" id="internal-tab" data-bs-toggle="pill" type="button" onclick="setType('internal')">
                            <i class="bi bi-clock-history me-1"></i>Internal Exeat (Start &amp; Close Time)
                        </button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link <?= $currentType === 'external' ? 'active' : '' ?>" id="external-tab" data-bs-toggle="pill" type="button" onclick="setType('external')">
                            <i class="bi bi-calendar2-range me-1"></i>External Exeat (Start &amp; End Date)
                        </button>
                    </li>
                </ul>
            </div>
            <div class="card-body p-4">
                <form method="POST" action="<?= url('views/exeat/edit/edit.php?id=' . urlencode($id)) ?>">
                    <?= csrf_field() ?>
                    <input type="hidden" name="id" value="<?= e($id) ?>">
                    <input type="hidden" name="exeatType" id="selectedExeatType" value="<?= e($currentType) ?>">

                    <?php if ($isStaff): ?>
                        <div class="mb-4 p-3 bg-light rounded-3 border">
                            <label class="form-label fw-semibold">Status Override</label>
                            <select name="status" class="form-select">
                                <?php foreach (['pending', 'approved', 'rejected', 'departed', 'returned'] as $st): ?>
                                    <option value="<?= e($st) ?>" <?= ($exeat['status'] ?? 'pending') === $st ? 'selected' : '' ?>>
                                        <?= e(ucfirst($st)) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    <?php endif; ?>

                    <!-- Internal Exeat Section -->
                    <div id="internalFields" class="<?= $currentType === 'external' ? 'd-none' : '' ?>">
                        <div class="alert alert-info small mb-3">
                            <i class="bi bi-info-circle-fill me-1"></i>
                            <strong>Internal Exeat:</strong> Single-day pass with specific start and close time.
                        </div>

                        <div class="row g-3">
                            <div class="col-md-4">
                                <label class="form-label fw-semibold">Date <span class="text-danger">*</span></label>
                                <div class="input-group">
                                    <span class="input-group-text bg-white"><i class="bi bi-calendar-event"></i></span>
                                    <input type="date" name="date" class="form-control" value="<?= e($exeat['startDate'] ?? $exeat['date'] ?? date('Y-m-d')) ?>">
                                </div>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label fw-semibold">Time to Start <span class="text-danger">*</span></label>
                                <div class="input-group">
                                    <span class="input-group-text bg-white"><i class="bi bi-alarm"></i></span>
                                    <input type="time" name="startTime" class="form-control" value="<?= e($exeat['startTime'] ?? '08:00') ?>">
                                </div>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label fw-semibold">Time to Close / Return <span class="text-danger">*</span></label>
                                <div class="input-group">
                                    <span class="input-group-text bg-white"><i class="bi bi-clock-fill text-danger"></i></span>
                                    <input type="time" name="closeTime" class="form-control" value="<?= e($exeat['closeTime'] ?? $exeat['endTime'] ?? '17:00') ?>">
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- External Exeat Section -->
                    <div id="externalFields" class="<?= $currentType === 'internal' ? 'd-none' : '' ?>">
                        <div class="alert alert-primary small mb-3">
                            <i class="bi bi-info-circle-fill me-1"></i>
                            <strong>External Exeat:</strong> Multi-day travel with departure start date and expected return date.
                        </div>

                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label fw-semibold">Start Date <span class="text-danger">*</span></label>
                                <div class="input-group">
                                    <span class="input-group-text bg-white"><i class="bi bi-calendar-check"></i></span>
                                    <input type="date" name="startDate" class="form-control" value="<?= e($exeat['startDate'] ?? date('Y-m-d')) ?>">
                                </div>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label fw-semibold">End Date <span class="text-danger">*</span></label>
                                <div class="input-group">
                                    <span class="input-group-text bg-white"><i class="bi bi-calendar-x"></i></span>
                                    <input type="date" name="endDate" class="form-control" value="<?= e($exeat['endDate'] ?? date('Y-m-d', strtotime('+1 day'))) ?>">
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Common Fields -->
                    <div class="row g-3 mt-1">
                        <div class="col-12">
                            <label class="form-label fw-semibold">Destination Address / Location</label>
                            <div class="input-group">
                                <span class="input-group-text bg-white"><i class="bi bi-geo-alt"></i></span>
                                <input name="destination" class="form-control" value="<?= e($exeat['destination'] ?? '') ?>" placeholder="Where will the student be staying?">
                            </div>
                        </div>

                        <div class="col-12">
                            <label class="form-label fw-semibold">Parent / Guardian Phone Number</label>
                            <div class="input-group">
                                <span class="input-group-text bg-white"><i class="bi bi-telephone"></i></span>
                                <input type="tel" name="guardianPhone" class="form-control" value="<?= e($guardianPhone) ?>" placeholder="e.g. +233 24 000 0000">
                            </div>
                            <small class="text-muted">This is shown on the exeat and should match the student's registered parent/guardian number.</small>
                        </div>

                        <div class="col-12">
                            <label class="form-label fw-semibold">Reason <span class="text-danger">*</span></label>
                            <textarea name="reason" class="form-control" rows="4" required placeholder="Explain the reason for the exeat..."><?= e($exeat['reason'] ?? '') ?></textarea>
                        </div>
                    </div>

                    <div class="mt-4 pt-3 border-top d-flex justify-content-end gap-2">
                        <a href="<?= url('views/exeat/view/view.php?id=' . urlencode($id)) ?>" class="btn btn-outline-secondary">Cancel</a>
                        <button type="submit" class="btn btn-primary">
                            <i class="bi bi-save me-1"></i>Save Changes
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
function setType(type) {
    document.getElementById('selectedExeatType').value = type;
    const internalFields = document.getElementById('internalFields');
    const externalFields = document.getElementById('externalFields');
    
    if (type === 'internal') {
        internalFields.classList.remove('d-none');
        externalFields.classList.add('d-none');
    } else {
        internalFields.classList.add('d-none');
        externalFields.classList.remove('d-none');
    }
}
</script>
<?php require APP_ROOT . '/app/views/components/footer/footer.php'; ?>

