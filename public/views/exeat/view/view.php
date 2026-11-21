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
use App\Services\RoomService;

$role = current_role() ?? '';
$user = current_user() ?? [];
$userId = current_user_id();
$houseId = current_house_id();
$isStudent = $role === ROLE_STUDENT;
$isStaff = in_array($role, [ROLE_ADMIN, ROLE_HOUSE_MASTER, ROLE_HOUSE_MISTRESS, ROLE_SENIOR_HOUSEPARENT], true);

$id = sanitize($_GET['id'] ?? '');
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
    flash('error', 'You do not have permission to view this exeat record.');
    redirect(url('views/exeat/index.php'));
}

// Student info
$recStudentId = (string) ($exeat['studentId'] ?? '');
$student = $recStudentId !== '' ? StudentService::find($recStudentId) : null;
$studentName = trim(($student['firstName'] ?? '') . ' ' . ($student['lastName'] ?? '')) ?: ($exeat['studentName'] ?? 'Unknown Student');
$admissionNo = $student['admissionNo'] ?? $student['studentId'] ?? $recStudentId;
$studentHouseId = $exeat['houseId'] ?? $student['houseId'] ?? null;
$house = $studentHouseId ? HouseService::find((string) $studentHouseId) : null;
$houseName = $house['name'] ?? ($studentHouseId ?: '—');
$room = !empty($student['roomId']) ? RoomService::find((string) $student['roomId']) : null;
$roomNumber = $room['roomNumber'] ?? $room['name'] ?? ($student['roomId'] ?? '—');
$guardianPhone = $exeat['guardianPhone'] ?? $student['guardianPhone'] ?? $student['phone'] ?? '—';

$exeatType = strtolower((string) ($exeat['exeatType'] ?? $exeat['type'] ?? 'external'));
$isInternal = $exeatType === 'internal';
$isVacation = $exeatType === 'vacation';
$typeLabel = $isInternal ? 'Internal' : ($isVacation ? 'Vacation' : 'External');
$status = strtolower((string) ($exeat['status'] ?? 'pending'));

$statusClasses = [
    'pending' => 'warning text-dark',
    'approved' => 'success',
    'rejected' => 'danger',
    'departed' => 'info text-dark',
    'returned' => 'secondary',
];

$dashboardHref = match ($role) {
    ROLE_ADMIN => 'views/admin/dashboard.php',
    ROLE_STUDENT => 'views/student/dashboard/index.php',
    ROLE_SENIOR_HOUSEPARENT => 'views/senior-houseparent/dashboard/index.php',
    default => 'views/house-master/dashboard/index.php',
};

$pageTitle = 'Exeat Details';
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
                <h4 class="mb-1 fw-bold text-dark"><i class="bi bi-file-earmark-text text-primary me-2"></i>Exeat Pass Details</h4>
                <p class="text-muted mb-0">Record Reference: <span class="font-monospace text-primary fw-semibold"><?= e($id) ?></span></p>
            </div>
            <div class="d-flex gap-2 flex-wrap">
                <a href="<?= url('views/exeat/index.php') ?>" class="btn btn-outline-secondary btn-sm">
                    <i class="bi bi-arrow-left me-1"></i>Back to Exeats
                </a>
                <button type="button" class="btn btn-outline-warning btn-sm" data-bs-toggle="modal" data-bs-target="#detailEditExeatModal">
                    <i class="bi bi-pencil me-1"></i>Edit Pass
                </button>
                <button type="button" class="btn btn-outline-danger btn-sm" data-bs-toggle="modal" data-bs-target="#detailDeleteExeatModal">
                    <i class="bi bi-trash me-1"></i>Delete
                </button>
                <button type="button" class="btn btn-primary btn-sm" onclick="window.print()">
                    <i class="bi bi-printer me-1"></i>Print Pass
                </button>
            </div>
        </div>

        <div class="row g-4">
            <!-- Left Card: Resident & Status -->
            <div class="col-lg-4">
                <div class="card stat-card shadow-sm border-0 mb-4">
                    <div class="card-body text-center p-4">
                        <div class="rounded-circle bg-primary bg-opacity-10 text-primary d-inline-flex align-items-center justify-content-center mb-3" style="width: 70px; height: 70px;">
                            <i class="bi bi-person-badge fs-2"></i>
                        </div>
                        <h5 class="fw-bold mb-1"><?= e($studentName) ?></h5>
                        <p class="text-muted small mb-3"><?= e($admissionNo) ?></p>

                        <div class="d-flex justify-content-center gap-2 mb-3">
                            <span class="badge bg-<?= e($statusClasses[$status] ?? 'secondary') ?> px-3 py-2 fs-6">
                                Status: <?= e(ucfirst($status)) ?>
                            </span>
                            <?php if ($isInternal): ?>
                                <span class="badge bg-info text-white px-3 py-2 fs-6"><i class="bi bi-clock me-1"></i>Internal</span>
                            <?php elseif ($isVacation): ?>
                                <span class="badge bg-success text-white px-3 py-2 fs-6"><i class="bi bi-sun me-1"></i>Vacation</span>
                            <?php else: ?>
                                <span class="badge bg-primary px-3 py-2 fs-6"><i class="bi bi-calendar-range me-1"></i>External</span>
                            <?php endif; ?>
                        </div>

                        <hr class="my-3">

                        <div class="text-start">
                            <div class="d-flex justify-content-between mb-2">
                                <span class="text-muted small">House:</span>
                                <strong class="small"><?= e($houseName) ?></strong>
                            </div>
                            <div class="d-flex justify-content-between mb-2">
                                <span class="text-muted small">Room:</span>
                                <strong class="small"><?= e($roomNumber) ?></strong>
                            </div>
                            <div class="d-flex justify-content-between mb-2">
                                <span class="text-muted small">Guardian Phone:</span>
                                <strong class="small"><?= e($guardianPhone) ?></strong>
                            </div>
                            <div class="d-flex justify-content-between mb-0">
                                <span class="text-muted small">Requested By:</span>
                                <span class="badge bg-light text-dark border"><?= e(ucfirst((string)($exeat['createdByRole'] ?? 'User'))) ?></span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Staff Action Box -->
                <?php if ($isStaff): ?>
                    <div class="card stat-card shadow-sm border-0 p-3">
                        <h6 class="fw-bold mb-3"><i class="bi bi-lightning-charge me-1 text-warning"></i>Workflow Actions</h6>
                        <div class="d-grid gap-2">
                            <?php if ($status === 'pending'): ?>
                                <form method="POST" action="<?= url('views/exeat/index.php') ?>">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="recordId" value="<?= e($id) ?>">
                                    <input type="hidden" name="action" value="reject">
                                    <button type="submit" class="btn btn-danger w-100"><i class="bi bi-x-lg me-1"></i>Reject Request</button>
                                </form>
                            <?php elseif ($status === 'departed'): ?>
                                <form method="POST" action="<?= url('views/exeat/index.php') ?>">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="recordId" value="<?= e($id) ?>">
                                    <input type="hidden" name="action" value="return">
                                    <button type="submit" class="btn btn-secondary w-100"><i class="bi bi-box-arrow-in-left me-1"></i>Mark as Returned</button>
                                </form>
                            <?php else: ?>
                                <p class="text-muted small mb-0 text-center">No pending workflow actions for this record.</p>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Right Card: Schedule & Details -->
            <div class="col-lg-8">
                <div class="card stat-card shadow-sm border-0 mb-4">
                    <div class="card-header bg-white py-3">
                        <h6 class="mb-0 fw-bold"><i class="bi bi-calendar-event me-2 text-primary"></i>Schedule &amp; Exeat Parameters</h6>
                    </div>
                    <div class="card-body p-4">
                        <?php if ($isInternal): ?>
                            <div class="alert alert-info d-flex align-items-center mb-4">
                                <i class="bi bi-clock-history fs-3 me-3"></i>
                                <div>
                                    <strong class="d-block">Internal Exeat Pass</strong>
                                    <small>This pass is valid for on-campus/local town activities within designated departure and curfew closing hours.</small>
                                </div>
                            </div>

                            <div class="row g-3 mb-4">
                                <div class="col-md-4">
                                    <div class="p-3 bg-light rounded-3 text-center border">
                                        <span class="text-muted small d-block mb-1">Pass Date</span>
                                        <h5 class="fw-bold mb-0 text-dark"><?= e($exeat['startDate'] ?? $exeat['date'] ?? '—') ?></h5>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="p-3 bg-light rounded-3 text-center border">
                                        <span class="text-muted small d-block mb-1">Time to Start</span>
                                        <h5 class="fw-bold mb-0 text-success"><i class="bi bi-alarm me-1"></i><?= e($exeat['startTime'] ?? '—') ?></h5>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="p-3 bg-light rounded-3 text-center border">
                                        <span class="text-muted small d-block mb-1">Time to Close / Return</span>
                                        <h5 class="fw-bold mb-0 text-danger"><i class="bi bi-clock-fill me-1"></i><?= e($exeat['closeTime'] ?? $exeat['endTime'] ?? '—') ?></h5>
                                    </div>
                                </div>
                            </div>
                        <?php else: ?>
                            <div class="alert <?= $isVacation ? 'alert-success' : 'alert-primary' ?> d-flex align-items-center mb-4">
                                <i class="bi bi-calendar2-range fs-3 me-3"></i>
                                <div>
                                    <strong class="d-block"><?= e($typeLabel) ?><?= $isVacation ? '' : ' Exeat' ?> Pass</strong>
                                    <small><?= $isVacation ? 'This request covers a multi-day vacation away from the dormitory.' : 'This pass is valid for multi-day travel, weekend release, medical appointments, or holiday home trips.' ?></small>
                                </div>
                            </div>

                            <div class="row g-3 mb-4">
                                <div class="col-md-6">
                                    <div class="p-3 bg-light rounded-3 text-center border">
                                        <span class="text-muted small d-block mb-1"><?= $isVacation ? 'Vacation Start Date' : 'Departure Start Date' ?></span>
                                        <h5 class="fw-bold mb-0 text-primary"><i class="bi bi-calendar-check me-1"></i><?= e($exeat['startDate'] ?? '—') ?></h5>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="p-3 bg-light rounded-3 text-center border">
                                        <span class="text-muted small d-block mb-1"><?= $isVacation ? 'Vacation End Date' : 'Expected Return Date' ?></span>
                                        <h5 class="fw-bold mb-0 text-danger"><i class="bi bi-calendar-x me-1"></i><?= e($exeat['endDate'] ?? '—') ?></h5>
                                    </div>
                                </div>
                            </div>
                        <?php endif; ?>

                        <div class="mb-4">
                            <label class="text-muted small text-uppercase fw-semibold d-block mb-1">Destination Address / Location</label>
                            <div class="p-3 bg-light rounded-3 border">
                                <i class="bi bi-geo-alt-fill text-danger me-2"></i>
                                <strong><?= e($exeat['destination'] ?? 'No specific destination stated') ?></strong>
                            </div>
                        </div>

                        <div class="mb-4">
                            <label class="text-muted small text-uppercase fw-semibold d-block mb-1">Reason / Purpose</label>
                            <div class="p-3 bg-light rounded-3 border">
                                <p class="mb-0 text-dark"><?= nl2br(e($exeat['reason'] ?? '—')) ?></p>
                            </div>
                        </div>

                        <hr class="my-4">

                        <h6 class="fw-bold mb-3"><i class="bi bi-clock-history me-2 text-primary"></i>Audit Trail &amp; Verification Timestamps</h6>
                        <div class="row g-3 text-muted small">
                            <div class="col-sm-6 col-md-3">
                                <strong>Created At:</strong>
                                <div><?= !empty($exeat['createdAt']) ? date('M j, Y H:i', strtotime((string) $exeat['createdAt'])) : '—' ?></div>
                            </div>
                            <div class="col-sm-6 col-md-3">
                                <strong>Reviewed At:</strong>
                                <div><?= !empty($exeat['reviewedAt']) ? date('M j, Y H:i', strtotime((string) $exeat['reviewedAt'])) : '—' ?></div>
                            </div>
                            <div class="col-sm-6 col-md-3">
                                <strong>Departed At:</strong>
                                <div><?= !empty($exeat['departedAt']) ? date('M j, Y H:i', strtotime((string) $exeat['departedAt'])) : '—' ?></div>
                            </div>
                            <div class="col-sm-6 col-md-3">
                                <strong>Returned At:</strong>
                                <div><?= !empty($exeat['returnedAt']) ? date('M j, Y H:i', strtotime((string) $exeat['returnedAt'])) : '—' ?></div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
<div class="modal fade" id="detailEditExeatModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable modal-lg"><div class="modal-content">
        <form method="POST" action="<?= url('views/exeat/edit/edit.php?id=' . urlencode($id)) ?>">
            <?= csrf_field() ?>
            <input type="hidden" name="id" value="<?= e($id) ?>">
            <div class="modal-header"><h5 class="modal-title">Edit Exeat Pass</h5><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button></div>
            <div class="modal-body">
                <?php if ($isStaff): ?>
                    <div class="mb-3"><label class="form-label">Status</label><select name="status" class="form-select"><?php foreach (['pending', 'approved', 'rejected', 'departed', 'returned'] as $exeatStatus): ?><option value="<?= e($exeatStatus) ?>" <?= $status === $exeatStatus ? 'selected' : '' ?>><?= ucfirst($exeatStatus) ?></option><?php endforeach; ?></select></div>
                <?php endif; ?>
                <div class="mb-3"><label class="form-label">Pass Type</label><select name="exeatType" id="detailExeatType" class="form-select"><option value="internal" <?= $exeatType === 'internal' ? 'selected' : '' ?>>Internal</option><option value="external" <?= $exeatType === 'external' ? 'selected' : '' ?>>External</option><option value="vacation" <?= $exeatType === 'vacation' ? 'selected' : '' ?>>Vacation</option></select></div>
                <div id="detailInternalExeatFields" class="row g-3">
                    <div class="col-md-4"><label class="form-label">Date</label><input type="date" name="date" class="form-control" value="<?= e($exeat['startDate'] ?? $exeat['date'] ?? date('Y-m-d')) ?>"></div>
                    <div class="col-md-4"><label class="form-label">Start Time</label><input type="time" name="startTime" class="form-control" value="<?= e($exeat['startTime'] ?? '08:00') ?>"></div>
                    <div class="col-md-4"><label class="form-label">Close Time</label><input type="time" name="closeTime" class="form-control" value="<?= e($exeat['closeTime'] ?? $exeat['endTime'] ?? '17:00') ?>"></div>
                </div>
                <div id="detailExternalExeatFields" class="row g-3">
                    <div class="col-md-6"><label class="form-label">Start Date</label><input type="date" name="startDate" class="form-control" value="<?= e($exeat['startDate'] ?? date('Y-m-d')) ?>"></div>
                    <div class="col-md-6"><label class="form-label">End Date</label><input type="date" name="endDate" class="form-control" value="<?= e($exeat['endDate'] ?? date('Y-m-d', strtotime('+1 day'))) ?>"></div>
                </div>
                <div class="mt-3"><label class="form-label">Destination</label><input name="destination" class="form-control" value="<?= e($exeat['destination'] ?? '') ?>"></div>
                <div class="mt-3"><label class="form-label">Parent / Guardian Phone</label><input type="tel" name="guardianPhone" class="form-control" value="<?= e($guardianPhone) ?>"></div>
                <div class="mt-3"><label class="form-label">Reason</label><textarea name="reason" id="detailExeatReason" class="form-control" rows="4" required><?= $isVacation ? 'Vacation' : e($exeat['reason'] ?? '') ?></textarea><div class="form-text" id="detailExeatReasonHint"></div></div>
            </div>
            <div class="modal-footer"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button><button type="submit" class="btn btn-primary">Save changes</button></div>
        </form>
    </div></div>
</div>
<div class="modal fade" id="detailDeleteExeatModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered"><div class="modal-content">
        <form method="POST" action="<?= url('views/exeat/index.php') ?>">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="delete"><input type="hidden" name="recordId" value="<?= e($id) ?>">
            <div class="modal-header"><h5 class="modal-title text-danger">Delete Exeat Pass</h5><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button></div>
            <div class="modal-body">Delete the exeat pass for <strong><?= e($studentName) ?></strong>? This action cannot be undone.</div>
            <div class="modal-footer"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button><button type="submit" class="btn btn-danger">Delete pass</button></div>
        </form>
    </div></div>
</div>
<script>
document.addEventListener('DOMContentLoaded', function () {
    const typeSelect = document.getElementById('detailExeatType');
    const internalFields = document.getElementById('detailInternalExeatFields');
    const externalFields = document.getElementById('detailExternalExeatFields');
    const reason = document.getElementById('detailExeatReason');
    const reasonHint = document.getElementById('detailExeatReasonHint');
    if (!typeSelect || !internalFields || !externalFields || !reason || !reasonHint) return;

    function updateExeatType() {
        const isInternal = typeSelect.value === 'internal';
        const isVacation = typeSelect.value === 'vacation';
        internalFields.classList.toggle('d-none', !isInternal);
        externalFields.classList.toggle('d-none', isInternal);
        internalFields.querySelectorAll('input').forEach(function (input) {
            input.disabled = !isInternal;
            input.required = isInternal;
        });
        externalFields.querySelectorAll('input').forEach(function (input) {
            input.disabled = isInternal;
            input.required = !isInternal;
        });
        if (isVacation) {
            reason.dataset.previousReason = reason.value;
            reason.value = 'Vacation';
            reason.readOnly = true;
            reasonHint.textContent = 'Vacation reason is fixed.';
        } else {
            if (reason.readOnly) reason.value = reason.dataset.previousReason || '';
            reason.readOnly = false;
            reasonHint.textContent = 'Provide a clear explanation for the exeat request.';
        }
    }

    typeSelect.addEventListener('change', updateExeatType);
    updateExeatType();
});
</script>
<?php require APP_ROOT . '/app/views/components/footer/footer.php'; ?>
