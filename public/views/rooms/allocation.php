<?php
require __DIR__ . '/../../bootstrap.php';
$allowedRoles = [ROLE_ADMIN, ROLE_HOUSE_MASTER];
require APP_ROOT . '/app/middleware/RoleMiddleware.php';

use App\Services\RoomService;
use App\Services\FirebaseService;

$pageTitle = 'Room Allocation';
$rooms = RoomService::all();
$students = FirebaseService::getInstance()->getCollection(COL_STUDENTS, [], 500);
$navItems = [
    ['icon' => 'bi-speedometer2', 'label' => 'Dashboard', 'href' => url('views/admin/dashboard.php')],
    ['icon' => 'bi-door-closed', 'label' => 'Room Allocation', 'href' => url('views/rooms/allocation.php'), 'active' => true],
];

require APP_ROOT . '/app/views/components/header.php';
require APP_ROOT . '/app/views/components/sidebar.php';
?>
<div class="main-content">
    <?php require APP_ROOT . '/app/views/components/navbar.php'; ?>
    <div class="content-wrapper">
        <div class="card stat-card p-4" style="max-width:760px;">
            <h5 class="mb-3">Allocate Student to Room</h5>
            <form method="POST" action="<?= url('views/rooms/allocation.php') ?>">
                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label">Student</label>
                        <select name="studentId" class="form-select" required>
                            <option value="">Select student</option>
                            <?php foreach ($students as $student): ?>
                                <option value="<?= e($student['id'] ?? '') ?>"><?= e(trim(($student['firstName'] ?? '') . ' ' . ($student['lastName'] ?? ''))) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Room</label>
                        <select name="roomId" class="form-select" required>
                            <option value="">Select room</option>
                            <?php foreach ($rooms as $room): ?>
                                <option value="<?= e($room['id'] ?? '') ?>"><?= e($room['roomNumber'] ?? '') ?> (<?= e($room['capacity'] ?? 0) ?> capacity)</option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <div class="mt-4">
                    <button class="btn btn-primary" type="submit">Allocate</button>
                    <a href="<?= url('views/rooms/index.php') ?>" class="btn btn-outline-secondary">Cancel</a>
                </div>
            </form>
        </div>
    </div>
</div>
<?php require APP_ROOT . '/app/views/components/footer.php'; ?>
