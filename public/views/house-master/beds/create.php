<?php
require __DIR__ . '/_context.php';

use App\Services\BedService;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $roomId = sanitize($_POST['roomId'] ?? '');
    if (!isset($roomMap[$roomId])) {
        flash('error', 'Select a room in your assigned house.');
    } else {
        $result = BedService::create(['bedNumber' => sanitize($_POST['bedNumber'] ?? ''), 'roomId' => $roomId, 'capacity' => max(1, (int) ($_POST['capacity'] ?? 1)), 'status' => sanitize($_POST['status'] ?? 'available')]);
        flash(!empty($result['success']) ? 'success' : 'error', $result['message'] ?? 'Unable to create bed.');
        if (!empty($result['success'])) house_master_bed_redirect();
    }
}
$pageTitle = 'Add Bed';
$navItems = [['icon'=>'bi-grid-3x3-gap','label'=>'Beds','href'=>url('views/house-master/beds/index.php'),'active'=>true]];

require APP_ROOT . '/app/views/components/header.php';
require APP_ROOT . '/app/views/components/sidebar.php';
?>
<div class="main-content">
    <?php require APP_ROOT . '/app/views/components/navbar.php'; ?>
    <?php require APP_ROOT . '/app/views/components/alerts.php'; ?>
    <div class="content-wrapper">
        <div class="card stat-card p-4" style="max-width:650px">
            <h5 class="mb-3">Add Bed</h5>
            <form method="POST">
                <label class="form-label">Bed number</label>
                <input name="bedNumber" class="form-control mb-3" placeholder="e.g. Bed 1" required>
                <label class="form-label">Bed capacity</label>
                <input type="number" name="capacity" class="form-control mb-3" min="1" value="1" required>
                <label class="form-label">Room</label>
                <select name="roomId" class="form-select mb-3" required>
                    <option value="">Select room</option>
                    <?php foreach ($rooms as $room): ?>
                        <option value="<?= e((string)($room['id']??'')) ?>"><?= e($room['roomNumber']??'-') ?></option>
                    <?php endforeach; ?>
                </select>
                <label class="form-label">Status</label>
                <select name="status" class="form-select">
                    <option value="available">Available</option>
                    <option value="maintenance">Maintenance</option>
                </select>
                <div class="mt-4">
                    <button class="btn btn-primary">Create bed</button>
                    <a class="btn btn-outline-secondary" href="<?= url('views/house-master/beds/index.php') ?>">Cancel</a>
                </div>
            </form>
        </div>
    </div>
</div>

<?php require APP_ROOT . '/app/views/components/footer.php'; ?>
