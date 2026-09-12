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
use App\Services\FirebaseService;

$pageTitle = 'Edit Room';
$id = $_GET['id'] ?? null;
$room = $id ? RoomService::find($id) : null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $postId = sanitize($_POST['id'] ?? '');
    $data = [
        'roomNumber' => sanitize($_POST['roomNumber'] ?? ''),
        'houseId' => sanitize($_POST['houseId'] ?? ''),
        'capacity' => (int) ($_POST['capacity'] ?? 1),
        'type' => sanitize($_POST['type'] ?? 'standard'),
        'status' => sanitize($_POST['status'] ?? 'available'),
    ];

    $errors = validate_required($data, ['roomNumber']);

    if (!$postId) {
        flash('error', 'Room ID is required.');
        redirect(base_url('index.php?route=/views/admin/rooms/index/index.php'));
    }

    if (!empty($errors)) {
        $_SESSION['_errors'] = $errors;
        $_SESSION['_old'] = $data;
        flash('error', 'Please fix the highlighted fields.');
        redirect(base_url('index.php?route=/views/admin/rooms/edit/edit.php?id=' . urlencode($id)));
    }

    try {
        RoomService::update($postId, $data);
        flash('success', 'Room updated successfully.');
    } catch (\Throwable $e) {
        flash('error', 'Unable to update room: ' . $e->getMessage());
    }

    redirect(base_url('index.php?route=/views/admin/rooms/index/index.php'));
}

$errors = $_SESSION['_errors'] ?? [];
unset($_SESSION['_errors']);
$old = $_SESSION['_old'] ?? [];
unset($_SESSION['_old']);

$houses = FirebaseService::getInstance()->getCollection(COL_HOUSES, [], 200);
$navItems = [
    ['icon' => 'bi-speedometer2', 'label' => 'Dashboard', 'href' => url('views/admin/dashboard.php')],
    ['icon' => 'bi-door-closed', 'label' => 'Rooms', 'href' => url('views/admin/rooms/index/index.php')],
    ['icon' => 'bi-pencil', 'label' => 'Edit Room', 'href' => url('views/admin/rooms/edit/edit.php?id=' . urlencode((string)$id)), 'active' => true],
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
                <h4 class="mb-1 fw-bold text-dark">
                    <i class="bi bi-pencil-square text-warning me-2"></i>Edit Room <?= e($room['roomNumber'] ?? '') ?>
                </h4>
                <p class="text-muted mb-0">Update room specifications, house assignment, and bed space parameters</p>
            </div>
            <div class="d-flex gap-2">
                <?php if ($room): ?>
                    <a href="<?= url('views/admin/rooms/view/view.php?id=' . urlencode((string)$id)) ?>" class="btn btn-outline-primary btn-sm">
                        <i class="bi bi-eye me-1"></i> View Room
                    </a>
                <?php endif; ?>
                <a href="<?= url('views/admin/rooms/index/index.php') ?>" class="btn btn-outline-secondary btn-sm">
                    <i class="bi bi-arrow-left me-1"></i> Back to Rooms
                </a>
            </div>
        </div>

        <div class="card stat-card shadow-sm border-0" style="max-width: 760px;">
            <div class="card-header bg-white py-3">
                <h6 class="mb-0 fw-bold"><i class="bi bi-pencil-square me-2 text-warning"></i>Room Modification Form</h6>
            </div>
            <div class="card-body p-4">
                <?php if (!$room): ?>
                    <div class="alert alert-warning"><i class="bi bi-exclamation-triangle me-2"></i>Room not found.</div>
                <?php else: ?>
                    <form method="POST" action="<?= url('views/admin/rooms/edit/edit.php?id=' . urlencode((string)$id)) ?>">
                        <input type="hidden" name="id" value="<?= e($room['id'] ?? '') ?>">
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label fw-semibold">Room Number <span class="text-danger">*</span></label>
                                <input name="roomNumber" class="form-control" value="<?= e($old['roomNumber'] ?? $room['roomNumber'] ?? '') ?>" required>
                                <?php if (!empty($errors['roomNumber'])): ?><div class="text-danger small mt-1"><?= e($errors['roomNumber']) ?></div><?php endif; ?>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label fw-semibold">Dormitory House <span class="text-danger">*</span></label>
                                <select name="houseId" class="form-select select2" required>
                                    <?php foreach ($houses as $house): ?>
                                        <option value="<?= e((string) ($house['id'] ?? '')) ?>" <?= (($old['houseId'] ?? $room['houseId'] ?? '') === ((string) ($house['id'] ?? ''))) ? 'selected' : '' ?>>
                                            <?= e($house['name'] ?? '') ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label fw-semibold">Bed Capacity</label>
                                <input type="number" name="capacity" class="form-control" min="1" value="<?= e($old['capacity'] ?? $room['capacity'] ?? 1) ?>" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label fw-semibold">Room Type</label>
                                <select name="type" class="form-select">
                                    <?php $currentType = $old['type'] ?? $room['type'] ?? 'standard'; ?>
                                    <option value="standard" <?= $currentType === 'standard' ? 'selected' : '' ?>>Standard Dorm</option>
                                    <option value="prefect" <?= $currentType === 'prefect' ? 'selected' : '' ?>>Prefect Room</option>
                                    <option value="sickbay" <?= $currentType === 'sickbay' ? 'selected' : '' ?>>Sickbay Annex</option>
                                    <option value="special" <?= $currentType === 'special' ? 'selected' : '' ?>>Special Needs</option>
                                </select>
                            </div>
                            <div class="col-md-12">
                                <label class="form-label fw-semibold">Status</label>
                                <select name="status" class="form-select">
                                    <?php $currentStatus = $old['status'] ?? $room['status'] ?? 'available'; ?>
                                    <option value="available" <?= $currentStatus === 'available' ? 'selected' : '' ?>>Available (Open)</option>
                                    <option value="full" <?= $currentStatus === 'full' ? 'selected' : '' ?>>Full (Max Capacity)</option>
                                    <option value="maintenance" <?= $currentStatus === 'maintenance' ? 'selected' : '' ?>>Under Maintenance</option>
                                </select>
                            </div>
                        </div>
                        <div class="mt-4 d-flex gap-2">
                            <button class="btn btn-primary"><i class="bi bi-check-lg me-1"></i> Update Room</button>
                            <a href="<?= url('views/admin/rooms/index/index.php') ?>" class="btn btn-outline-secondary">Cancel</a>
                        </div>
                    </form>
                <?php endif; ?>
            </div>
        </div>

    </div>
</div>
<?php require APP_ROOT . '/app/views/components/footer/footer.php'; ?>