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
require APP_ROOT . '/app/middleware/RoleMiddleware.php';

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

        if ($allocationStudentId !== ''
            && $allocationStatus === 'active'
            && ($allocationHouseId === $houseId || isset($roomIdSet[$allocationRoomId]))) {
            $assignedStudentIds[$allocationStudentId] = true;
        }
    }

    $studentsWithRoomAssignment = array_values(array_filter($allStudents, static function (array $student) use ($studentRecordId, $roomIdSet, $assignedStudentIds): bool {
        $studentId = $studentRecordId($student);
        $studentRoomId = (string) ($student['roomId'] ?? '');

        return ($studentId !== '' && isset($assignedStudentIds[$studentId]))
            || ($studentRoomId !== '' && isset($roomIdSet[$studentRoomId]));
    }));

    $students = !empty($studentsWithRoomAssignment)
        ? $studentsWithRoomAssignment
        : array_values(array_filter(
            $allStudents,
            static fn(array $student): bool => $studentHouseId($student) === $houseId
        ));

    usort($students, static fn(array $first, array $second): int => strcasecmp(
        trim(($first['firstName'] ?? '') . ' ' . ($first['lastName'] ?? '')),
        trim(($second['firstName'] ?? '') . ' ' . ($second['lastName'] ?? ''))
    ));

    return $students;
};

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $selectedHouseId = sanitize($_POST['houseId'] ?? '');
    $studentId = sanitize($_POST['studentId'] ?? '');
    $allowedHouseIds = array_keys($assignedHouses);
    $selectedStudents = in_array($selectedHouseId, $allowedHouseIds, true) ? $studentsForHouse($selectedHouseId) : [];
    $studentIds = array_map($studentRecordId, $selectedStudents);

    if (!in_array($selectedHouseId, $allowedHouseIds, true)) {
        flash('error', 'Please select one of your assigned houses.');
        redirect(base_url('index.php?route=/views/house-master/attendance/index.php'));
    }

    if (!in_array($studentId, $studentIds, true)) {
        flash('error', 'The selected student is not assigned to that house.');
        redirect(base_url('index.php?route=/views/house-master/attendance/index.php&houseId=' . urlencode($selectedHouseId)));
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
    redirect(base_url('index.php?route=/views/house-master/attendance/index.php'));
}

$pageTitle = 'House Master Attendance';
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

// Apply filters
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
    ['icon' => 'bi-mortarboard', 'label' => 'Students', 'href' => url('views/house-master/students/index.php')],
    ['icon' => 'bi-calendar-check', 'label' => 'Attendance', 'href' => url('views/house-master/attendance/index.php'), 'active' => true],
    ['icon' => 'bi-door-closed', 'label' => 'Rooms', 'href' => url('views/house-master/rooms/index.php')],
    ['icon' => 'bi-people', 'label' => 'Visitors', 'href' => url('views/house-master/visitors/index.php')],
    ['icon' => 'bi-flag', 'label' => 'Incidents', 'href' => url('views/house-master/incidents/index.php')],
    ['icon' => 'bi-file-earmark-text', 'label' => 'Reports', 'href' => url('views/house-master/reports/index.php')],
    ['icon' => 'bi-bell', 'label' => 'Notifications', 'href' => url('views/house-master/notifications/index.php')],
];

require APP_ROOT . '/app/views/components/header.php';
require APP_ROOT . '/app/views/components/sidebar.php';
?>
<div class="main-content">
    <?php require APP_ROOT . '/app/views/components/navbar.php'; ?>
    <?php require APP_ROOT . '/app/views/components/alerts.php'; ?>
    <div class="content-wrapper">
        <div class="mb-3">
            <div class="d-flex justify-content-between align-items-center mb-2">
                <div>
                    <h5 class="mb-0">Attendance Overview</h5>
                    <small class="text-muted">Record and review daily attendance for your assigned house.</small>
                </div>
                <div class="d-flex gap-2">
                    <a href="<?= url('views/house-master/attendance/mark-attendance.php') ?>" class="btn btn-success btn-sm">
                        <i class="bi bi-check2-square"></i> Bulk Mark
                    </a>
                    <a href="<?= url('views/house-master/attendance/history.php') ?>" class="btn btn-outline-secondary btn-sm">History</a>
                    <a href="<?= url('views/house-master/reports/export.php?type=attendance&date=' . urlencode($date)) ?>" class="btn btn-outline-success btn-sm"><i class="bi bi-filetype-csv"></i> CSV</a>
                </div>
            </div>
        </div>

        <!-- Advanced Filters -->
        <div class="card stat-card p-3 mb-3">
            <form method="GET" class="row g-3">
                <input type="hidden" name="route" value="/views/house-master/attendance/index.php">
                
                <div class="col-md-3">
                    <label class="form-label small">Date</label>
                    <input type="date" name="date" class="form-control form-control-sm" value="<?= e($date) ?>">
                </div>

                <div class="col-md-4">
                    <label class="form-label small">Search Student</label>
                    <input type="text" name="search" class="form-control form-control-sm" placeholder="Name or admission no." value="<?= e($searchQuery) ?>">
                </div>

                <div class="col-md-3">
                    <label class="form-label small">Status</label>
                    <select name="status" class="form-select form-select-sm">
                        <option value="">All Statuses</option>
                        <option value="present" <?= $statusFilter === 'present' ? 'selected' : '' ?>>Present</option>
                        <option value="absent" <?= $statusFilter === 'absent' ? 'selected' : '' ?>>Absent</option>
                        <option value="late" <?= $statusFilter === 'late' ? 'selected' : '' ?>>Late</option>
                        <option value="excused" <?= $statusFilter === 'excused' ? 'selected' : '' ?>>Excused</option>
                    </select>
                </div>

                <div class="col-md-2 d-flex align-items-end gap-2">
                    <button type="submit" class="btn btn-primary btn-sm w-100">Filter</button>
                    <a href="<?= url('views/house-master/attendance/index.php') ?>" class="btn btn-outline-secondary btn-sm">Reset</a>
                </div>
            </form>
        </div>

        <br>

        <div class="row g-3 mb-4">
            <div class="col-md-4">
                <div class="card stat-card p-3 text-center">
                    <div class="text-muted small">Present</div>
                    <div class="fs-2 fw-bold"><?= e($summary['present'] ?? 0) ?></div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card stat-card p-3 text-center">
                    <div class="text-muted small">Absent</div>
                    <div class="fs-2 fw-bold"><?= e($summary['absent'] ?? 0) ?></div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card stat-card p-3 text-center">
                    <div class="text-muted small">Late</div>
                    <div class="fs-2 fw-bold"><?= e($summary['late'] ?? 0) ?></div>
                </div>
            </div>
        </div>

        <div class="card stat-card p-4 mb-4">
            <h6 class="mb-3">Quick Mark Attendance</h6>
            <form method="POST" action="<?= url('views/house-master/attendance/index.php') ?>" class="row g-3">
                <div class="col-md-3">
                    <label class="form-label">House</label>
                    <select name="houseId" class="form-select" required onchange="window.location.href='<?= url('views/house-master/attendance/index.php') ?>?houseId=' + encodeURIComponent(this.value)">
                        <option value="">Select house</option>
                        <?php foreach ($assignedHouses as $houseId => $house): ?>
                            <option value="<?= e($houseId) ?>" <?= $selectedHouseId === $houseId ? 'selected' : '' ?>><?= e($house['name'] ?? $houseId) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label">Student</label>
                    <select name="studentId" class="form-select" required <?= empty($students) ? 'disabled' : '' ?>>
                        <option value=""><?= empty($students) ? 'No students assigned to this house' : 'Select student in this house' ?></option>
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
                <div class="col-md-3">
                    <label class="form-label">Status</label>
                    <select name="status" class="form-select">
                        <option value="present">Present</option>
                        <option value="absent">Absent</option>
                        <option value="excused">Excused</option>
                        <option value="late">Late</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label">Date</label>
                    <input type="date" name="date" class="form-control" value="<?= e($date) ?>" required>
                </div>
                <div class="col-md-2 d-flex align-items-end">
                    <button class="btn btn-primary w-100">Save</button>
                </div>
            </form>
        </div>

        <div class="card stat-card p-3">
            <div class="mb-3 small">
                Showing <strong><?= count($attendance) ?></strong> record(s)
                <?php if (!empty($searchQuery) || !empty($statusFilter)): ?>
                    (filtered)
                <?php endif; ?>
            </div>
            <table class="table table-hover data-table w-100">
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>Student</th>
                        <th>Bed</th>
                        <th>Status</th>
                        <th>Marked By</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($attendance)): ?>
                        <tr>
                            <td colspan="6" class="text-center text-muted">No attendance records matching your filters.</td>
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
                            ?>
                            <tr>
                                <td><?= e($record['date'] ?? '-') ?></td>
                                <td><?= e(($student['firstName'] ?? '') . ' ' . ($student['lastName'] ?? '') . ' (' . ($student['admissionNo'] ?? '') . ')') ?: e($record['studentId'] ?? '-') ?></td>
                                <td><?= e($bedMap[(string) ($record['studentId'] ?? '')] ?? '—') ?></td>
                                <td><span class="badge bg-<?= ($record['status'] ?? 'present') === 'present' ? 'success' : (($record['status'] ?? '') === 'absent' ? 'danger' : 'warning') ?>"><?= e($record['status'] ?? 'present') ?></span></td>
                                <td><?= e($markedByNames[(string) ($record['markedBy'] ?? '')] ?? ($record['markedBy'] ?? '—')) ?></td>
                                <td class="text-nowrap"><a class="btn btn-sm btn-outline-primary" href="<?= url('views/house-master/attendance/view.php?id=' . urlencode((string) ($record['id'] ?? ''))) ?>">View</a> <a class="btn btn-sm btn-outline-secondary" href="<?= url('views/house-master/attendance/edit.php?id=' . urlencode((string) ($record['id'] ?? ''))) ?>">Edit</a> <a class="btn btn-sm btn-outline-danger" href="<?= url('views/house-master/attendance/delete.php?id=' . urlencode((string) ($record['id'] ?? ''))) ?>">Delete</a></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
<?php require APP_ROOT . '/app/views/components/footer.php'; ?>
