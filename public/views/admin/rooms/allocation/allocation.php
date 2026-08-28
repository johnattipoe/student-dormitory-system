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
    $room = $roomMap[$roomId] ?? null;
    $roomNumber = $room ? ($room['roomNumber'] ?? $roomId) : 'Room #' . substr($roomId, 0, 6) . ' (Deleted)';
    $houseName = $room ? ($houseMap[(string) ($room['houseId'] ?? '')] ?? ($room['houseId'] ?? '-')) : '-';
    foreach ($roomOccupants as $student) {
        $occupantRows[] = [
            'roomId' => $roomId,
            'roomNumber' => $roomNumber,
            'houseName' => $houseName,
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
    redirect(url('views/admin/rooms/allocation/allocation.php'));
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
    ['icon' => 'bi-door-closed', 'label' => 'Rooms', 'href' => url('views/admin/rooms/index/index.php')],
    ['icon' => 'bi-diagram-3', 'label' => 'Allocation', 'href' => url('views/admin/rooms/allocation/allocation.php'), 'active' => true],
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
                    <i class="bi bi-diagram-3-fill text-primary me-2"></i>Dormitory Bed Space Allocation
                </h4>
                <p class="text-muted mb-0">Assign resident students to rooms, process room transfers, and track house density</p>
            </div>
            <div class="d-flex gap-2 flex-wrap">
                <a class="btn btn-outline-success btn-sm" href="<?= url('views/admin/rooms/allocation/allocation.php?download=csv&search=' . urlencode($search) . '&status=' . urlencode($statusFilter) . '&houseId=' . urlencode($houseFilter)) ?>">
                    <i class="bi bi-filetype-csv me-1"></i> Export CSV
                </a>
                <a href="<?= url('views/admin/rooms/index/index.php') ?>" class="btn btn-outline-secondary btn-sm">
                    <i class="bi bi-door-closed me-1"></i> Rooms List
                </a>
            </div>
        </div>

        <!-- KPI Stats Row -->
        <div class="row g-3 mb-4">
            <div class="col-sm-6 col-lg-3">
                <div class="card stat-card h-100 p-3 border-start border-4 border-info shadow-sm">
                    <div class="d-flex justify-content-between align-items-start">
                        <div>
                            <span class="text-muted small text-uppercase fw-semibold">Filtered Rooms</span>
                            <h3 class="fw-bold my-1 text-info"><?= e((string) count($rooms)) ?></h3>
                            <span class="small text-muted">Active in filter</span>
                        </div>
                        <div class="rounded-3 bg-info bg-opacity-10 p-2 text-info"><i class="bi bi-door-closed fs-4"></i></div>
                    </div>
                </div>
            </div>

            <div class="col-sm-6 col-lg-3">
                <div class="card stat-card h-100 p-3 border-start border-4 border-primary shadow-sm">
                    <div class="d-flex justify-content-between align-items-start">
                        <div>
                            <span class="text-muted small text-uppercase fw-semibold">Bed Capacity</span>
                            <h3 class="fw-bold my-1 text-primary"><?= e((string) $totalCapacity) ?></h3>
                            <span class="small text-muted"><?= e((string) $totalOccupied) ?> occupied beds</span>
                        </div>
                        <div class="rounded-3 bg-primary bg-opacity-10 p-2 text-primary"><i class="bi bi-grid-3x3-gap fs-4"></i></div>
                    </div>
                </div>
            </div>

            <div class="col-sm-6 col-lg-3">
                <div class="card stat-card h-100 p-3 border-start border-4 border-success shadow-sm">
                    <div class="d-flex justify-content-between align-items-start">
                        <div>
                            <span class="text-muted small text-uppercase fw-semibold">Available Beds</span>
                            <h3 class="fw-bold my-1 text-success"><?= e((string) $availableSpaces) ?></h3>
                            <span class="small text-muted">Ready for assignment</span>
                        </div>
                        <div class="rounded-3 bg-success bg-opacity-10 p-2 text-success"><i class="bi bi-check-circle fs-4"></i></div>
                    </div>
                </div>
            </div>

            <div class="col-sm-6 col-lg-3">
                <div class="card stat-card h-100 p-3 border-start border-4 border-danger shadow-sm">
                    <div class="d-flex justify-content-between align-items-start">
                        <div>
                            <span class="text-muted small text-uppercase fw-semibold">Full Rooms</span>
                            <h3 class="fw-bold my-1 text-danger"><?= e((string) $fullRooms) ?></h3>
                            <span class="small text-muted">At 100% capacity</span>
                        </div>
                        <div class="rounded-3 bg-danger bg-opacity-10 p-2 text-danger"><i class="bi bi-people-fill fs-4"></i></div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Assignment Form Card -->
        <div class="card stat-card shadow-sm border-0 mb-4">
            <div class="card-header bg-white py-3">
                <h6 class="mb-0 fw-bold"><i class="bi bi-person-plus me-2 text-primary"></i>Assign or Transfer Student</h6>
            </div>
            <div class="card-body p-4">
                <form method="POST" class="row g-3 align-items-end">
                    <div class="col-md-5">
                        <label class="form-label fw-semibold">Select Student</label>
                        <select name="studentId" class="form-select select2" required>
                            <option value="">-- Choose student --</option>
                            <?php foreach ($students as $student): ?>
                                <option value="<?= e((string) ($student['id'] ?? '')) ?>">
                                    <?= e(trim(($student['firstName'] ?? '') . ' ' . ($student['lastName'] ?? ''))) ?> (Adm: <?= e($student['admissionNo'] ?? '—') ?>)<?= !empty($student['roomId']) ? ' — [Currently Assigned]' : '' ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label fw-semibold">Destination Room</label>
                        <select name="roomId" class="form-select select2" required>
                            <option value="">-- Choose destination room --</option>
                            <?php foreach (RoomService::all() as $room): $occupied = (int) ($room['occupied'] ?? 0); $capacity = (int) ($room['capacity'] ?? 0); ?>
                                <option value="<?= e((string) ($room['id'] ?? '')) ?>" <?= (($room['status'] ?? '') === 'maintenance' || $occupied >= $capacity) ? 'disabled' : '' ?>>
                                    Room <?= e($room['roomNumber'] ?? '-') ?> &bull; <?= e($houseMap[(string) ($room['houseId'] ?? '')] ?? '-') ?> (<?= e((string) $occupied) ?>/<?= e((string) $capacity) ?> beds)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label fw-semibold">Operation Type</label>
                        <select name="action" class="form-select">
                            <option value="allocate">New Space Allocation</option>
                            <option value="transfer">Inter-Room Transfer</option>
                        </select>
                    </div>
                    <div class="col-12 mt-3">
                        <button class="btn btn-primary"><i class="bi bi-check-lg me-1"></i> Save Space Allocation</button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Filter Search Bar -->
        <div class="card stat-card shadow-sm mb-4 border-0">
            <div class="card-body p-3">
                <form method="GET" class="row g-2 align-items-center">
                    <div class="col-md-5">
                        <input name="search" class="form-control form-control-sm" placeholder="Search by room number or house name..." value="<?= e($search) ?>">
                    </div>
                    <div class="col-md-3">
                        <select name="houseId" class="form-select form-select-sm">
                            <option value="">All Dormitory Houses</option>
                            <?php foreach ($houses as $house): ?>
                                <option value="<?= e((string) ($house['id'] ?? '')) ?>" <?= $houseFilter === ($house['id'] ?? '') ? 'selected' : '' ?>><?= e($house['name'] ?? '') ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <select name="status" class="form-select form-select-sm">
                            <option value="">All Room Statuses</option>
                            <?php foreach (['available','occupied','full','maintenance'] as $status): ?>
                                <option value="<?= e($status) ?>" <?= $statusFilter === $status ? 'selected' : '' ?>><?= ucfirst($status) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-2 d-flex gap-2">
                        <button class="btn btn-primary btn-sm flex-grow-1">Filter</button> 
                        <a class="btn btn-outline-secondary btn-sm" href="<?= url('views/admin/rooms/allocation/allocation.php') ?>">Reset</a>
                    </div>
                </form>
            </div>
        </div>

        <!-- Room Inventory Table -->
        <div class="card stat-card shadow-sm border-0 mb-4">
            <div class="card-header bg-white py-3">
                <h6 class="mb-0 fw-bold"><i class="bi bi-door-open me-2 text-info"></i>Room Allocation Density</h6>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Room</th>
                                <th>House</th>
                                <th>Capacity</th>
                                <th>Occupied</th>
                                <th>Available</th>
                                <th>Status</th>
                                <th>Residents</th>
                                <th class="text-end">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (!$rooms): ?>
                                <tr><td colspan="8" class="text-center text-muted py-4">No rooms match the selected filters.</td></tr>
                            <?php else: ?>
                                <?php foreach ($rooms as $room): $roomId = (string) ($room['id'] ?? ''); $occupied = (int) ($room['occupied'] ?? 0); $capacity = (int) ($room['capacity'] ?? 0); $available = max(0, $capacity - $occupied); $status = ($room['status'] ?? '') === 'maintenance' ? 'maintenance' : ($occupied >= $capacity ? 'full' : ($occupied > 0 ? 'occupied' : 'available')); ?>
                                    <tr>
                                        <td><strong>Room <?= e($room['roomNumber'] ?? '-') ?></strong></td>
                                        <td><?= e($houseMap[(string) ($room['houseId'] ?? '')] ?? ($room['houseId'] ?? '-')) ?></td>
                                        <td><?= e((string) $capacity) ?> beds</td>
                                        <td><?= e((string) $occupied) ?></td>
                                        <td><strong class="text-success"><?= e((string) $available) ?></strong></td>
                                        <td><span class="badge bg-<?= $status === 'full' ? 'danger' : ($status === 'maintenance' ? 'secondary' : ($status === 'occupied' ? 'warning' : 'success')) ?>"><?= ucfirst(e($status)) ?></span></td>
                                        <td><?= !empty($occupantsByRoom[$roomId]) ? e((string) count($occupantsByRoom[$roomId])) . ' resident(s)' : '<span class="text-muted">Empty</span>' ?></td>
                                        <td class="text-end">
                                            <a class="btn btn-sm btn-outline-primary" href="<?= url('views/admin/rooms/view/view.php?id=' . urlencode($roomId)) ?>"><i class="bi bi-eye"></i></a>
                                            <a class="btn btn-sm btn-outline-secondary" href="<?= url('views/admin/rooms/edit/edit.php?id=' . urlencode($roomId)) ?>"><i class="bi bi-pencil"></i></a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- Current Occupants Table -->
        <div class="card stat-card shadow-sm border-0">
            <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
                <h6 class="mb-0 fw-bold"><i class="bi bi-people me-2 text-primary"></i>Active Student Room Allocations</h6>
                <span class="badge bg-primary fs-6"><?= e((string) count($occupantRows)) ?> Assigned Students</span>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Student Name</th>
                                <th>Admission No.</th>
                                <th>Room Number</th>
                                <th>Dormitory House</th>
                                <th class="text-end">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (!$occupantRows): ?>
                                <tr><td colspan="5" class="text-center text-muted py-4">No students are currently allocated to dormitory spaces.</td></tr>
                            <?php else: foreach ($occupantRows as $occupant): $student = $occupant['student']; ?>
                                <tr>
                                    <td><strong><?= e(trim(($student['firstName'] ?? '') . ' ' . ($student['lastName'] ?? ''))) ?></strong></td>
                                    <td><span class="font-monospace text-muted"><?= e($student['admissionNo'] ?? '-') ?></span></td>
                                    <td><span class="badge bg-light text-dark border">Room <?= e($occupant['roomNumber']) ?></span></td>
                                    <td><?= e($occupant['houseName']) ?></td>
                                    <td class="text-end">
                                        <form method="POST" class="d-inline" onsubmit="return confirm('Remove this student from room allocation?')">
                                            <input type="hidden" name="action" value="remove">
                                            <input type="hidden" name="studentId" value="<?= e((string) ($student['id'] ?? '')) ?>">
                                            <button class="btn btn-sm btn-outline-danger">
                                                <i class="bi bi-person-dash me-1"></i> Deallocate
                                            </button>
                                        </form>
                                    </td>
                                </tr>
                            <?php endforeach; endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

    </div>
</div>
<?php require APP_ROOT . '/app/views/components/footer/footer.php'; ?>