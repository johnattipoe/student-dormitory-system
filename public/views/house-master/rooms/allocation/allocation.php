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
$allowedRoles = [ROLE_ADMIN, ROLE_HOUSE_MASTER, ROLE_HOUSE_MISTRESS];
require APP_ROOT . '/app/middleware/RoleMiddleware/RoleMiddleware.php';
use App\Services\HouseService;
use App\Services\RoomService;
use App\Services\RoomAllocationService;
use App\Services\StudentService;

$pageTitle = 'Room Allocation';
$isAdmin = current_role() === ROLE_ADMIN;
$portalPrefix = $isAdmin ? 'admin' : 'house-master';
$roomsIndexRoute = 'views/' . $portalPrefix . '/rooms/index/index.php';
$allocationRoute = 'views/' . $portalPrefix . '/rooms/allocation/allocation.php';
$houseId = $isAdmin ? '' : (string) (current_user()['houseId'] ?? current_user()['house_id'] ?? '');
$rooms = RoomService::all($isAdmin ? null : $houseId);
$houses = $isAdmin ? HouseService::all() : ($houseId !== '' && ($house = HouseService::find($houseId)) ? [$house] : []);
$students = StudentService::all($isAdmin ? null : $houseId);
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
            'roomId' => (string) ($room['id'] ?? $roomId),
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
    if (!$isAdmin) {
        $student = StudentService::find($studentId);
        $room = RoomService::find($roomId);
        if (!$student || !$room || (string) ($student['houseId'] ?? '') !== $houseId || (string) ($room['houseId'] ?? '') !== $houseId) {
            flash('error', 'The student and room must belong to your assigned house.');
            redirect(url('views/house-master/rooms/allocation/allocation.php'));
        }
    }
    if ($action === 'transfer') {
        $result = $allocationService->transfer($studentId, $roomId, current_user_id());
    } else {
        $result = $allocationService->allocate([
            'studentId' => $studentId,
            'roomId' => $roomId,
            'allocatedBy' => current_user_id(),
        ]);
    }
    flash(!empty($result['success']) ? 'success' : 'error', $result['message'] ?? 'Allocation action failed.');
    redirect(url($allocationRoute));
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
    ['icon' => 'bi-speedometer2', 'label' => 'Dashboard', 'href' => url('views/' . $portalPrefix . '/dashboard/index.php')],
    ['icon' => 'bi-door-closed', 'label' => 'Rooms', 'href' => url($roomsIndexRoute)],
    ['icon' => 'bi-diagram-3', 'label' => 'Allocation', 'href' => url($allocationRoute), 'active' => true],
];
require APP_ROOT . '/app/views/components/header/header.php';
require APP_ROOT . '/app/views/components/sidebar/sidebar.php';
?>
<div class="main-content">
    <?php require APP_ROOT . '/app/views/components/navbar/navbar.php'; ?>
    <div class="content-wrapper">
        <?php require APP_ROOT . '/app/views/components/alerts/alerts.php'; ?>

        <!-- Hero Header -->
        <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-3">
            <div>
                <h4 class="mb-1 fw-bold text-dark"><i class="bi bi-diagram-3-fill text-primary me-2"></i>Room Allocation &amp; Transfers</h4>
                <p class="text-muted mb-0">Manage resident bed assignments, transfers, and capacity utilization</p>
            </div>
            <div class="d-flex gap-2 flex-wrap">
                <a class="btn btn-outline-success btn-sm" href="<?= url($allocationRoute . '?download=csv&search=' . urlencode($search) . '&status=' . urlencode($statusFilter) . '&houseId=' . urlencode($houseFilter)) ?>">
                    <i class="bi bi-filetype-csv me-1"></i>Export CSV
                </a>
                <a class="btn btn-outline-secondary btn-sm" href="<?= url($roomsIndexRoute) ?>">
                    <i class="bi bi-arrow-left me-1"></i>Back to Rooms
                </a>
            </div>
        </div>

        <!-- KPI Stats Cards -->
        <div class="row g-3 mb-4">
            <div class="col-md-3">
                <div class="card stat-card h-100 p-3 border-start border-4 border-primary shadow-sm">
                    <div class="d-flex justify-content-between align-items-start">
                        <div>
                            <span class="text-muted small text-uppercase fw-semibold">Total Capacity</span>
                            <h3 class="fw-bold my-1 text-primary"><?= e((string) $totalCapacity) ?></h3>
                            <span class="small text-muted">Total spaces</span>
                        </div>
                        <div class="rounded-3 bg-primary bg-opacity-10 p-2 text-primary"><i class="bi bi-grid-1x2 fs-4"></i></div>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card stat-card h-100 p-3 border-start border-4 border-warning shadow-sm">
                    <div class="d-flex justify-content-between align-items-start">
                        <div>
                            <span class="text-muted small text-uppercase fw-semibold">Occupied</span>
                            <h3 class="fw-bold my-1 text-warning"><?= e((string) $totalOccupied) ?></h3>
                            <span class="small text-muted">Allocated spaces</span>
                        </div>
                        <div class="rounded-3 bg-warning bg-opacity-10 p-2 text-warning"><i class="bi bi-person-check fs-4"></i></div>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card stat-card h-100 p-3 border-start border-4 border-success shadow-sm">
                    <div class="d-flex justify-content-between align-items-start">
                        <div>
                            <span class="text-muted small text-uppercase fw-semibold">Available</span>
                            <h3 class="fw-bold my-1 text-success"><?= e((string) $availableSpaces) ?></h3>
                            <span class="small text-muted">Open vacancies</span>
                        </div>
                        <div class="rounded-3 bg-success bg-opacity-10 p-2 text-success"><i class="bi bi-circle-half fs-4"></i></div>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card stat-card h-100 p-3 border-start border-4 border-danger shadow-sm">
                    <div class="d-flex justify-content-between align-items-start">
                        <div>
                            <span class="text-muted small text-uppercase fw-semibold">Full Rooms</span>
                            <h3 class="fw-bold my-1 text-danger"><?= e((string) $fullRooms) ?></h3>
                            <span class="small text-muted">At 100% capacity</span>
                        </div>
                        <div class="rounded-3 bg-danger bg-opacity-10 p-2 text-danger"><i class="bi bi-exclamation-octagon fs-4"></i></div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Allocation Form Card -->
        <div class="card stat-card shadow-sm border-0 mb-4">
            <div class="card-header bg-white py-3">
                <h6 class="mb-0 fw-bold"><i class="bi bi-person-plus me-2 text-primary"></i>Assign or Transfer Student</h6>
            </div>
            <div class="card-body p-4">
                <form method="POST" action="<?= url($allocationRoute) ?>" class="row g-3 align-items-end">
                    <div class="col-md-4">
                        <label class="form-label fw-semibold">Student <span class="text-danger">*</span></label>
                        <select name="studentId" class="form-select" required>
                            <option value="">Select student...</option>
                            <?php foreach ($students as $student): ?>
                                <option value="<?= e((string) ($student['id'] ?? '')) ?>">
                                    <?= e(trim(($student['firstName'] ?? '') . ' ' . ($student['lastName'] ?? ''))) ?> (<?= e($student['admissionNo'] ?? '') ?>)<?= !empty($student['roomId']) ? ' — Assigned' : '' ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label fw-semibold">Destination Room <span class="text-danger">*</span></label>
                        <select name="roomId" class="form-select" required>
                            <option value="">Select destination room...</option>
                            <?php foreach ($rooms as $room): 
                                $occupied = (int) ($room['occupied'] ?? 0); 
                                $capacity = (int) ($room['capacity'] ?? 0); 
                            ?>
                                <option value="<?= e((string) ($room['id'] ?? '')) ?>" <?= (($room['status'] ?? '') === 'maintenance' || $occupied >= $capacity) ? 'disabled' : '' ?>>
                                    Room <?= e($room['roomNumber'] ?? '-') ?> — <?= e($houseMap[(string) ($room['houseId'] ?? '')] ?? '-') ?> (<?= $occupied ?>/<?= $capacity ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label fw-semibold">Action</label>
                        <select name="action" class="form-select">
                            <option value="allocate">New Allocation</option>
                            <option value="transfer">Transfer Student</option>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <button type="submit" class="btn btn-primary w-100"><i class="bi bi-check2-circle me-1"></i>Save</button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Filter Bar -->
        <div class="card stat-card shadow-sm mb-4 border-0">
            <div class="card-body p-3">
                <form method="GET" class="row g-2 align-items-end">
                    <div class="col-md-4">
                        <label class="form-label small fw-semibold">Search</label>
                        <input name="search" class="form-control form-control-sm" placeholder="Search room or house" value="<?= e($search) ?>">
                    </div>
                    <?php if ($isAdmin): ?>
                    <div class="col-md-3">
                        <label class="form-label small fw-semibold">House</label>
                        <select name="houseId" class="form-select form-select-sm">
                            <option value="">All houses</option>
                            <?php foreach ($houses as $house): ?>
                                <option value="<?= e((string) ($house['id'] ?? '')) ?>" <?= $houseFilter === ($house['id'] ?? '') ? 'selected' : '' ?>><?= e($house['name'] ?? '') ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <?php endif; ?>
                    <div class="col-md-3">
                        <label class="form-label small fw-semibold">Status</label>
                        <select name="status" class="form-select form-select-sm">
                            <option value="">All statuses</option>
                            <?php foreach (['available', 'occupied', 'full', 'maintenance'] as $status): ?>
                                <option value="<?= e($status) ?>" <?= $statusFilter === $status ? 'selected' : '' ?>><?= ucfirst($status) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-2 d-flex gap-2">
                        <button class="btn btn-primary btn-sm flex-fill"><i class="bi bi-funnel me-1"></i>Filter</button>
                        <a class="btn btn-outline-secondary btn-sm" href="<?= url($allocationRoute) ?>">Reset</a>
                    </div>
                </form>
            </div>
        </div>

        <!-- Rooms Table -->
        <div class="card stat-card shadow-sm border-0 mb-4">
            <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
                <h6 class="mb-0 fw-bold"><i class="bi bi-door-closed me-2"></i>Room Capacity &amp; Status</h6>
                <small class="text-muted"><?= count($rooms) ?> room(s)</small>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0 data-table w-100">
                        <thead class="table-light">
                            <tr>
                                <th>Room</th>
                                <th>House</th>
                                <th>Capacity</th>
                                <th>Occupied</th>
                                <th>Available</th>
                                <th>Status</th>
                                <th>Occupants</th>
                                <th class="text-end">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (!$rooms): ?>
                                <tr><td colspan="8" class="text-center text-muted py-5"><i class="bi bi-inbox fs-3 d-block mb-2"></i>No rooms match the selected filters.</td></tr>
                            <?php else: ?>
                                <?php foreach ($rooms as $room): 
                                    $roomId = (string) ($room['id'] ?? ''); 
                                    $occupied = (int) ($room['occupied'] ?? 0); 
                                    $capacity = (int) ($room['capacity'] ?? 0); 
                                    $available = max(0, $capacity - $occupied); 
                                    $status = ($room['status'] ?? '') === 'maintenance' ? 'maintenance' : ($occupied >= $capacity ? 'full' : ($occupied > 0 ? 'occupied' : 'available')); 
                                    $statusBadge = match($status) {
                                        'full' => 'bg-danger text-white',
                                        'maintenance' => 'bg-secondary text-white',
                                        'occupied' => 'bg-warning text-dark',
                                        default => 'bg-success text-white'
                                    };
                                ?>
                                    <tr>
                                        <td><strong class="text-dark">Room <?= e($room['roomNumber'] ?? '-') ?></strong></td>
                                        <td><?= e($houseMap[(string) ($room['houseId'] ?? '')] ?? ($room['houseId'] ?? '-')) ?></td>
                                        <td><?= e((string) $capacity) ?></td>
                                        <td><strong><?= e((string) $occupied) ?></strong></td>
                                        <td><span class="text-success fw-bold"><?= e((string) $available) ?></span></td>
                                        <td><span class="badge <?= $statusBadge ?>"><?= ucfirst($status) ?></span></td>
                                        <td><?= !empty($occupantsByRoom[$roomId]) ? count($occupantsByRoom[$roomId]) . ' student(s)' : '<span class="text-muted small">None</span>' ?></td>
                                        <td class="text-end text-nowrap">
                                            <a class="btn btn-sm btn-outline-primary" href="<?= url('views/' . $portalPrefix . '/rooms/view/view.php?id=' . urlencode($roomId)) ?>"><i class="bi bi-eye me-1"></i>View</a>
                                            <a class="btn btn-sm btn-outline-secondary ms-1" href="<?= url('views/' . $portalPrefix . '/rooms/edit/edit.php?id=' . urlencode($roomId)) ?>"><i class="bi bi-pencil me-1"></i>Edit</a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- Current Occupants Card -->
        <div class="card stat-card shadow-sm border-0">
            <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
                <h6 class="mb-0 fw-bold"><i class="bi bi-people me-2 text-primary"></i>Current Resident Occupants</h6>
                <span class="badge bg-primary"><?= count($occupantRows) ?> assigned</span>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0 data-table w-100">
                        <thead class="table-light">
                            <tr>
                                <th>Student</th>
                                <th>Admission No.</th>
                                <th>Room</th>
                                <th>House</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (!$occupantRows): ?>
                                <tr><td colspan="4" class="text-center text-muted py-4">No students are currently assigned to rooms.</td></tr>
                            <?php else: ?>
                                <?php foreach ($occupantRows as $occupant): $student = $occupant['student']; ?>
                                    <tr>
                                        <td><strong class="text-dark"><?= e(trim(($student['firstName'] ?? '') . ' ' . ($student['lastName'] ?? ''))) ?></strong></td>
                                        <td class="font-monospace text-muted"><?= e($student['admissionNo'] ?? '-') ?></td>
                                        <td><span class="badge bg-light text-dark border">Room <?= e($occupant['roomNumber']) ?></span></td>
                                        <td><?= e($occupant['houseName']) ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>
<?php require APP_ROOT . '/app/views/components/footer/footer.php'; ?>