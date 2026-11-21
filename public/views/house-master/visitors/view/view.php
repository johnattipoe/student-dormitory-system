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
$allowedRoles = [ROLE_HOUSE_MASTER, ROLE_HOUSE_MISTRESS];
require APP_ROOT . '/app/middleware/RoleMiddleware/RoleMiddleware.php';

use App\Services\VisitorService;
use App\Services\StudentService;

$id = sanitize($_GET['id'] ?? '');
$houseId = current_user()['houseId'] ?? null;
$visitor = null;
foreach ((new VisitorService())->byHouse($houseId) as $record) {
    if (($record['id'] ?? '') === $id) {
        $visitor = $record;
        break;
    }
}

if (!$visitor) {
    flash('error', 'Visitor not found.');
    redirect(url('views/house-master/visitors/index/index.php'));
}

$student = StudentService::find((string) ($visitor['studentId'] ?? ''));
$studentName = $student ? trim(($student['firstName'] ?? '') . ' ' . ($student['lastName'] ?? '')) : ($visitor['studentId'] ?? '—');
if ($student && !empty($student['admissionNo'])) {
    $studentName .= ' [' . $student['admissionNo'] . ']';
}

$status = strtolower((string) ($visitor['status'] ?? 'registered'));
$statusBadge = match($status) {
    'inside' => 'bg-success text-white',
    'checked_out', 'left' => 'bg-secondary text-white',
    default => 'bg-primary text-white'
};

$pageTitle = 'Visitor Details';
$navItems = [
    ['icon' => 'bi-speedometer2', 'label' => 'Dashboard', 'href' => url('views/house-master/dashboard/index.php')],
    ['icon' => 'bi-people', 'label' => 'Visitors', 'href' => url('views/house-master/visitors/index/index.php'), 'active' => true],
];

require APP_ROOT . '/app/views/components/header/header.php';
require APP_ROOT . '/app/views/components/sidebar/sidebar.php';
?>
<div class="main-content">
    <?php require APP_ROOT . '/app/views/components/navbar/navbar.php'; ?>
    <div class="content-wrapper">
        <?php require APP_ROOT . '/app/views/components/alerts/alerts.php'; ?>

        <!-- Hero Header -->
        <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-3">
            <div>
                <h4 class="mb-1 fw-bold text-dark"><i class="bi bi-person-badge text-primary me-2"></i>Visitor Record Details</h4>
                <p class="text-muted mb-0">Review visitor check-in information and visit purpose</p>
            </div>
            <div class="d-flex gap-2 flex-wrap">
                <button type="button" class="btn btn-warning btn-sm" data-bs-toggle="modal" data-bs-target="#houseVisitorEditModal">
                    <i class="bi bi-pencil me-1"></i>Edit
                </button>
                <button type="button" class="btn btn-outline-danger btn-sm" data-bs-toggle="modal" data-bs-target="#houseVisitorDeleteModal">
                    <i class="bi bi-trash me-1"></i>Delete
                </button>
                <a class="btn btn-outline-secondary btn-sm" href="<?= url('views/house-master/visitors/index/index.php') ?>">
                    <i class="bi bi-arrow-left me-1"></i>Back
                </a>
            </div>
        </div>

        <!-- Detail Card -->
        <div class="card stat-card shadow-sm border-0 mb-4" style="max-width: 760px;">
            <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
                <h6 class="mb-0 fw-bold"><i class="bi bi-people me-2 text-primary"></i><?= e($visitor['visitorName'] ?? 'Visitor') ?></h6>
                <span class="badge <?= $statusBadge ?>"><?= e(ucwords(str_replace('_', ' ', $status))) ?></span>
            </div>
            <div class="card-body p-4">
                <div class="row g-3 mb-4">
                    <div class="col-sm-6">
                        <span class="text-muted small d-block">Visitor Full Name</span>
                        <strong class="fs-6"><?= e($visitor['visitorName'] ?? '—') ?></strong>
                    </div>
                    <div class="col-sm-6">
                        <span class="text-muted small d-block">Phone / Contact</span>
                        <strong><?= e($visitor['phone'] ?? '—') ?></strong>
                    </div>
                    <div class="col-sm-6">
                        <span class="text-muted small d-block">Student Being Visited</span>
                        <strong><?= e($studentName) ?></strong>
                    </div>
                    <div class="col-sm-6">
                        <span class="text-muted small d-block">Visit Status</span>
                        <span class="badge <?= $statusBadge ?>"><?= e(ucwords(str_replace('_', ' ', $status))) ?></span>
                    </div>
                    <?php if (!empty($visitor['checkInTime'])): ?>
                    <div class="col-sm-6">
                        <span class="text-muted small d-block">Check-In Time</span>
                        <strong><?= e($visitor['checkInTime']) ?></strong>
                    </div>
                    <?php endif; ?>
                    <?php if (!empty($visitor['checkOutTime'])): ?>
                    <div class="col-sm-6">
                        <span class="text-muted small d-block">Check-Out Time</span>
                        <strong><?= e($visitor['checkOutTime']) ?></strong>
                    </div>
                    <?php endif; ?>
                </div>

                <?php if (!empty($visitor['purpose'])): ?>
                <div class="pt-3 border-top">
                    <span class="text-muted small d-block mb-1 fw-semibold">Purpose of Visit</span>
                    <div class="p-3 bg-light rounded small" style="white-space: pre-line;">
                        <?= e($visitor['purpose']) ?>
                    </div>
                </div>
                <?php endif; ?>

                <div class="mt-4 pt-3 border-top d-flex justify-content-end gap-2">
                    <a class="btn btn-outline-secondary btn-sm" href="<?= url('views/house-master/visitors/index/index.php') ?>">
                        Back to Visitors
                    </a>
                    <button type="button" class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#houseVisitorEditModal">
                        <i class="bi bi-pencil me-1"></i>Edit Visitor
                    </button>
                </div>
            </div>
        </div>
        <div class="modal fade" id="houseVisitorEditModal" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered modal-lg">
                <div class="modal-content">
                    <form method="POST" action="<?= url('views/house-master/visitors/edit/edit.php?id=' . urlencode($id)) ?>">
                        <div class="modal-header"><h5 class="modal-title">Edit Visitor</h5><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button></div>
                        <div class="modal-body">
                            <input type="hidden" name="id" value="<?= e($id) ?>">
                            <label class="form-label">Visitor Name</label><input name="visitorName" class="form-control mb-3" value="<?= e($visitor['visitorName'] ?? '') ?>" required>
                            <label class="form-label">Phone</label><input name="phone" class="form-control mb-3" value="<?= e($visitor['phone'] ?? '') ?>">
                            <label class="form-label">Status</label>
                            <select name="status" class="form-select mb-3">
                                <option value="registered" <?= $status === 'registered' ? 'selected' : '' ?>>Registered</option>
                                <option value="inside" <?= $status === 'inside' ? 'selected' : '' ?>>Inside</option>
                                <option value="checked_out" <?= in_array($status, ['checked_out', 'left'], true) ? 'selected' : '' ?>>Checked Out</option>
                            </select>
                            <label class="form-label">Purpose</label><textarea name="purpose" class="form-control" rows="3"><?= e($visitor['purpose'] ?? '') ?></textarea>
                        </div>
                        <div class="modal-footer"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button><button type="submit" class="btn btn-primary">Save changes</button></div>
                    </form>
                </div>
            </div>
        </div>
        <div class="modal fade" id="houseVisitorDeleteModal" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content">
                    <div class="modal-header border-0"><h5 class="modal-title text-danger">Delete Visitor</h5><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button></div>
                    <div class="modal-body">Delete visitor <strong><?= e($visitor['visitorName'] ?? 'this visitor') ?></strong>?</div>
                    <div class="modal-footer border-0">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <form method="POST" action="<?= url('views/house-master/visitors/delete/delete.php?id=' . urlencode($id)) ?>">
                            <input type="hidden" name="id" value="<?= e($id) ?>"><button type="submit" class="btn btn-danger">Delete</button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
<?php require APP_ROOT . '/app/views/components/footer/footer.php'; ?>