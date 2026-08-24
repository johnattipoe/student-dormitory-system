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
use App\Services\RoomService;
use App\Services\StudentService;

$houseId = (string) (current_user()['houseId'] ?? current_user()['house_id'] ?? '');
$markedBy = current_user()['uid'] ?? current_user()['id'] ?? 'house-master';

$studentRecordId = static fn(array $student): string => (string) (
    $student['id']
    ?? $student['uid']
    ?? $student['studentId']
    ?? ''
);

$studentHouseId = static fn(array $student): string => (string) (
    $student['houseId']
    ?? $student['house_id']
    ?? $student['assignedHouseId']
    ?? $student['assigned_house_id']
    ?? ''
);

$studentsForHouse = static function (string $houseId) use ($studentRecordId, $studentHouseId): array {
    if ($houseId === '') {
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

$students = $studentsForHouse($houseId);
$allowedStudentIds = array_fill_keys(array_filter(array_map($studentRecordId, $students)), true);

// Handle form submission
$errors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $date = sanitize($_POST['date'] ?? date('Y-m-d'));
    $status = sanitize($_POST['status'] ?? 'present');
    $notes = sanitize($_POST['notes'] ?? '');
    
    // Check if single or bulk mode
    $singleStudentId = sanitize($_POST['studentId'] ?? '');
    $studentIds = array_values(array_unique(array_filter(array_map(
        static fn($studentId): string => sanitize((string) $studentId),
        (array) ($_POST['studentIds'] ?? [])
    ))));
    
    if (!empty($singleStudentId)) {
        if (!isset($allowedStudentIds[$singleStudentId])) {
            $errors[] = 'The selected student is not assigned to your house.';
        } else {
            // Single student mode
            try {
                AttendanceService::mark($singleStudentId, $status, $date, $houseId, $markedBy);
                flash('success', 'Attendance marked for student');
                redirect(url('views/house-master/attendance/index.php'));
            } catch (Exception $e) {
                $errors[] = 'Failed to mark attendance: ' . $e->getMessage();
            }
        }
    } elseif (!empty($studentIds) && is_array($studentIds)) {
        // Bulk mode
        $successCount = 0;
        $invalidStudentIds = array_values(array_filter($studentIds, static fn(string $studentId): bool => !isset($allowedStudentIds[$studentId])));
        if (!empty($invalidStudentIds)) {
            $errors[] = 'One or more selected students are not assigned to your house.';
        }

        foreach (array_diff($studentIds, $invalidStudentIds) as $sId) {
            try {
                AttendanceService::mark($sId, $status, $date, $houseId, $markedBy);
                $successCount++;
            } catch (Exception $e) {
                $errors[] = 'Failed for student ' . $sId . ': ' . $e->getMessage();
            }
        }
        if ($successCount > 0) {
            flash('success', 'Marked attendance for ' . $successCount . ' student(s)');
            redirect(url('views/house-master/attendance/index.php'));
        }
    } else {
        $errors[] = 'Please select at least one student';
    }
}

$studentSearch = strtolower(sanitize($_GET['search'] ?? ''));
if ($studentSearch !== '') {
    $students = array_values(array_filter($students, function ($student) use ($studentSearch) {
        return str_contains(strtolower(trim(($student['firstName'] ?? '') . ' ' . ($student['lastName'] ?? '') . ' ' . ($student['admissionNo'] ?? '') . ' ' . ($student['roomId'] ?? ''))), $studentSearch);
    }));
}

$pageTitle = 'Mark Attendance';
$navItems = [
    ['icon' => 'bi-speedometer2', 'label' => 'Dashboard', 'href' => url('views/house-master/dashboard/index.php')],
    ['icon' => 'bi-mortarboard', 'label' => 'Students', 'href' => url('views/house-master/students/index.php')],
    ['icon' => 'bi-calendar-check', 'label' => 'Attendance', 'href' => url('views/house-master/attendance/index.php')],
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
        <?php foreach ($errors as $error): ?>
            <div class="alert alert-danger"><?= e($error) ?></div>
        <?php endforeach; ?>

        <div class="card stat-card p-4" style="max-width:900px;">
            <h5 class="mb-3">Mark Attendance</h5>
            <div class="alert alert-light border d-flex justify-content-between align-items-center"><span><strong><?= e((string) count($students)) ?></strong> students available for this house.</span><a href="<?= url('views/house-master/attendance/history.php') ?>" class="btn btn-sm btn-outline-primary">View history</a></div>
            
            <!-- Tab Navigation -->
            <ul class="nav nav-tabs mb-4" role="tablist">
                <li class="nav-item" role="presentation">
                    <button class="nav-link active" id="single-tab" data-bs-toggle="tab" data-bs-target="#single-mode" type="button">Single Student</button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link" id="bulk-tab" data-bs-toggle="tab" data-bs-target="#bulk-mode" type="button">Bulk Mark</button>
                </li>
            </ul>

            <!-- Tab Content -->
            <div class="tab-content">
                <!-- Single Student Mode -->
                <div class="tab-pane fade show active" id="single-mode" role="tabpanel">
                    <form method="POST" action="<?= url('views/house-master/attendance/mark-attendance.php') ?>">
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label">Student</label>
                                <select name="studentId" class="form-select" <?= empty($students) ? 'disabled' : '' ?>>
                                    <option value=""><?= empty($students) ? 'No students assigned to your house' : '-- Select a student --' ?></option>
                                    <?php foreach ($students as $s): ?>
                                        <?php $singleStudentId = $studentRecordId($s); ?>
                                        <?php if ($singleStudentId !== ''): ?>
                                        <option value="<?= e($singleStudentId) ?>">
                                            <?= e(trim(($s['firstName'] ?? '') . ' ' . ($s['lastName'] ?? ''))) ?> (<?= e($s['admissionNo'] ?? '') ?>)
                                        </option>
                                        <?php endif; ?>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Date</label>
                                <input type="date" name="date" class="form-control" value="<?= e(date('Y-m-d')) ?>" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Status</label>
                                <select name="status" class="form-select" required>
                                    <option value="present">Present</option>
                                    <option value="absent">Absent</option>
                                    <option value="late">Late</option>
                                    <option value="excused">Excused</option>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Notes (optional)</label>
                                <input type="text" name="notes" class="form-control" placeholder="e.g., Medical appointment">
                            </div>
                        </div>
                        <div class="mt-4 d-flex gap-2">
                            <button class="btn btn-primary">Submit</button>
                            <a href="<?= url('views/house-master/attendance/index.php') ?>" class="btn btn-outline-secondary">Cancel</a>
                        </div>
                    </form>
                </div>

                <!-- Bulk Mode -->
                <div class="tab-pane fade" id="bulk-mode" role="tabpanel">
                    <form method="POST" action="<?= url('views/house-master/attendance/mark-attendance.php') ?>">
                        <div class="row g-3 mb-3">
                            <div class="col-md-6">
                                <label class="form-label">Date</label>
                                <input type="date" name="date" class="form-control" value="<?= e(date('Y-m-d')) ?>" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Status</label>
                                <select name="status" class="form-select" required>
                                    <option value="present">Present</option>
                                    <option value="absent">Absent</option>
                                    <option value="late">Late</option>
                                    <option value="excused">Excused</option>
                                </select>
                            </div>
                            <div class="col-md-12">
                                <label class="form-label">Notes (optional)</label>
                                <input type="text" name="notes" class="form-control" placeholder="e.g., After sports event">
                            </div>
                        </div>

                        <div class="alert alert-info">
                            <small>Select students below to mark their attendance with the status above.</small>
                        </div>

                        <div class="mb-3">
                            <form method="GET" class="row g-2">
                                <div class="col-md-9">
                                    <input name="search" class="form-control form-control-sm" placeholder="Filter students by name, admission number, or room" value="<?= e($studentSearch) ?>">
                                </div>
                                <br>
                                <div class="col-md-3">
                                    <button class="btn btn-outline-primary btn-sm">Filter list</button>
                                </div>
                            </form>
                        </div>

                        <div class="table-responsive mb-3" style="max-height: 400px; overflow-y: auto;">
                            <table class="table table-hover table-sm">
                                <thead>
                                    <tr>
                                        <th style="width: 40px;">
                                            <input type="checkbox" id="selectAllStudents" class="form-check-input">
                                        </th>
                                        <th>Name</th>
                                        <th>Admission No.</th>
                                        <th>Room</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (empty($students)): ?>
                                        <tr>
                                            <td colspan="4" class="text-center text-muted py-4">No students assigned to your house.</td>
                                        </tr>
                                    <?php else: ?>
                                    <?php foreach ($students as $s): ?>
                                        <?php $bulkStudentId = $studentRecordId($s); ?>
                                        <?php if ($bulkStudentId === '') continue; ?>
                                        <tr>
                                            <td>
                                                <input type="checkbox" name="studentIds[]" value="<?= e($bulkStudentId) ?>" class="form-check-input student-checkbox">
                                            </td>
                                            <td><?= e(trim(($s['firstName'] ?? '') . ' ' . ($s['lastName'] ?? ''))) ?></td>
                                            <td><?= e($s['admissionNo'] ?? '') ?></td>
                                            <td><?= e($s['roomId'] ?? '—') ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>

                        <div class="mt-4 d-flex gap-2">
                            <button class="btn btn-primary">Mark Attendance</button>
                            <a href="<?= url('views/house-master/attendance/index.php') ?>" class="btn btn-outline-secondary">Cancel</a>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <script>
            // Select/deselect all checkboxes
            document.getElementById('selectAllStudents')?.addEventListener('change', function() {
                document.querySelectorAll('.student-checkbox').forEach(checkbox => {
                    checkbox.checked = this.checked;
                });
            });
        </script>
    </div>
</div>
<?php require APP_ROOT . '/app/views/components/footer.php'; ?>
