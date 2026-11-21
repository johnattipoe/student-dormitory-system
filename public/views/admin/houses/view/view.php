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
use App\Services\HouseService;
use App\Services\RoomService;
use App\Services\StudentService;
use App\Services\UserService;

$pageTitle = 'View House';
$id = $_GET['id'] ?? null;
$house = $id ? HouseService::find($id) : null;
$houseName = $house['name'] ?? 'Dormitory House';
$houseStudents = $id ? StudentService::all($id) : [];
$houseRooms = $id ? RoomService::byHouse($id) : [];
$houseStaffUsers = $house ? (new UserService())->all() : [];

$navItems = [
    ['icon' => 'bi-speedometer2', 'label' => 'Dashboard', 'href' => url('views/admin/dashboard.php')],
    ['icon' => 'bi-building', 'label' => 'Houses', 'href' => url('views/admin/houses/index/index.php')],
    ['icon' => 'bi-eye', 'label' => 'View House', 'href' => url('views/admin/houses/view/view.php?id=' . urlencode((string)$id)), 'active' => true],
];
require APP_ROOT . '/app/views/components/header/header.php';
require APP_ROOT . '/app/views/components/sidebar/sidebar.php';
?>
<div class="main-content">
    <?php require APP_ROOT . '/app/views/components/navbar/navbar.php'; ?>
    <?php require APP_ROOT . '/app/views/components/alerts/alerts.php'; ?>
    <div class="content-wrapper">

        <?php if (!$house): ?>
            <div class="alert alert-warning"><i class="bi bi-exclamation-triangle me-2"></i>House not found.</div>
            <a href="<?= url('views/admin/houses/index/index.php') ?>" class="btn btn-outline-secondary">Back to Houses</a>
        <?php else: ?>
            <!-- Page Hero -->
            <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-3">
                <div>
                    <h4 class="mb-1 fw-bold text-dark">
                        <i class="bi bi-building-fill text-success me-2"></i><?= e($houseName) ?>
                    </h4>
                    <p class="text-muted mb-0">
                        Location: <strong><?= e($house['location'] ?? 'Main Campus') ?></strong> &bull; 
                        Gender: <span class="badge bg-light text-dark border"><?= e(ucfirst($house['gender'] ?? 'Mixed')) ?></span>
                    </p>
                </div>
                <div class="d-flex gap-2">
                    <button type="button" class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#adminEditHouseModal">
                        <i class="bi bi-pencil me-1"></i> Edit House
                    </button>
                    <a href="<?= url('views/admin/houses/index/index.php') ?>" class="btn btn-outline-secondary btn-sm">
                        <i class="bi bi-arrow-left me-1"></i> Back to Houses
                    </a>
                </div>
            </div>

            <div class="row g-4">
                <!-- Info Column -->
                <div class="col-lg-8">
                    <div class="card stat-card shadow-sm border-0 mb-4">
                        <div class="card-header bg-white py-3">
                            <h6 class="mb-0 fw-bold"><i class="bi bi-info-circle me-2 text-primary"></i>House Overview</h6>
                        </div>
                        <div class="card-body p-0">
                            <ul class="list-group list-group-flush">
                                <li class="list-group-item d-flex justify-content-between align-items-center py-3">
                                    <span class="text-muted fw-semibold small">House Name</span>
                                    <strong class="text-dark"><?= e($house['name'] ?? '-') ?></strong>
                                </li>
                                <li class="list-group-item d-flex justify-content-between align-items-center py-3">
                                    <span class="text-muted fw-semibold small">Gender Allocation</span>
                                    <span><?= e(ucfirst((string)($house['gender'] ?? '-'))) ?></span>
                                </li>
                                <li class="list-group-item d-flex justify-content-between align-items-center py-3">
                                    <span class="text-muted fw-semibold small">Bed Capacity</span>
                                    <span class="fw-bold text-primary"><?= e((string)($house['capacity'] ?? '-')) ?> beds</span>
                                </li>
                                <li class="list-group-item d-flex justify-content-between align-items-center py-3">
                                    <span class="text-muted fw-semibold small">Campus Location</span>
                                    <span><?= e($house['location'] ?? '-') ?></span>
                                </li>
                                <li class="list-group-item d-flex justify-content-between align-items-center py-3">
                                    <span class="text-muted fw-semibold small">Operational Status</span>
                                    <span class="badge bg-<?= ($house['status'] ?? '') === 'active' ? 'success' : 'secondary' ?>"><?= e(ucfirst($house['status'] ?? 'active')) ?></span>
                                </li>
                            </ul>
                        </div>
                    </div>
                </div>

                <!-- Stats Sidebar -->
                <div class="col-lg-4">
                    <div class="card stat-card shadow-sm border-0 mb-4">
                        <div class="card-header bg-white py-3">
                            <h6 class="mb-0 fw-bold"><i class="bi bi-bar-chart me-2 text-success"></i>Occupancy Quick Stats</h6>
                        </div>
                        <div class="card-body p-3">
                            <div class="d-flex align-items-center justify-content-between p-3 bg-light rounded-3 mb-2">
                                <div>
                                    <span class="small text-muted d-block">Residents Housed</span>
                                    <strong class="fs-4 text-dark"><?= count($houseStudents) ?></strong>
                                </div>
                                <i class="bi bi-people fs-2 text-primary"></i>
                            </div>
                            <div class="d-flex align-items-center justify-content-between p-3 bg-light rounded-3">
                                <div>
                                    <span class="small text-muted d-block">Room Blocks</span>
                                    <strong class="fs-4 text-dark"><?= count($houseRooms) ?></strong>
                                </div>
                                <i class="bi bi-door-closed fs-2 text-info"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        <?php endif; ?>

    </div>
</div>
<?php if ($house): ?>
<div class="modal fade" id="adminEditHouseModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg"><div class="modal-content">
        <form method="POST" action="<?= url('views/admin/houses/edit/edit.php?id=' . urlencode((string) $id)) ?>">
            <input type="hidden" name="id" value="<?= e((string) ($house['id'] ?? $id)) ?>">
            <div class="modal-header"><h5 class="modal-title">Edit House</h5><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button></div>
            <div class="modal-body">
                <div class="row g-3">
                    <div class="col-md-6"><label class="form-label">House Name</label><input name="name" class="form-control" value="<?= e($house['name'] ?? '') ?>" required></div>
                    <div class="col-md-6"><label class="form-label">Gender Allocation</label><select name="gender" class="form-select"><?php foreach (['male', 'female', 'coed'] as $gender): ?><option value="<?= e($gender) ?>" <?= ($house['gender'] ?? '') === $gender ? 'selected' : '' ?>><?= ucfirst($gender) ?></option><?php endforeach; ?></select></div>
                    <div class="col-md-6"><label class="form-label">Bed Capacity</label><input type="number" name="capacity" min="1" class="form-control" value="<?= e((string) ($house['capacity'] ?? 1)) ?>" required></div>
                    <div class="col-md-6"><label class="form-label">Location / Zone</label><input name="location" class="form-control" value="<?= e($house['location'] ?? '') ?>"></div>
                    <div class="col-md-6"><label class="form-label">Assigned House Master</label><select name="houseMasterId" class="form-select"><option value="">Not assigned</option><?php foreach ($houseStaffUsers as $staff): ?><?php if (($staff['role'] ?? '') === ROLE_HOUSE_MASTER): $staffId = (string) ($staff['id'] ?? $staff['uid'] ?? ''); ?><option value="<?= e($staffId) ?>" <?= (($house['houseMasterId'] ?? '') === $staffId) ? 'selected' : '' ?>><?= e($staff['name'] ?? '') ?> (<?= e($staff['email'] ?? '') ?>)</option><?php endif; ?><?php endforeach; ?></select></div>
                    <div class="col-md-6"><label class="form-label">Assigned House Mistress</label><select name="houseMistressId" class="form-select"><option value="">Not assigned</option><?php foreach ($houseStaffUsers as $staff): ?><?php if (($staff['role'] ?? '') === ROLE_HOUSE_MISTRESS): $staffId = (string) ($staff['id'] ?? $staff['uid'] ?? ''); ?><option value="<?= e($staffId) ?>" <?= (($house['houseMistressId'] ?? '') === $staffId) ? 'selected' : '' ?>><?= e($staff['name'] ?? '') ?> (<?= e($staff['email'] ?? '') ?>)</option><?php endif; ?><?php endforeach; ?></select></div>
                    <div class="col-12"><label class="form-label">Status</label><select name="status" class="form-select"><option value="active" <?= ($house['status'] ?? 'active') === 'active' ? 'selected' : '' ?>>Active</option><option value="inactive" <?= ($house['status'] ?? '') === 'inactive' ? 'selected' : '' ?>>Inactive</option></select></div>
                </div>
            </div>
            <div class="modal-footer"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button><button type="submit" class="btn btn-primary">Save changes</button></div>
        </form>
    </div></div>
</div>
<?php endif; ?>
<?php require APP_ROOT . '/app/views/components/footer/footer.php'; ?>