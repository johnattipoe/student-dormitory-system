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
use App\Services\HouseService;
use App\Services\RoomService;
use App\Services\RoomAllocationService;
use App\Services\StudentService;

$pageTitle = 'Room Allocation';
$rooms = RoomService::all();
$houses = HouseService::all();
$students = StudentService::all();
$allocationService = new RoomAllocationService();
$houseMap = [];
$roomMap = [];
foreach ($houses as $house) {
    $houseMap[(string) ($house['id'] ?? '')] = (string) ($house['name'] ?? $house['id'] ?? '');
}
foreach ($rooms as $room) {
    $roomMap[(string) ($room['id'] ?? '')] = $room;
}
$studentMap = [];
$occupantsByRoom = [];
foreach ($students as $student) {
    $studentMap[(string) ($student['id'] ?? '')] = $student;
    $roomId = (string) ($student['roomId'] ?? '');
    if ($roomId !== '') {
        $occupantsByRoom[$roomId][] = $student;
    }
}
$occupantRows = [];
foreach ($occupantsByRoom as $roomId => $roomOccupants) {
    $room = $roomMap[$roomId] ?? [];
    foreach ($roomOccupants as $student) {
        $occupantRows[] = [
            'roomId' => $roomId,
            'roomNumber' => $room['roomNumber'] ?? $roomId,
            'houseName' => $houseMap[(string) ($room['houseId'] ?? '')] ?? ($room['houseId'] ?? '-'),
            'student' => $student,
        ];
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = sanitize($_POST['action'] ?? 'allocate');
    $studentId = sanitize($_POST['studentId'] ?? '');
    $roomId = sanitize($_POST['roomId'] ?? '');
    if ($action === 'remove') {
        $result = $allocationService->deallocate($studentId);
    } elseif ($action === 'transfer') {
        $result = $allocationService->transfer($studentId, $roomId, current_user_id());
    } else {
        $result = $allocationService->allocate([
            'studentId' => $studentId,
            'roomId' => $roomId,
            'allocatedBy' => current_user_id(),
        ]);
    }
    flash(!empty($result['success']) ? 'success' : 'error', $result['message'] ?? 'Allocation action failed.');
    redirect(url('views/admin/rooms/allocation.php'));
}

$search = strtolower(trim(sanitize($_GET['search'] ?? '')));
$statusFilter = sanitize($_GET['status'] ?? '');
$houseFilter = sanitize($_GET['houseId'] ?? '');
$rooms = array_values(array_filter($rooms, function ($room) use ($search, $statusFilter, $houseFilter, $houseMap) {
    $roomId = (string) ($room['id'] ?? '');
    $houseId = (string) ($room['houseId'] ?? '');
    $roomName = (string) ($room['roomNumber'] ?? '');
    $houseName = $houseMap[$houseId] ?? $houseId;
    $occupied = (int) ($room['occupied'] ?? 0);
    $capacity = (int) ($room['capacity'] ?? 0);
    $computedStatus = ($room['status'] ?? '') === 'maintenance'
        ? 'maintenance'
        : ($occupied >= $capacity ? 'full' : ($occupied > 0 ? 'occupied' : 'available'));
    return ($search === '' || str_contains(strtolower($roomName . ' ' . $houseName), $search))
        && ($statusFilter === '' || $computedStatus === $statusFilter)
        && ($houseFilter === '' || $houseId === $houseFilter);
}));

if (isset($_GET['download']) && $_GET['download'] === 'csv') {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="room-allocation.csv"');
    $output = fopen('php://output', 'w');
    fputcsv($output, ['Room', 'House', 'Capacity', 'Occupied', 'Available', 'Status', 'Occupants']);
    foreach ($rooms as $room) {
        $capacity = (int) ($room['capacity'] ?? 0);
        $occupied = (int) ($room['occupied'] ?? 0);
        $occupantNames = array_map(static fn ($student) => trim(($student['firstName'] ?? '') . ' ' . ($student['lastName'] ?? '')), $occupantsByRoom[(string) ($room['id'] ?? '')] ?? []);
        fputcsv($output, [$room['roomNumber'] ?? '', $houseMap[(string) ($room['houseId'] ?? '')] ?? '', $capacity, $occupied, max(0, $capacity - $occupied), $room['status'] ?? '', implode(', ', $occupantNames)]);
    }
    fclose($output);
    exit;
}
$totalCapacity = array_sum(array_map(static fn ($room) => (int) ($room['capacity'] ?? 0), $rooms));
$totalOccupied = array_sum(array_map(static fn ($room) => (int) ($room['occupied'] ?? 0), $rooms));
$availableSpaces = max(0, $totalCapacity - $totalOccupied);
$fullRooms = count(array_filter($rooms, static fn ($room) => (int) ($room['occupied'] ?? 0) >= (int) ($room['capacity'] ?? 0)));
$navItems = [
    ['icon' => 'bi-speedometer2', 'label' => 'Dashboard', 'href' => url('views/admin/dashboard.php')],
    ['icon' => 'bi-door-closed', 'label' => 'Rooms', 'href' => url('views/admin/rooms/index.php')],
    ['icon' => 'bi-diagram-3', 'label' => 'Allocation', 'href' => url('views/admin/rooms/allocation.php'), 'active' => true],
];
require APP_ROOT . '/app/views/components/header.php';
require APP_ROOT . '/app/views/components/sidebar.php';
?>
<div class="main-content">
    <?php require APP_ROOT . '/app/views/components/navbar.php'; ?>
    <?php require APP_ROOT . '/app/views/components/alerts.php'; ?>
    <div class="content-wrapper">
        <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
            <div><h5 class="mb-1">Room Allocation</h5><p class="text-muted mb-0">Assign, transfer, and review student room occupancy.</p></div>
            <a class="btn btn-success btn-sm" href="<?= url('views/admin/rooms/allocation.php?download=csv&search=' . urlencode($search) . '&status=' . urlencode($statusFilter) . '&houseId=' . urlencode($houseFilter)) ?>"><i class="bi bi-filetype-csv"></i> Export CSV</a>
        </div>
        <div class="row g-3 mb-3">
            <div class="col-md-3"><div class="card stat-card p-3"><small class="text-muted">Rooms shown</small><strong class="fs-3"><?= e((string) count($rooms)) ?></strong></div></div>
            <div class="col-md-3"><div class="card stat-card p-3"><small class="text-muted">Capacity</small><strong class="fs-3"><?= e((string) $totalCapacity) ?></strong></div></div>
            <div class="col-md-3"><div class="card stat-card p-3"><small class="text-muted">Available spaces</small><strong class="fs-3 text-success"><?= e((string) $availableSpaces) ?></strong></div></div>
            <div class="col-md-3"><div class="card stat-card p-3"><small class="text-muted">Full rooms</small><strong class="fs-3 text-danger"><?= e((string) $fullRooms) ?></strong></div></div>
        </div>
        <div class="card stat-card p-4 mb-3">
            <h6 class="mb-3">Assign or transfer student</h6>
            <form method="POST" class="row g-3">
                <div class="col-md-5"><label class="form-label">Student</label><select name="studentId" class="form-select" required><option value="">Select student</option><?php foreach ($students as $student): ?><option value="<?= e((string) ($student['id'] ?? '')) ?>"><?= e(trim(($student['firstName'] ?? '') . ' ' . ($student['lastName'] ?? ''))) ?><?= !empty($student['roomId']) ? ' (currently assigned)' : '' ?></option><?php endforeach; ?></select></div>
                <div class="col-md-4"><label class="form-label">Destination room</label><select name="roomId" class="form-select" required><option value="">Select room</option><?php foreach (RoomService::all() as $room): $occupied = (int) ($room['occupied'] ?? 0); $capacity = (int) ($room['capacity'] ?? 0); ?><option value="<?= e((string) ($room['id'] ?? '')) ?>" <?= (($room['status'] ?? '') === 'maintenance' || $occupied >= $capacity) ? 'disabled' : '' ?>><?= e($room['roomNumber'] ?? '-') ?> - <?= e($houseMap[(string) ($room['houseId'] ?? '')] ?? '-') ?> (<?= e((string) $occupied) ?>/<?= e((string) $capacity) ?>)</option><?php endforeach; ?></select></div>
                <div class="col-md-3"><label class="form-label">Action</label><select name="action" class="form-select"><option value="allocate">New allocation</option><option value="transfer">Transfer student</option></select></div>
                <div class="col-12"><button class="btn btn-primary">Save allocation</button></div>
            </form>
        </div>
        <div class="card stat-card p-3 mb-3"><form method="GET" class="row g-2"><div class="col-md-5"><input name="search" class="form-control form-control-sm" placeholder="Search room or house" value="<?= e($search) ?>"></div><div class="col-md-3"><select name="houseId" class="form-select form-select-sm"><option value="">All houses</option><?php foreach ($houses as $house): ?><option value="<?= e((string) ($house['id'] ?? '')) ?>" <?= $houseFilter === ($house['id'] ?? '') ? 'selected' : '' ?>><?= e($house['name'] ?? '') ?></option><?php endforeach; ?></select></div><div class="col-md-2"><select name="status" class="form-select form-select-sm"><option value="">All statuses</option><?php foreach (['available','occupied','full','maintenance'] as $status): ?><option value="<?= e($status) ?>" <?= $statusFilter === $status ? 'selected' : '' ?>><?= ucfirst($status) ?></option><?php endforeach; ?></select></div><div class="col-md-2"><button class="btn btn-primary btn-sm">Filter</button> <a class="btn btn-outline-secondary btn-sm" href="<?= url('views/admin/rooms/allocation.php') ?>">Reset</a></div></form></div>
        <div class="card stat-card p-3">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead>
                        <tr>
                            <th>Room</th>
                            <th>House</th>
                            <th>Capacity</th>
                            <th>Occupied</th>
                            <th>Available</th>
                            <th>Status</th>
                            <th>Occupants</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!$rooms): ?>
                            <tr><td colspan="8" class="text-center text-muted py-4">No rooms match the selected filters.</td></tr>
                        <?php else: ?>
                            <?php foreach ($rooms as $room): $roomId = (string) ($room['id'] ?? ''); $occupied = (int) ($room['occupied'] ?? 0); $capacity = (int) ($room['capacity'] ?? 0); $available = max(0, $capacity - $occupied); $status = ($room['status'] ?? '') === 'maintenance' ? 'maintenance' : ($occupied >= $capacity ? 'full' : ($occupied > 0 ? 'occupied' : 'available')); ?>
                                <tr>
                                    <td><strong><?= e($room['roomNumber'] ?? '-') ?></strong></td>
                                    <td><?= e($houseMap[(string) ($room['houseId'] ?? '')] ?? ($room['houseId'] ?? '-')) ?></td>
                                    <td><?= e((string) $capacity) ?></td>
                                    <td><?= e((string) $occupied) ?></td>
                                    <td><?= e((string) $available) ?></td>
                                    <td><span class="badge bg-<?= $status === 'full' ? 'danger' : ($status === 'maintenance' ? 'secondary' : ($status === 'occupied' ? 'warning' : 'success')) ?>"><?= e($status) ?></span></td>
                                    <td><?= !empty($occupantsByRoom[$roomId]) ? e((string) count($occupantsByRoom[$roomId])) . ' student(s)' : '<span class="text-muted">None</span>' ?></td>
                                    <td class="text-nowrap">
                                        <div class="dropdown">
                                            <button class="btn btn-sm btn-outline-primary dropdown-toggle" type="button" data-bs-toggle="dropdown" aria-expanded="false">Actions</button>
                                            <ul class="dropdown-menu dropdown-menu-end">
                                                <li><a class="dropdown-item" href="<?= url('views/admin/rooms/view.php?id=' . urlencode($roomId)) ?>"><i class="bi bi-eye me-2"></i>View</a></li>
                                                <li><a class="dropdown-item" href="<?= url('views/admin/rooms/edit.php?id=' . urlencode($roomId)) ?>"><i class="bi bi-pencil me-2"></i>Edit</a></li>
                                                <li><hr class="dropdown-divider"></li>
                                                <li><a class="dropdown-item text-danger" href="<?= url('views/admin/rooms/delete.php?id=' . urlencode($roomId)) ?>"><i class="bi bi-trash me-2"></i>Delete</a></li>
                                            </ul>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <div class="card stat-card p-3 mt-3">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <h6 class="mb-0">Current Occupants</h6>
                <span class="badge bg-primary"><?= e((string) count($occupantRows)) ?> assigned</span>
            </div>
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead><tr><th>Student</th><th>Admission No.</th><th>Room ID</th><th>Room</th><th>House</th><th>Action</th></tr></thead>
                    <tbody>
                        <?php if (!$occupantRows): ?>
                            <tr><td colspan="6" class="text-center text-muted py-4">No students are currently assigned to rooms.</td></tr>
                        <?php else: foreach ($occupantRows as $occupant): $student = $occupant['student']; ?>
                            <tr>
                                <td><strong><?= e(trim(($student['firstName'] ?? '') . ' ' . ($student['lastName'] ?? ''))) ?></strong></td>
                                <td><?= e($student['admissionNo'] ?? '-') ?></td>
                                <td><code><?= e($occupant['roomId']) ?></code></td>
                                <td><?= e($occupant['roomNumber']) ?></td>
                                <td><?= e($occupant['houseName']) ?></td>
                                <td><form method="POST" class="d-inline"><input type="hidden" name="action" value="remove"><input type="hidden" name="studentId" value="<?= e((string) ($student['id'] ?? '')) ?>"><button class="btn btn-sm btn-outline-danger" onclick="return confirm('Remove this student from the room?')"><i class="bi bi-person-dash me-1"></i>Remove</button></form></td>
                            </tr>
                        <?php endforeach; endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
<?php require APP_ROOT . '/app/views/components/footer.php'; ?>