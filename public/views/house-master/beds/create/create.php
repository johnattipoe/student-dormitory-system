<?php
require __DIR__ . '/../_context/_context.php';

use App\Services\BedService;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $roomId = sanitize($_POST['roomId'] ?? '');
    if (!isset($roomMap[$roomId])) {
        flash('error', 'Select a room in your assigned house.');
    } else {
        $result = BedService::create([
            'bedNumber' => sanitize($_POST['bedNumber'] ?? ''),
            'roomId' => $roomId,
            'capacity' => max(1, (int) ($_POST['capacity'] ?? 1)),
            'status' => sanitize($_POST['status'] ?? 'available'),
        ]);
        flash(!empty($result['success']) ? 'success' : 'error', $result['message'] ?? 'Unable to create bed.');
        if (!empty($result['success'])) house_master_bed_redirect();
    }
}

$pageTitle = 'Add Bed';
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
                <h4 class="mb-1 fw-bold text-dark"><i class="bi bi-plus-square-fill text-success me-2"></i>Add New Bed</h4>
                <p class="text-muted mb-0">Register a new bed slot to a dormitory room</p>
            </div>
            <a class="btn btn-outline-secondary btn-sm" href="<?= url('views/house-master/beds/index/index.php') ?>">
                <i class="bi bi-arrow-left me-1"></i>Back to Beds
            </a>
        </div>

        <div class="card stat-card shadow-sm border-0" style="max-width: 680px;">
            <div class="card-header bg-white py-3">
                <h6 class="mb-0 fw-bold"><i class="bi bi-grid-3x3-gap me-2 text-primary"></i>Bed Details</h6>
            </div>
            <div class="card-body p-4">
                <form method="POST" action="<?= url('views/house-master/beds/create/create.php') ?>">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Bed Number <span class="text-danger">*</span></label>
                            <div class="input-group">
                                <span class="input-group-text bg-white"><i class="bi bi-hash"></i></span>
                                <input name="bedNumber" class="form-control" placeholder="e.g. Bed A1 or Bunk 2" required>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Bed Capacity</label>
                            <div class="input-group">
                                <span class="input-group-text bg-white"><i class="bi bi-person"></i></span>
                                <input type="number" name="capacity" class="form-control" min="1" value="1" required>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Assigned Room <span class="text-danger">*</span></label>
                            <div class="input-group">
                                <span class="input-group-text bg-white"><i class="bi bi-door-closed"></i></span>
                                <select name="roomId" class="form-select" required>
                                    <option value="">Select room</option>
                                    <?php foreach ($rooms as $room): ?>
                                        <option value="<?= e((string) ($room['id'] ?? '')) ?>"><?= e($room['roomNumber'] ?? '—') ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Initial Status</label>
                            <div class="input-group">
                                <span class="input-group-text bg-white"><i class="bi bi-check2-circle"></i></span>
                                <select name="status" class="form-select">
                                    <option value="available">Available</option>
                                    <option value="maintenance">Under Maintenance</option>
                                </select>
                            </div>
                        </div>
                    </div>

                    <div class="mt-4 pt-3 border-top d-flex justify-content-end gap-2">
                        <a class="btn btn-outline-secondary" href="<?= url('views/house-master/beds/index/index.php') ?>">Cancel</a>
                        <button type="submit" class="btn btn-success">
                            <i class="bi bi-plus-circle me-1"></i>Create Bed
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
<?php require APP_ROOT . '/app/views/components/footer/footer.php'; ?>
