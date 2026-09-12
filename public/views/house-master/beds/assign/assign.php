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
    $result = BedService::assign($id, sanitize($_POST['studentId'] ?? '')); 
    flash(!empty($result['success']) ? 'success' : 'error', $result['message'] ?? 'Unable to assign bed.'); 
    if (!empty($result['success'])) house_master_bed_redirect(); 
}

$pageTitle = 'Assign Bed'; 
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
                <h4 class="mb-1 fw-bold text-dark"><i class="bi bi-person-plus-fill text-success me-2"></i>Assign Bed <?= e($bed['bedNumber'] ?? '—') ?></h4>
                <p class="text-muted mb-0">Room: <strong><?= e($room['roomNumber'] ?? '—') ?></strong> &bull; House: <strong><?= e($house['name'] ?? '—') ?></strong></p>
            </div>
            <a class="btn btn-outline-secondary btn-sm" href="<?= url('views/house-master/beds/index/index.php') ?>">
                <i class="bi bi-arrow-left me-1"></i>Back to Beds
            </a>
        </div>

        <div class="card stat-card shadow-sm border-0" style="max-width: 680px;">
            <div class="card-header bg-white py-3">
                <h6 class="mb-0 fw-bold"><i class="bi bi-person-badge me-2 text-primary"></i>Bed Allocation</h6>
            </div>
            <div class="card-body p-4">
                <form method="POST" action="<?= url('views/house-master/beds/assign/assign.php?id=' . urlencode($id)) ?>">
                    <input type="hidden" name="id" value="<?= e($id) ?>">

                    <div class="mb-4">
                        <label class="form-label fw-semibold">Select Resident Student <span class="text-danger">*</span></label>
                        <div class="input-group">
                            <span class="input-group-text bg-white"><i class="bi bi-mortarboard"></i></span>
                            <select name="studentId" class="form-select" required>
                                <option value="">Choose a student...</option>
                                <?php foreach ($students as $student): ?>
                                    <option value="<?= e((string) ($student['id'] ?? '')) ?>">
                                        <?= e(trim(($student['firstName'] ?? '') . ' ' . ($student['lastName'] ?? ''))) ?> (<?= e($student['admissionNo'] ?? 'No ID') ?>)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-text">Only students in your house are listed above.</div>
                    </div>

                    <div class="pt-3 border-top d-flex justify-content-end gap-2">
                        <a class="btn btn-outline-secondary" href="<?= url('views/house-master/beds/index/index.php') ?>">Cancel</a>
                        <button type="submit" class="btn btn-success">
                            <i class="bi bi-check2-circle me-1"></i>Confirm Assignment
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
<?php require APP_ROOT . '/app/views/components/footer/footer.php'; ?>
