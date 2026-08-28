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
$allowedRoles = [ROLE_ADMIN];
require APP_ROOT . '/app/middleware/RoleMiddleware/RoleMiddleware.php';

use App\Services\RoomService;

$id = sanitize($_GET['id'] ?? $_POST['id'] ?? '');
$room = $id ? RoomService::find($id) : null;

if (!$room) {
    flash('error', 'Room not found.');
    redirect(url('views/admin/rooms/index/index.php'));
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    RoomService::delete($id);
    flash('success', 'Room deleted successfully.');
    redirect(url('views/admin/rooms/index/index.php?deleted=1'));
}

$occupied = (int) ($room['occupied'] ?? $room['occupancy'] ?? 0);
$pageTitle = 'Delete Room';
$navItems = [
    ['icon' => 'bi-speedometer2', 'label' => 'Dashboard', 'href' => url('views/admin/dashboard.php')],
    ['icon' => 'bi-door-closed', 'label' => 'Rooms', 'href' => url('views/admin/rooms/index/index.php'), 'active' => true],
];

require APP_ROOT . '/app/views/components/header/header.php';
require APP_ROOT . '/app/views/components/sidebar/sidebar.php';
?>
<div class="main-content">
    <?php require APP_ROOT . '/app/views/components/navbar/navbar.php'; ?>
    <?php require APP_ROOT . '/app/views/components/alerts/alerts.php'; ?>
    <div class="content-wrapper">

        <!-- Page Hero -->
        <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-3">
            <div>
                <h4 class="mb-1 fw-bold text-danger">
                    <i class="bi bi-door-closed-fill me-2"></i>Delete Room Record
                </h4>
                <p class="text-muted mb-0">Permanently remove room block from the dormitory database</p>
            </div>
            <div>
                <a href="<?= url('views/admin/rooms/index/index.php') ?>" class="btn btn-outline-secondary btn-sm">
                    <i class="bi bi-arrow-left me-1"></i> Back to Rooms
                </a>
            </div>
        </div>

        <div class="card stat-card shadow-sm border-0 border-top border-4 border-danger" style="max-width: 600px;">
            <div class="card-body p-4 text-center">
                <div class="rounded-circle bg-danger bg-opacity-10 text-danger d-inline-flex align-items-center justify-content-center mb-3" style="width: 64px; height: 64px;">
                    <i class="bi bi-trash3-fill fs-2"></i>
                </div>
                <h5 class="fw-bold text-dark mb-2">Delete Room <?= e($room['roomNumber'] ?? '') ?>?</h5>
                <p class="text-muted mb-3">
                    Total Capacity: <strong><?= e((string)($room['capacity'] ?? 0)) ?> Beds</strong>
                </p>

                <?php if ($occupied > 0): ?>
                    <div class="alert alert-danger text-start py-2 small mb-4">
                        <i class="bi bi-exclamation-triangle-fill me-2"></i><strong>Warning:</strong> This room currently has <strong><?= e((string) $occupied) ?> resident occupant(s)</strong> assigned. Deleting this room will unassign them from their room and bed spaces.
                    </div>
                <?php else: ?>
                    <div class="alert alert-warning text-start py-2 small mb-4">
                        <i class="bi bi-exclamation-triangle-fill me-2"></i>This action cannot be undone. Are you sure you want to permanently delete this room block?
                    </div>
                <?php endif; ?>

                <form method="POST" action="<?= url('views/admin/rooms/delete/delete.php?id=' . urlencode($id)) ?>" class="d-flex justify-content-center gap-2">
                    <input type="hidden" name="id" value="<?= e($id) ?>">
                    <button type="submit" class="btn btn-danger">
                        <i class="bi bi-trash me-1"></i> Permanently Delete
                    </button>
                    <a class="btn btn-outline-secondary" href="<?= url('views/admin/rooms/index/index.php') ?>">Cancel</a>
                </form>
            </div>
        </div>

    </div>
</div>
<?php require APP_ROOT . '/app/views/components/footer/footer.php'; ?>