<?php
require __DIR__ . '/../../../bootstrap.php';
$allowedRoles = [ROLE_ADMIN];
require APP_ROOT . '/app/middleware/RoleMiddleware.php';
use App\Services\FirebaseService;
use App\Services\RoomService;

$pageTitle = 'Add Room';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = [
        'roomNumber' => sanitize($_POST['roomNumber'] ?? ''),
        'houseId' => sanitize($_POST['houseId'] ?? ''),
        'capacity' => (int) ($_POST['capacity'] ?? 1),
        'type' => sanitize($_POST['type'] ?? 'standard'),
        'status' => sanitize($_POST['status'] ?? 'available'),
    ];

    $errors = validate_required($data, ['roomNumber']);

    if (!empty($errors)) {
        $_SESSION['_errors'] = $errors;
        $_SESSION['_old'] = $data;
        flash('error', 'Please fix the highlighted fields.');
        redirect(base_url('index.php?route=/views/admin/rooms/create.php'));
    }

    RoomService::create($data);
    flash('success', 'Room created successfully.');
    redirect(base_url('index.php?route=/views/admin/rooms/index.php'));
}

$errors = $_SESSION['_errors'] ?? [];
unset($_SESSION['_errors']);
$old = $_SESSION['_old'] ?? [];
unset($_SESSION['_old']);

$houses = FirebaseService::getInstance()->getCollection(COL_HOUSES, [], 200);
$navItems = [
    ['icon' => 'bi-speedometer2', 'label' => 'Dashboard', 'href' => url('views/admin/dashboard.php')],
    ['icon' => 'bi-door-closed', 'label' => 'Rooms', 'href' => url('views/admin/rooms/index.php')],
    ['icon' => 'bi-plus-lg', 'label' => 'Add Room', 'href' => url('views/admin/rooms/create.php'), 'active' => true],
];
require APP_ROOT . '/app/views/components/header.php';
require APP_ROOT . '/app/views/components/sidebar.php';
?>
<div class="main-content">
    <?php require APP_ROOT . '/app/views/components/navbar.php'; ?>
    <div class="content-wrapper">
        <div class="card stat-card p-4" style="max-width:720px;">
            <h5 class="mb-3">Create Room</h5>
            <form method="POST" action="<?= url('views/admin/rooms/create.php') ?>">
                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label">Room Number</label>
                        <input name="roomNumber" class="form-control" value="<?= e($old['roomNumber'] ?? '') ?>" required>
                        <?php if (!empty($errors['roomNumber'])): ?><div class="text-danger small"><?= e($errors['roomNumber']) ?></div><?php endif; ?>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">House</label>
                        <select name="houseId" class="form-select">
                            <option value="">- Select House -</option>
                            <?php foreach ($houses as $house): ?>
                                <option value="<?= e($house['id']) ?>" <?= ($old['houseId'] ?? '') === ($house['id'] ?? '') ? 'selected' : '' ?>><?= e($house['name'] ?? $house['id']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Capacity</label>
                        <input type="number" name="capacity" class="form-control" min="1" value="<?= e($old['capacity'] ?? 1) ?>" required>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Type</label>
                        <select name="type" class="form-select">
                            <?php foreach (['standard','deluxe'] as $type): ?>
                                <option value="<?= e($type) ?>" <?= ($old['type'] ?? 'standard') === $type ? 'selected' : '' ?>><?= ucfirst($type) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-12">
                        <label class="form-label">Status</label>
                        <select name="status" class="form-select">
                            <?php foreach (['available','occupied'] as $status): ?>
                                <option value="<?= e($status) ?>" <?= ($old['status'] ?? 'available') === $status ? 'selected' : '' ?>><?= ucfirst($status) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <div class="mt-4 d-flex gap-2">
                    <button class="btn btn-primary">Save Room</button>
                    <a href="<?= url('views/admin/rooms/index.php') ?>" class="btn btn-outline-secondary">Cancel</a>
                </div>
            </form>
        </div>
    </div>
</div>
<?php require APP_ROOT . '/app/views/components/footer.php'; ?>