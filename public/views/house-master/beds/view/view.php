<?php
require __DIR__ . '/../_context/_context.php';

use App\Services\BedService;

$id = sanitize($_GET['id'] ?? '');
$bed = BedService::find($id);
$room = $bed ? ($roomMap[(string) ($bed['roomId'] ?? '')] ?? null) : null;
if (!$bed || !house_master_bed_allowed($room, $houseId)) {
    flash('error', 'Bed not found in your assigned house.');
    house_master_bed_redirect();
}
$student = $studentMap[(string) ($bed['studentId'] ?? '')] ?? null;
$status = $bed['status'] ?? 'available';
$statusBadge = match($status) {
    'occupied' => 'bg-warning text-dark',
    'maintenance' => 'bg-secondary text-white',
    default => 'bg-success text-white'
};

$pageTitle = 'Bed Details';
$navItems = [
    ['icon' => 'bi-speedometer2', 'label' => 'Dashboard', 'href' => url('views/house-master/dashboard/index.php')],
    ['icon' => 'bi-grid-3x3-gap', 'label' => 'Beds', 'href' => url('views/house-master/beds/index/index.php'), 'active' => true],
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
                <h4 class="mb-1 fw-bold text-dark"><i class="bi bi-grid-3x3-gap text-primary me-2"></i>Bed <?= e($bed['bedNumber'] ?? '—') ?></h4>
                <p class="text-muted mb-0">Room <?= e($room['roomNumber'] ?? '—') ?> &bull; <?= e($house['name'] ?? '') ?></p>
            </div>
            <div class="d-flex gap-2 flex-wrap">
                <a class="btn btn-warning btn-sm" href="<?= url('views/house-master/beds/edit/edit.php?id=' . urlencode($id)) ?>">
                    <i class="bi bi-pencil me-1"></i>Edit
                </a>
                <?php if (!$student && $status !== 'maintenance'): ?>
                    <a class="btn btn-outline-success btn-sm" href="<?= url('views/house-master/beds/assign/assign.php?id=' . urlencode($id)) ?>">
                        <i class="bi bi-person-plus me-1"></i>Assign Student
                    </a>
                <?php elseif ($student): ?>
                    <form method="POST" action="<?= url('views/house-master/beds/unassign/unassign.php') ?>" class="d-inline">
                        <input type="hidden" name="id" value="<?= e($id) ?>">
                        <button class="btn btn-outline-warning btn-sm"><i class="bi bi-person-dash me-1"></i>Unassign</button>
                    </form>
                <?php endif; ?>
                <a class="btn btn-outline-secondary btn-sm" href="<?= url('views/house-master/beds/index/index.php') ?>">
                    <i class="bi bi-arrow-left me-1"></i>Back
                </a>
            </div>
        </div>

        <div class="card stat-card shadow-sm border-0 mb-4" style="max-width: 720px;">
            <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
                <h6 class="mb-0 fw-bold"><i class="bi bi-info-circle me-2 text-primary"></i>Bed Information</h6>
                <span class="badge <?= $statusBadge ?>"><?= ucfirst($status) ?></span>
            </div>
            <div class="card-body p-4">
                <div class="row g-3">
                    <div class="col-sm-6">
                        <span class="text-muted small d-block">Bed Number</span>
                        <strong class="fs-5"><?= e($bed['bedNumber'] ?? '—') ?></strong>
                    </div>
                    <div class="col-sm-6">
                        <span class="text-muted small d-block">Capacity</span>
                        <strong><?= e((string) ($bed['capacity'] ?? 1)) ?> person(s)</strong>
                    </div>
                    <div class="col-sm-6">
                        <span class="text-muted small d-block">Assigned Room</span>
                        <strong>Room <?= e($room['roomNumber'] ?? '—') ?></strong>
                    </div>
                    <div class="col-sm-6">
                        <span class="text-muted small d-block">House</span>
                        <strong><?= e($house['name'] ?? '—') ?></strong>
                    </div>
                    <div class="col-12 pt-3 border-top">
                        <span class="text-muted small d-block mb-1">Assigned Student</span>
                        <?php if ($student): ?>
                            <div class="p-3 bg-light rounded d-flex justify-content-between align-items-center">
                                <div>
                                    <strong class="text-dark d-block"><?= e(trim(($student['firstName'] ?? '') . ' ' . ($student['lastName'] ?? ''))) ?></strong>
                                    <small class="text-muted font-monospace"><?= e($student['admissionNo'] ?? 'No ID') ?></small>
                                </div>
                                <a class="btn btn-outline-primary btn-sm" href="<?= url('views/house-master/students/profile/profile.php?studentId=' . urlencode((string) ($student['id'] ?? ''))) ?>">
                                    <i class="bi bi-person me-1"></i>Profile
                                </a>
                            </div>
                        <?php else: ?>
                            <div class="text-muted p-3 bg-light rounded text-center">
                                <i class="bi bi-person-slash me-1"></i>No student currently assigned to this bed.
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
<?php require APP_ROOT . '/app/views/components/footer/footer.php'; ?>
