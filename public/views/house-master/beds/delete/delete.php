<?php
require __DIR__ . '/../_context/_context.php';

use App\Services\BedService;

$id = sanitize($_GET['id'] ?? $_POST['id'] ?? ''); 
$bed = BedService::find($id); 
$room = $bed ? ($roomMap[(string)($bed['roomId']??'')] ?? null) : null;

if (!$bed || !house_master_bed_allowed($room, $houseId)) { 
    flash('error', 'Bed not found in your assigned house.'); 
    house_master_bed_redirect(); 
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') { 
    $result = BedService::delete($id); 
    flash(!empty($result['success']) ? 'success' : 'error', $result['message'] ?? 'Unable to delete bed.'); 
    house_master_bed_redirect(); 
}

$student = $studentMap[(string)($bed['studentId']??'')] ?? null;

$pageTitle = 'Delete Bed'; 
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
                <h4 class="mb-1 fw-bold text-dark"><i class="bi bi-trash text-danger me-2"></i>Delete Bed</h4>
                <p class="text-muted mb-0">Confirm removal of bed slot from room registry</p>
            </div>
            <a class="btn btn-outline-secondary btn-sm" href="<?= url('views/house-master/beds/index/index.php') ?>">
                <i class="bi bi-arrow-left me-1"></i>Back
            </a>
        </div>

        <div class="card stat-card shadow-sm border-0 border-top border-4 border-danger mx-auto" style="max-width: 540px;">
            <div class="card-body p-4 text-center">
                <div class="rounded-circle bg-danger bg-opacity-10 text-danger d-inline-flex align-items-center justify-content-center mb-3" style="width: 72px; height: 72px;">
                    <i class="bi bi-grid-3x3-gap fs-2"></i>
                </div>
                <h5 class="fw-bold text-dark mb-2">Delete Bed <?= e($bed['bedNumber'] ?? '—') ?>?</h5>
                <p class="text-muted mb-3">Room: <strong><?= e($room['roomNumber'] ?? '—') ?></strong> &bull; House: <strong><?= e($house['name'] ?? '—') ?></strong></p>

                <?php if ($student): ?>
                    <div class="alert alert-warning small text-start mb-4">
                        <i class="bi bi-exclamation-triangle-fill me-1"></i>
                        This bed is currently assigned to <strong><?= e(trim(($student['firstName'] ?? '') . ' ' . ($student['lastName'] ?? ''))) ?></strong>. Confirming deletion will automatically unassign the student.
                    </div>
                <?php else: ?>
                    <div class="alert alert-danger small text-start mb-4">
                        <i class="bi bi-info-circle me-1"></i>This action cannot be undone. The bed slot will be permanently removed.
                    </div>
                <?php endif; ?>

                <form method="POST" action="<?= url('views/house-master/beds/delete/delete.php?id=' . urlencode($id)) ?>" class="d-flex justify-content-center gap-2">
                    <input type="hidden" name="id" value="<?= e($id) ?>">
                    <a class="btn btn-outline-secondary" href="<?= url('views/house-master/beds/index/index.php') ?>">Cancel</a>
                    <button type="submit" class="btn btn-danger">
                        <i class="bi bi-trash me-1"></i>Confirm Delete
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>
<?php require APP_ROOT . '/app/views/components/footer/footer.php'; ?>
