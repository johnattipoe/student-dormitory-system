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
require APP_ROOT . '/app/middleware/RoleMiddleware.php';

use App\Services\BedService;
use App\Services\HouseService;
use App\Services\RoomService;
use App\Services\StudentService;

$rooms = RoomService::all();
$houses = HouseService::all();
$students = StudentService::all();
$allBeds = BedService::all();
$beds = $allBeds;
$houseMap = [];
$roomMap = [];
foreach ($houses as $house) $houseMap[(string) ($house['id'] ?? '')] = (string) ($house['name'] ?? $house['id'] ?? '');
foreach ($rooms as $room) $roomMap[(string) ($room['id'] ?? '')] = $room;
$studentMap = [];
foreach ($students as $student) $studentMap[(string) ($student['id'] ?? '')] = $student;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = sanitize($_POST['action'] ?? '');
    $bedId = sanitize($_POST['bedId'] ?? '');
    if ($action === 'create') {
        $result = BedService::create(['bedNumber' => sanitize($_POST['bedNumber'] ?? ''), 'roomId' => sanitize($_POST['roomId'] ?? ''), 'status' => sanitize($_POST['status'] ?? 'available')]);
    } elseif ($action === 'update') {
        $result = BedService::update($bedId, ['bedNumber' => sanitize($_POST['bedNumber'] ?? ''), 'roomId' => sanitize($_POST['roomId'] ?? ''), 'status' => sanitize($_POST['status'] ?? 'available')]);
    } elseif ($action === 'assign') {
        $result = BedService::assign($bedId, sanitize($_POST['studentId'] ?? ''));
    } elseif ($action === 'unassign') {
        $result = BedService::unassign($bedId);
    } elseif ($action === 'delete') {
        $result = BedService::delete($bedId);
    } else {
        $result = ['success' => false, 'message' => 'Unknown bed action.'];
    }
    flash(!empty($result['success']) ? 'success' : 'error', $result['message'] ?? 'Bed action failed.');
    redirect(url('views/admin/beds/index.php'));
}

$search = strtolower(trim(sanitize($_GET['search'] ?? '')));
$statusFilter = sanitize($_GET['status'] ?? '');
$roomFilter = sanitize($_GET['roomId'] ?? '');
$beds = array_values(array_filter($beds, function ($bed) use ($search, $statusFilter, $roomFilter, $roomMap, $houseMap) {
    $room = $roomMap[(string) ($bed['roomId'] ?? '')] ?? [];
    $roomName = (string) ($room['roomNumber'] ?? $bed['roomId'] ?? '');
    $houseName = $houseMap[(string) ($room['houseId'] ?? '')] ?? '';
    return ($search === '' || str_contains(strtolower(($bed['bedNumber'] ?? '') . ' ' . $roomName . ' ' . $houseName), $search))
        && ($statusFilter === '' || ($bed['status'] ?? 'available') === $statusFilter)
        && ($roomFilter === '' || (string) ($bed['roomId'] ?? '') === $roomFilter);
}));

$pageTitle = 'Bed Management';
$navItems = [
    ['icon' => 'bi-speedometer2', 'label' => 'Dashboard', 'href' => url('views/admin/dashboard.php')],
    ['icon' => 'bi-door-closed', 'label' => 'Rooms', 'href' => url('views/admin/rooms/index.php')],
    ['icon' => 'bi-grid-3x3-gap', 'label' => 'Beds', 'href' => url('views/admin/beds/index.php'), 'active' => true],
];
require APP_ROOT . '/app/views/components/header.php';
require APP_ROOT . '/app/views/components/sidebar.php';
?>
<div class="main-content" data-disable-loading>
    <?php require APP_ROOT . '/app/views/components/navbar.php'; ?>
    <?php require APP_ROOT . '/app/views/components/alerts.php'; ?>
    <div class="content-wrapper">
        <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
            <div><h5 class="mb-1">Bed Management</h5><p class="text-muted mb-0">Create beds, assign students, and manage availability.</p></div>
            <div class="d-flex gap-2">
                <a class="btn btn-outline-success btn-sm" href="<?= url('views/admin/beds/export.php') ?>"><i class="bi bi-download"></i> Export</a>
                <a class="btn btn-outline-primary btn-sm" href="<?= url('views/admin/beds/import.php') ?>"><i class="bi bi-upload"></i> Import</a>
                <button class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#createBedModal"><i class="bi bi-plus-lg"></i> Add bed</button>
            </div>
        </div>
        <div class="row g-3 mb-3">
            <div class="col-md-3"><div class="card stat-card p-3"><small class="text-muted">Beds shown</small><strong class="fs-3"><?= e((string) count($beds)) ?></strong></div></div>
            <div class="col-md-3"><div class="card stat-card p-3"><small class="text-muted">Occupied</small><strong class="fs-3 text-warning"><?= e((string) count(array_filter($beds, static fn ($bed) => ($bed['status'] ?? '') === 'occupied'))) ?></strong></div></div>
            <div class="col-md-3"><div class="card stat-card p-3"><small class="text-muted">Available</small><strong class="fs-3 text-success"><?= e((string) count(array_filter($beds, static fn ($bed) => ($bed['status'] ?? 'available') === 'available'))) ?></strong></div></div>
            <div class="col-md-3"><div class="card stat-card p-3"><small class="text-muted">Maintenance</small><strong class="fs-3 text-secondary"><?= e((string) count(array_filter($beds, static fn ($bed) => ($bed['status'] ?? '') === 'maintenance'))) ?></strong></div></div>
        </div>
        <div class="card stat-card p-4 mb-3">
            <h6 class="mb-3">Assign student to bed</h6>
            <form method="POST" class="row g-3">
                <input type="hidden" name="action" value="assign">
                <div class="col-md-5">
                    <label class="form-label">Student</label>
                    <select name="studentId" class="form-select" required>
                        <option value="">Select student</option>
                        <?php foreach ($students as $student): ?>
                            <?php if (in_array(strtolower((string) ($student['status'] ?? 'active')), ['inactive', 'suspended'], true)) continue; ?>
                            <option value="<?= e((string) ($student['id'] ?? '')) ?>"><?= e(trim(($student['firstName'] ?? '') . ' ' . ($student['lastName'] ?? ''))) ?> (<?= e($student['admissionNo'] ?? '') ?>)</option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-5">
                    <label class="form-label">Available bed</label>
                    <select name="bedId" class="form-select" required>
                        <option value="">Select bed</option>
                        <?php foreach ($allBeds as $bed): ?>
                            <?php if (($bed['status'] ?? 'available') !== 'available' || !empty($bed['studentId'])) continue; $bedRoom = $roomMap[(string) ($bed['roomId'] ?? '')] ?? []; ?>
                            <option value="<?= e((string) ($bed['id'] ?? '')) ?>"><?= e($bed['bedNumber'] ?? '-') ?> - <?= e($bedRoom['roomNumber'] ?? '-') ?> (<?= e($houseMap[(string) ($bedRoom['houseId'] ?? '')] ?? '-') ?>)</option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-2 d-flex align-items-end">
                    <button class="btn btn-primary w-100">Assign</button>
                </div>
            </form>
        </div>
        <div class="card stat-card p-3 mb-3">
            <form method="GET" class="row g-2">
                <div class="col-md-5">
                    <input name="search" class="form-control form-control-sm" placeholder="Search bed, room, or house" value="<?= e($search) ?>">
                </div>
                <div class="col-md-3">
                    <select name="roomId" class="form-select form-select-sm">
                        <option value="">All rooms</option><?php foreach ($rooms as $room): ?>
                            <option value="<?= e((string) ($room['id'] ?? '')) ?>" <?= $roomFilter === ($room['id'] ?? '') ? 'selected' : '' ?>><?= e($room['roomNumber'] ?? '-') ?> - <?= e($houseMap[(string) ($room['houseId'] ?? '')] ?? '-') ?>
                        </option><?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-2">
                    <select name="status" class="form-select form-select-sm">
                        <option value="">All statuses</option><?php foreach (['available','occupied','maintenance'] as $status): ?>
                            <option value="<?= e($status) ?>" <?= $statusFilter === $status ? 'selected' : '' ?>><?= ucfirst($status) ?>
                        </option><?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-2">
                    <button class="btn btn-primary btn-sm">Filter</button>
                    <a class="btn btn-outline-secondary btn-sm" href="<?= url('views/admin/beds/index.php') ?>">Reset</a>
                </div>
            </form>
        </div>
        <div class="card stat-card p-3">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead>
                        <tr>
                            <th>Bed</th>
                            <th>Room</th>
                            <th>House</th>
                            <th>Student</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead><tbody>
        <?php if (!$beds): ?>
            <tr>
                <td colspan="6" class="text-center text-muted py-4">No beds found. Add the first bed to begin.</td>
            </tr>
            <?php else: foreach ($beds as $bed): $bedId = (string) ($bed['id'] ?? ''); $room = $roomMap[(string) ($bed['roomId'] ?? '')] ?? []; $student = $studentMap[(string) ($bed['studentId'] ?? '')] ?? []; $status = $bed['status'] ?? 'available'; ?>
            <tr>
                <td>
                    <strong><?= e($bed['bedNumber'] ?? '-') ?></strong>
                </td>
                <td><?= e($room['roomNumber'] ?? ($bed['roomId'] ?? '-')) ?></td>
                <td><?= e($houseMap[(string) ($room['houseId'] ?? '')] ?? '-') ?></td>
                <td><?= $student ? e(trim(($student['firstName'] ?? '') . ' ' . ($student['lastName'] ?? ''))) . '<small class="d-block text-muted">' . e($student['admissionNo'] ?? '') . '</small>' : '<span class="text-muted">Unassigned</span>' ?></td>
                <td>
                    <span class="badge bg-<?= $status === 'occupied' ? 'warning' : ($status === 'maintenance' ? 'secondary' : 'success') ?>"><?= e($status) ?></span>
                </td>
                <td class="text-nowrap">
                    <button type="button" class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#viewBed<?= e($bedId) ?>">
                        <i class="bi bi-eye"></i> View</button> 
                        <button type="button" class="btn btn-sm btn-outline-secondary" data-bs-toggle="modal" data-bs-target="#editBed<?= e($bedId) ?>">
                            <i class="bi bi-pencil"></i> Edit</button> 
                            <?php if (!$student && $status !== 'maintenance'): ?>
                                <button type="button" class="btn btn-sm btn-outline-success" data-bs-toggle="modal" data-bs-target="#assignBed<?= e($bedId) ?>">
                                    <i class="bi bi-person-plus"></i> Assign</button>
                                    <?php elseif ($student): ?>
                                        <form method="POST" class="d-inline">
                                            <input type="hidden" name="action" value="unassign">
                                            <input type="hidden" name="bedId" value="<?= e($bedId) ?>">
                                            <button class="btn btn-sm btn-outline-warning"><i class="bi bi-person-dash"></i> Unassign</button>
                                        </form><?php endif; ?> 
                                        <form method="POST" class="d-inline">
                                            <input type="hidden" name="action" value="delete">
                                            <input type="hidden" name="bedId" value="<?= e($bedId) ?>">
                                            <button class="btn btn-sm btn-outline-danger" onclick="return confirm('Delete this bed?')">
                                                <i class="bi bi-trash"></i> Delete
                                            </button>
                                        </form>
                                    </td>
                                </tr>
            <div class="modal fade" id="viewBed<?= e($bedId) ?>" tabindex="-1"><div class="modal-dialog"><div class="modal-content"><div class="modal-header"><h5 class="modal-title">Bed <?= e($bed['bedNumber'] ?? '-') ?></h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div><div class="modal-body"><p><strong>Room:</strong> <?= e($room['roomNumber'] ?? '-') ?></p><p><strong>House:</strong> <?= e($houseMap[(string) ($room['houseId'] ?? '')] ?? '-') ?></p><p><strong>Student:</strong> <?= $student ? e(trim(($student['firstName'] ?? '') . ' ' . ($student['lastName'] ?? ''))) : 'Unassigned' ?></p><p><strong>Status:</strong> <?= e($status) ?></p></div></div></div></div>
            <div class="modal fade" id="editBed<?= e($bedId) ?>" tabindex="-1"><div class="modal-dialog"><div class="modal-content"><form method="POST"><div class="modal-header"><h5 class="modal-title">Edit bed</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div><div class="modal-body"><input type="hidden" name="action" value="update"><input type="hidden" name="bedId" value="<?= e($bedId) ?>"><label class="form-label">Bed number</label><input name="bedNumber" class="form-control mb-3" value="<?= e($bed['bedNumber'] ?? '') ?>" required><label class="form-label">Room</label><select name="roomId" class="form-select mb-3" required><?php foreach ($rooms as $optionRoom): ?><option value="<?= e((string) ($optionRoom['id'] ?? '')) ?>" <?= (string) ($bed['roomId'] ?? '') === (string) ($optionRoom['id'] ?? '') ? 'selected' : '' ?>><?= e($optionRoom['roomNumber'] ?? '-') ?> - <?= e($houseMap[(string) ($optionRoom['houseId'] ?? '')] ?? '-') ?></option><?php endforeach; ?></select><label class="form-label">Status</label><select name="status" class="form-select"><option value="available" <?= $status === 'available' ? 'selected' : '' ?>>Available</option><option value="maintenance" <?= $status === 'maintenance' ? 'selected' : '' ?>>Maintenance</option></select></div><div class="modal-footer"><button class="btn btn-primary">Save changes</button></div></form></div></div></div>
            <?php if (!$student && $status !== 'maintenance'): ?><div class="modal fade" id="assignBed<?= e($bedId) ?>" tabindex="-1"><div class="modal-dialog"><div class="modal-content"><form method="POST"><div class="modal-header"><h5 class="modal-title">Assign bed <?= e($bed['bedNumber'] ?? '-') ?></h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div><div class="modal-body"><input type="hidden" name="action" value="assign"><input type="hidden" name="bedId" value="<?= e($bedId) ?>"><label class="form-label">Student</label><select name="studentId" class="form-select" required><option value="">Select student</option><?php foreach ($students as $optionStudent): ?><option value="<?= e((string) ($optionStudent['id'] ?? '')) ?>"><?= e(trim(($optionStudent['firstName'] ?? '') . ' ' . ($optionStudent['lastName'] ?? ''))) ?> (<?= e($optionStudent['admissionNo'] ?? '') ?>)</option><?php endforeach; ?></select></div><div class="modal-footer"><button class="btn btn-primary">Assign bed</button></div></form></div></div></div><?php endif; ?>
        <?php endforeach; endif; ?></tbody></table></div></div>
    </div>
</div>
<div class="modal fade" id="createBedModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST">
                <div class="modal-header">
                    <h5 class="modal-title">Add bed</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="action" value="create">
                    <label class="form-label">Bed number</label>
                    <input name="bedNumber" class="form-control mb-3" placeholder="e.g. Bed 1" required>
                    <label class="form-label">Room</label>
                    <select name="roomId" class="form-select mb-3" required>
                        <option value="">Select room</option>
                        <?php foreach ($rooms as $room): ?>
                            <option value="<?= e((string) ($room['id'] ?? '')) ?>"><?= e($room['roomNumber'] ?? '-') ?> - <?= e($houseMap[(string) ($room['houseId'] ?? '')] ?? '-') ?></option>
                        <?php endforeach; ?>
                    </select>
                    <label class="form-label">Status</label>
                    <select name="status" class="form-select">
                        <option value="available">Available</option>
                        <option value="maintenance">Maintenance</option>
                    </select>
                </div>
                <div class="modal-footer">
                    <button class="btn btn-primary">Create bed</button>
                </div>
            </form>
        </div>
    </div>
</div>
<script>
document.querySelectorAll('.modal').forEach(function (modal) {
    modal.dataset.disableLoading = 'true';
    document.body.appendChild(modal);
});
</script>
<?php require APP_ROOT . '/app/views/components/footer.php'; ?>
