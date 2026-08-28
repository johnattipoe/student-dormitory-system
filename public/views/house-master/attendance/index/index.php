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

use App\Services\AttendanceService;
use App\Services\BedService;
use App\Services\FirebaseService;
use App\Services\HouseService;
use App\Services\RoomService;
use App\Services\StudentService;
use App\Services\UserService;

$user = current_user() ?? [];
$userId = (string) ($user['uid'] ?? $user['id'] ?? '');
$assignedHouseId = (string) ($user['houseId'] ?? $user['house_id'] ?? '');
$assignedHouses = [];
foreach (HouseService::all() as $house) {
    $houseId = (string) ($house['id'] ?? $house['houseId'] ?? '');
    if ($houseId !== '' && ($houseId === $assignedHouseId
        || (string) ($house['houseMasterId'] ?? '') === $userId
        || (string) ($house['houseMistressId'] ?? '') === $userId)) {
        $assignedHouses[$houseId] = $house;
    }
}

$studentHouseId = static fn(array $student): string => (string) (
    $student['houseId']
    ?? $student['house_id']
    ?? $student['assignedHouseId']
    ?? $student['assigned_house_id']
    ?? ''
);

$studentRecordId = static fn(array $student): string => (string) (
    $student['id']
    ?? $student['uid']
    ?? $student['studentId']
    ?? ''
);

$studentsForHouse = static function (string $houseId) use ($assignedHouses, $studentHouseId, $studentRecordId): array {
    if ($houseId === '' || !array_key_exists($houseId, $assignedHouses)) {
        return [];
    }

    $allStudents = StudentService::all();
    $rooms = RoomService::all($houseId);
    $roomIds = array_values(array_filter(array_map(
        static fn(array $room): string => (string) ($room['id'] ?? $room['roomId'] ?? ''),
        $rooms
    )));
    $roomIdSet = array_fill_keys($roomIds, true);

    $assignedStudentIds = [];
    foreach (BedService::all() as $bed) {
        $bedRoomId = (string) ($bed['roomId'] ?? '');
        $bedStudentId = (string) ($bed['studentId'] ?? '');
        if ($bedStudentId !== '' && isset($roomIdSet[$bedRoomId])) {
            $assignedStudentIds[$bedStudentId] = true;
        }
    }

    foreach (FirebaseService::getInstance()->getCollection(COL_ROOM_ALLOCATIONS, [], 1000) as $allocation) {
        $allocationStudentId = (string) ($allocation['studentId'] ?? '');
        $allocationHouseId = (string) ($allocation['houseId'] ?? $allocation['house_id'] ?? '');
        $allocationRoomId = (string) ($allocation['roomId'] ?? '');
        $allocationStatus = strtolower((string) ($allocation['status'] ?? 'active'));

        if ($allocationStudentId === '' || $allocationStatus === 'inactive' || $allocationStatus === 'cancelled') {
            continue;
        }

        if ($allocationHouseId === $houseId || ($allocationRoomId !== '' && isset($roomIdSet[$allocationRoomId]))) {
            $assignedStudentIds[$allocationStudentId] = true;
        }
    }

    return array_values(array_filter($allStudents, static function (array $student) use ($houseId, $assignedStudentIds, $studentHouseId, $studentRecordId): bool {
        $id = $studentRecordId($student);
        if ($id !== '' && isset($assignedStudentIds[$id])) {
            return true;
        }
        return $studentHouseId($student) === $houseId;
    }));
};

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $selectedHouseId = sanitize($_POST['houseId'] ?? $assignedHouseId);
    $studentId = sanitize($_POST['studentId'] ?? '');
    $eligibleStudents = $studentsForHouse($selectedHouseId);
    $studentIds = array_map($studentRecordId, $eligibleStudents);

    if (!array_key_exists($selectedHouseId, $assignedHouses)) {
        flash('error', 'Please select one of your assigned houses.');
        redirect(base_url('index.php?route=/views/house-master/attendance/index/index.php'));
    }

    if (!in_array($studentId, $studentIds, true)) {
        flash('error', 'The selected student is not assigned to that house.');
        redirect(base_url('index.php?route=/views/house-master/attendance/index/index.php&houseId=' . urlencode($selectedHouseId)));
    }

    $data = [
        'studentId' => $studentId,
        'status' => sanitize($_POST['status'] ?? 'present'),
        'date' => sanitize($_POST['date'] ?? date('Y-m-d')),
        'houseId' => $selectedHouseId,
        'markedBy' => $user['uid'] ?? $user['id'] ?? 'house-master',
    ];

    $result = AttendanceService::mark($data);
    flash($result['success'] ? 'success' : 'error', $result['message'] ?? 'Attendance saved.');
    redirect(base_url('index.php?route=/views/house-master/attendance/index/index.php'));
}

$pageTitle = 'House Attendance';
$date = sanitize($_GET['date'] ?? date('Y-m-d'));
$searchQuery = sanitize($_GET['search'] ?? '');
$statusFilter = sanitize($_GET['status'] ?? '');
$selectedHouseId = sanitize($_GET['houseId'] ?? $assignedHouseId);
if (!array_key_exists($selectedHouseId, $assignedHouses)) {
    $selectedHouseId = array_key_first($assignedHouses) ?? '';
}

$students = $studentsForHouse($selectedHouseId);
$beds = BedService::all();
$bedMap = [];
foreach ($beds as $bed) {
    $bedMap[(string) ($bed['studentId'] ?? '')] = (string) ($bed['bedNumber'] ?? '—');
}
$attendance = AttendanceService::forDate($date, $selectedHouseId !== '' ? $selectedHouseId : null);
$markedByNames = [];
foreach ((new UserService())->all() as $user) {
    $userName = trim((string) ($user['name'] ?? ''));
    if ($userName === '') {
        $userName = trim(($user['firstName'] ?? '') . ' ' . ($user['lastName'] ?? ''));
    }
    if ($userName !== '') {
        foreach ([$user['id'] ?? null, $user['uid'] ?? null] as $userId) {
            if ($userId !== null && (string) $userId !== '') {
                $markedByNames[(string) $userId] = $userName;
            }
        }
    }
}

if (!empty($searchQuery)) {
    $attendance = array_filter($attendance, function($record) use ($searchQuery, $students, $studentRecordId) {
        $student = current(array_filter($students, fn($s) => $studentRecordId($s) === ((string) ($record['studentId'] ?? ''))));
        $name = ($student['firstName'] ?? '') . ' ' . ($student['lastName'] ?? '');
        $admNo = $student['admissionNo'] ?? '';
        return stripos($name, $searchQuery) !== false || stripos($admNo, $searchQuery) !== false;
    });
}

if (!empty($statusFilter)) {
    $attendance = array_filter($attendance, fn($record) => ($record['status'] ?? 'present') === $statusFilter);
}

$summary = AttendanceService::summary($date, $selectedHouseId !== '' ? $selectedHouseId : null);
$navItems = [
    ['icon' => 'bi-speedometer2', 'label' => 'Dashboard', 'href' => url('views/house-master/dashboard/index.php')],
    ['icon' => 'bi-mortarboard', 'label' => 'Students', 'href' => url('views/house-master/students/index/index.php')],
    ['icon' => 'bi-calendar-check', 'label' => 'Attendance', 'href' => url('views/house-master/attendance/index/index.php'), 'active' => true],
    ['icon' => 'bi-door-closed', 'label' => 'Rooms', 'href' => url('views/house-master/rooms/index/index.php')],
    ['icon' => 'bi-people', 'label' => 'Visitors', 'href' => url('views/house-master/visitors/index/index.php')],
    ['icon' => 'bi-flag', 'label' => 'Incidents', 'href' => url('views/house-master/incidents/index/index.php')],
    ['icon' => 'bi-file-earmark-text', 'label' => 'Reports', 'href' => url('views/house-master/reports/index/index.php')],
    ['icon' => 'bi-bell', 'label' => 'Notifications', 'href' => url('views/house-master/notifications/index/index.php')],
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
                    <i class="bi bi-calendar-check-fill text-success me-2"></i>House Attendance Roll Call
                </h4>
                <p class="text-muted mb-0">Track daily night roll calls and resident accountability for your assigned house</p>
            </div>
            <div class="d-flex gap-2 flex-wrap">
                <a href="<?= url('views/house-master/attendance/mark-attendance/mark-attendance.php') ?>" class="btn btn-success btn-sm">
                    <i class="bi bi-check2-square me-1"></i> Bulk Roll Call
                </a>
                <a href="<?= url('views/house-master/attendance/history/history.php') ?>" class="btn btn-outline-secondary btn-sm">
                    <i class="bi bi-clock-history me-1"></i> History
                </a>
                <a href="<?= url('views/house-master/reports/export/export.php?type=attendance&date=' . urlencode($date)) ?>" class="btn btn-outline-success btn-sm">
                    <i class="bi bi-filetype-csv me-1"></i> Export CSV
                </a>
            </div>
        </div>

        <!-- KPI Stats Cards -->
        <div class="row g-3 mb-4">
            <div class="col-sm-6 col-lg-4">
                <div class="card stat-card h-100 p-3 border-start border-4 border-success shadow-sm">
                    <div class="d-flex justify-content-between align-items-start">
                        <div>
                            <span class="text-muted small text-uppercase fw-semibold">Present Tonight</span>
                            <h3 class="fw-bold my-1 text-success"><?= e((string) ($summary['present'] ?? 0)) ?></h3>
                            <span class="small text-muted">Confirmed in dorm</span>
                        </div>
                        <div class="rounded-3 bg-success bg-opacity-10 p-2 text-success"><i class="bi bi-person-check fs-4"></i></div>
                    </div>
                </div>
            </div>

            <div class="col-sm-6 col-lg-4">
                <div class="card stat-card h-100 p-3 border-start border-4 border-danger shadow-sm">
                    <div class="d-flex justify-content-between align-items-start">
                        <div>
                            <span class="text-muted small text-uppercase fw-semibold">Absent</span>
                            <h3 class="fw-bold my-1 text-danger"><?= e((string) ($summary['absent'] ?? 0)) ?></h3>
                            <span class="small text-muted">Unaccounted residents</span>
                        </div>
                        <div class="rounded-3 bg-danger bg-opacity-10 p-2 text-danger"><i class="bi bi-person-x fs-4"></i></div>
                    </div>
                </div>
            </div>

            <div class="col-sm-6 col-lg-4">
                <div class="card stat-card h-100 p-3 border-start border-4 border-warning shadow-sm">
                    <div class="d-flex justify-content-between align-items-start">
                        <div>
                            <span class="text-muted small text-uppercase fw-semibold">Late / Excused</span>
                            <h3 class="fw-bold my-1 text-warning"><?= e((string) (($summary['late'] ?? 0) + ($summary['excused'] ?? 0))) ?></h3>
                            <span class="small text-muted"><?= e((string) ($summary['late'] ?? 0)) ?> late &bull; <?= e((string) ($summary['excused'] ?? 0)) ?> excused</span>
                        </div>
                        <div class="rounded-3 bg-warning bg-opacity-10 p-2 text-warning"><i class="bi bi-clock-history fs-4"></i></div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Quick Mark Roll Call Form -->
        <div class="card stat-card shadow-sm border-0 mb-4">
            <div class="card-header bg-white py-3">
                <h6 class="mb-0 fw-bold"><i class="bi bi-pencil-square me-2 text-success"></i>Quick Individual Roll Call</h6>
            </div>
            <div class="card-body p-4">
                <form method="POST" action="<?= url('views/house-master/attendance/index/index.php') ?>" class="row g-3 align-items-end">
                    <div class="col-md-3">
                        <label class="form-label fw-semibold">Dormitory House</label>
                        <select name="houseId" class="form-select" required onchange="window.location.href='<?= url('views/house-master/attendance/index/index.php') ?>?houseId=' + encodeURIComponent(this.value)">
                            <option value="">-- Choose House --</option>
                            <?php foreach ($assignedHouses as $houseId => $house): ?>
                                <option value="<?= e($houseId) ?>" <?= $selectedHouseId === $houseId ? 'selected' : '' ?>><?= e($house['name'] ?? $houseId) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label fw-semibold">Resident Student</label>
                        <select name="studentId" class="form-select select2" required <?= empty($students) ? 'disabled' : '' ?>>
                            <option value=""><?= empty($students) ? 'No students in this house' : 'Select student' ?></option>
                            <?php foreach ($students as $student): ?>
                                <?php
                                $optionStudentId = $studentRecordId($student);
                                $studentName = trim(($student['firstName'] ?? '') . ' ' . ($student['lastName'] ?? ''));
                                $studentLabel = trim($studentName . ' (' . ($student['admissionNo'] ?? $student['studentId'] ?? $optionStudentId) . ')');
                                ?>
                                <?php if ($optionStudentId !== ''): ?>
                                    <option value="<?= e($optionStudentId) ?>"><?= e($studentLabel) ?></option>
                                <?php endif; ?>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label fw-semibold">Roll Call Date</label>
                        <input type="date" name="date" class="form-control" value="<?= e($date) ?>" required>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label fw-semibold">Status</label>
                        <select name="status" class="form-select">
                            <option value="present">Present</option>
                            <option value="absent">Absent</option>
                            <option value="excused">Excused</option>
                            <option value="late">Late</option>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <button class="btn btn-primary w-100"><i class="bi bi-check-lg me-1"></i> Save</button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Filter Search Bar -->
        <div class="card stat-card shadow-sm mb-4 border-0">
            <div class="card-body p-3">
                <form method="GET" class="row g-2 align-items-center">
                    <input type="hidden" name="route" value="/views/house-master/attendance/index/index.php">
                    <input type="hidden" name="houseId" value="<?= e($selectedHouseId) ?>">
                    
                    <div class="col-md-3">
                        <input type="date" name="date" class="form-control form-control-sm" value="<?= e($date) ?>">
                    </div>

                    <div class="col-md-4">
                        <input type="text" name="search" class="form-control form-control-sm" placeholder="Search by student name or admission no..." value="<?= e($searchQuery) ?>">
                    </div>

                    <div class="col-md-3">
                        <select name="status" class="form-select form-select-sm">
                            <option value="">All Statuses</option>
                            <option value="present" <?= $statusFilter === 'present' ? 'selected' : '' ?>>Present</option>
                            <option value="absent" <?= $statusFilter === 'absent' ? 'selected' : '' ?>>Absent</option>
                            <option value="late" <?= $statusFilter === 'late' ? 'selected' : '' ?>>Late</option>
                            <option value="excused" <?= $statusFilter === 'excused' ? 'selected' : '' ?>>Excused</option>
                        </select>
                    </div>

                    <div class="col-md-2 d-flex gap-2">
                        <button type="submit" class="btn btn-primary btn-sm flex-grow-1">Filter</button>
                        <a href="<?= url('views/house-master/attendance/index/index.php') ?>" class="btn btn-outline-secondary btn-sm">Reset</a>
                    </div>
                </form>
            </div>
        </div>

        <!-- Attendance Table -->
        <div class="card stat-card shadow-sm border-0">
            <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
                <h6 class="mb-0 fw-bold"><i class="bi bi-clock-history me-2 text-success"></i>Roll Call Records for <?= e($date) ?></h6>
                <span class="small text-muted"><?= count($attendance) ?> record(s)</span>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Roll Call Date</th>
                                <th>Resident Student</th>
                                <th>Bed Space</th>
                                <th>Attendance Status</th>
                                <th>Marked By</th>
                                <th class="text-end">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($attendance)): ?>
                                <tr>
                                    <td colspan="6" class="text-center text-muted py-4">No attendance records submitted for this date matching your filter.</td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($attendance as $record): ?>
                                    <?php
                                    $student = null;
                                    foreach ($students as $s) {
                                        if ($studentRecordId($s) === (string) ($record['studentId'] ?? '')) {
                                            $student = $s;
                                            break;
                                        }
                                    }
                                    $st = strtolower((string)($record['status'] ?? 'present'));
                                    $badge = match($st) {
                                        'present' => 'bg-success',
                                        'absent' => 'bg-danger',
                                        'late' => 'bg-warning text-dark',
                                        'excused' => 'bg-info',
                                        default => 'bg-secondary',
                                    };
                                    $rId = (string)($record['id'] ?? '');
                                    ?>
                                    <tr>
                                        <td><?= e($record['date'] ?? '-') ?></td>
                                        <td>
                                            <strong class="text-dark d-block"><?= e(($student['firstName'] ?? '') . ' ' . ($student['lastName'] ?? '')) ?: e($record['studentId'] ?? '-') ?></strong>
                                            <small class="text-muted font-monospace">Adm: <?= e($student['admissionNo'] ?? '—') ?></small>
                                        </td>
                                        <td><span class="badge bg-light text-dark border">Bed <?= e($bedMap[(string) ($record['studentId'] ?? '')] ?? '—') ?></span></td>
                                        <td><span class="badge <?= $badge ?>"><?= ucfirst(e($st)) ?></span></td>
                                        <td><small class="text-muted"><i class="bi bi-person me-1"></i><?= e($markedByNames[(string) ($record['markedBy'] ?? '')] ?? ($record['markedBy'] ?? '—')) ?></small></td>
                                        <td class="text-end text-nowrap">
                                            <a class="btn btn-sm btn-outline-primary" href="<?= url('views/house-master/attendance/view/view.php?id=' . urlencode($rId)) ?>" title="View"><i class="bi bi-eye"></i></a>
                                            <a class="btn btn-sm btn-outline-secondary" href="<?= url('views/house-master/attendance/edit/edit.php?id=' . urlencode($rId)) ?>" title="Edit"><i class="bi bi-pencil"></i></a>
                                            <a class="btn btn-sm btn-outline-danger" href="<?= url('views/house-master/attendance/delete/delete.php?id=' . urlencode($rId)) ?>" title="Delete"><i class="bi bi-trash"></i></a>
                                        </td>
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
