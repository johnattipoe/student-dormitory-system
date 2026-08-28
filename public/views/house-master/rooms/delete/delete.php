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

use App\Services\RoomService;

$houseId = current_user()['houseId'] ?? null;
$id = sanitize($_GET['id'] ?? $_POST['id'] ?? '');
$room = $id ? RoomService::find($id) : null;

if (!$room || ($room['houseId'] ?? null) !== $houseId) {
    flash('error', 'Room not found in your assigned house.');
    redirect(url('views/house-master/rooms/index/index.php'));
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    RoomService::delete($id);
    flash('success', 'Room deleted successfully.');
    redirect(url('views/house-master/rooms/index/index.php'));
}

$occupied = (int) ($room['occupied'] ?? $room['occupancy'] ?? 0);
$pageTitle = 'Delete Room';
$navItems = [
    ['icon' => 'bi-speedometer2', 'label' => 'Dashboard', 'href' => url('views/house-master/dashboard/index.php')],
    ['icon' => 'bi-door-closed', 'label' => 'Rooms', 'href' => url('views/house-master/rooms/index/index.php'), 'active' => true],
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
                <h4 class="mb-1 fw-bold text-dark"><i class="bi bi-trash text-danger me-2"></i>Delete Room</h4>
                <p class="text-muted mb-0">Confirm removal of dormitory room</p>
            </div>
            <div class="d-flex gap-2">
                <a class="btn btn-outline-secondary btn-sm" href="<?= url('views/house-master/rooms/index/index.php') ?>">
                    <i class="bi bi-arrow-left me-1"></i>Back
                </a>
            </div>
        </div>

        <div class="card stat-card shadow-sm border-0 border-top border-4 border-danger mx-auto" style="max-width: 540px;">
            <div class="card-body p-4 text-center">
                <div class="rounded-circle bg-danger bg-opacity-10 text-danger d-inline-flex align-items-center justify-content-center mb-3" style="width: 72px; height: 72px;">
                    <i class="bi bi-door-open fs-2"></i>
                </div>
                <h5 class="fw-bold text-dark mb-2">Delete Room <?= e($room['roomNumber'] ?? '') ?>?</h5>
                <p class="text-muted mb-3">This action cannot be undone. The room will be permanently removed from the dormitory registry.</p>

                <?php if ($occupied > 0): ?>
                    <div class="alert alert-warning small text-start mb-4">
                        <i class="bi bi-exclamation-triangle-fill me-1"></i>
                        This room currently has <strong><?= e((string) $occupied) ?> occupant(s)</strong>. Confirming will automatically unassign all students from this room.
                    </div>
                <?php else: ?>
                    <div class="alert alert-danger small text-start mb-4">
                        <i class="bi bi-info-circle me-1"></i>All bed allocations and assignments for this room will be permanently removed.
                    </div>
                <?php endif; ?>

                <form method="POST" action="<?= url('views/house-master/rooms/delete/delete.php?id=' . urlencode($id)) ?>" class="d-flex justify-content-center gap-2">
                    <input type="hidden" name="id" value="<?= e($id) ?>">
                    <a class="btn btn-outline-secondary" href="<?= url('views/house-master/rooms/index/index.php') ?>">Cancel</a>
                    <button type="submit" class="btn btn-danger">
                        <i class="bi bi-trash me-1"></i>Confirm Delete
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>
<?php require APP_ROOT . '/app/views/components/footer/footer.php'; ?>