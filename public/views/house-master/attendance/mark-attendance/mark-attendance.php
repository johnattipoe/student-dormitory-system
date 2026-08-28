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
$roomNumberById = [];
foreach (RoomService::all($houseId) as $room) {
    $roomId = (string) ($room['id'] ?? $room['roomId'] ?? '');
    if ($roomId !== '') {
        $roomNumberById[$roomId] = (string) ($room['roomNumber'] ?? $room['number'] ?? $roomId);
    }
}

// Handle form submission
$errors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $date = sanitize($_POST['date'] ?? date('Y-m-d'));
    $status = sanitize($_POST['status'] ?? 'present');
    $notes = sanitize($_POST['notes'] ?? '');
    
    $singleStudentId = sanitize($_POST['studentId'] ?? '');
    $studentIds = array_values(array_unique(array_filter(array_map(
        static fn($studentId): string => sanitize((string) $studentId),
        (array) ($_POST['studentIds'] ?? [])
    ))));
    
    if (!empty($singleStudentId)) {
        if (!isset($allowedStudentIds[$singleStudentId])) {
            $errors[] = 'The selected student is not assigned to your house.';
        } else {
            try {
                AttendanceService::mark($singleStudentId, $status, $date, $houseId, $markedBy);
                flash('success', 'Attendance marked for student.');
                redirect(url('views/house-master/attendance/index/index.php'));
            } catch (Exception $e) {
                $errors[] = 'Failed to mark attendance: ' . $e->getMessage();
            }
        }
    } elseif (!empty($studentIds) && is_array($studentIds)) {
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
            flash('success', 'Marked attendance for ' . $successCount . ' student(s).');
            redirect(url('views/house-master/attendance/index/index.php'));
        }
    } else {
        $errors[] = 'Please select at least one student.';
    }
}

$studentSearch = strtolower(sanitize($_GET['search'] ?? ''));
if ($studentSearch !== '') {
    $students = array_values(array_filter($students, function ($student) use ($studentSearch) {
        return str_contains(strtolower(trim(($student['firstName'] ?? '') . ' ' . ($student['lastName'] ?? '') . ' ' . ($student['admissionNo'] ?? '') . ' ' . ($student['roomNumber'] ?? $student['roomId'] ?? ''))), $studentSearch);
    }));
}

$pageTitle = 'Mark Attendance';
$navItems = [
    ['icon' => 'bi-speedometer2', 'label' => 'Dashboard', 'href' => url('views/house-master/dashboard/index.php')],
    ['icon' => 'bi-calendar-check', 'label' => 'Attendance', 'href' => url('views/house-master/attendance/index/index.php'), 'active' => true],
];

require APP_ROOT . '/app/views/components/header/header.php';
require APP_ROOT . '/app/views/components/sidebar/sidebar.php';
?>
<div class="main-content">
    <?php require APP_ROOT . '/app/views/components/navbar/navbar.php'; ?>
    <div class="content-wrapper">
        <?php require APP_ROOT . '/app/views/components/alerts/alerts.php'; ?>

        <?php foreach ($errors as $error): ?>
            <div class="alert alert-danger mb-3"><i class="bi bi-exclamation-triangle me-2"></i><?= e($error) ?></div>
        <?php endforeach; ?>

        <!-- Hero Header -->
        <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-3">
            <div>
                <h4 class="mb-1 fw-bold text-dark"><i class="bi bi-calendar-check-fill text-success me-2"></i>Mark House Roll Call</h4>
                <p class="text-muted mb-0">Record daily curfew and dormitory roll call for <?= e((string) count($students)) ?> resident students</p>
            </div>
            <div class="d-flex gap-2 flex-wrap">
                <a href="<?= url('views/house-master/attendance/history/history.php') ?>" class="btn btn-outline-primary btn-sm">
                    <i class="bi bi-clock-history me-1"></i>View Attendance History
                </a>
                <a href="<?= url('views/house-master/attendance/index/index.php') ?>" class="btn btn-outline-secondary btn-sm">
                    <i class="bi bi-arrow-left me-1"></i>Back
                </a>
            </div>
        </div>

        <form method="POST" action="<?= url('views/house-master/attendance/mark-attendance/mark-attendance.php') ?>">
            <!-- Roll Call Settings Card -->
            <div class="card stat-card shadow-sm border-0 mb-4">
                <div class="card-header bg-white py-3">
                    <h6 class="mb-0 fw-bold"><i class="bi bi-sliders me-2 text-primary"></i>Roll Call Settings</h6>
                </div>
                <div class="card-body p-4">
                    <div class="row g-3">
                        <div class="col-md-4">
                            <label class="form-label fw-semibold">Attendance Date <span class="text-danger">*</span></label>
                            <div class="input-group">
                                <span class="input-group-text bg-white"><i class="bi bi-calendar-event"></i></span>
                                <input type="date" name="date" class="form-control" value="<?= e(date('Y-m-d')) ?>" required>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-semibold">Mark Status As <span class="text-danger">*</span></label>
                            <div class="input-group">
                                <span class="input-group-text bg-white"><i class="bi bi-check2-circle"></i></span>
                                <select name="status" class="form-select" required>
                                    <option value="present">Present (On-time)</option>
                                    <option value="absent">Absent</option>
                                    <option value="late">Late Arrival</option>
                                    <option value="excused">Excused / Exeat</option>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-semibold">Roll Call Notes (Optional)</label>
                            <div class="input-group">
                                <span class="input-group-text bg-white"><i class="bi bi-journal-text"></i></span>
                                <input type="text" name="notes" class="form-control" placeholder="e.g. Evening roll call, pre-curfew">
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Student Selection Table Card -->
            <div class="card stat-card shadow-sm border-0 mb-4">
                <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center flex-wrap gap-2">
                    <h6 class="mb-0 fw-bold"><i class="bi bi-people me-2 text-primary"></i>Select Students to Mark</h6>
                    <small class="text-muted">Select students below to apply status</small>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive" style="max-height: 480px; overflow-y: auto;">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="table-light sticky-top">
                                <tr>
                                    <th style="width: 45px;" class="text-center">
                                        <input type="checkbox" id="selectAllStudents" class="form-check-input">
                                    </th>
                                    <th>Student Name</th>
                                    <th>Admission No.</th>
                                    <th>Assigned Room</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($students)): ?>
                                    <tr>
                                        <td colspan="4" class="text-center text-muted py-5"><i class="bi bi-person-x fs-3 d-block mb-2"></i>No students assigned to your house.</td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($students as $s): ?>
                                        <?php $bulkStudentId = $studentRecordId($s); ?>
                                        <?php if ($bulkStudentId === '') continue; ?>
                                        <tr>
                                            <td class="text-center">
                                                <input type="checkbox" name="studentIds[]" value="<?= e($bulkStudentId) ?>" class="form-check-input student-checkbox">
                                            </td>
                                            <td>
                                                <strong class="text-dark d-block"><?= e(trim(($s['firstName'] ?? '') . ' ' . ($s['lastName'] ?? ''))) ?></strong>
                                            </td>
                                            <td class="small text-muted font-monospace"><?= e($s['admissionNo'] ?? '—') ?></td>
                                            <?php $studentRoomId = (string) ($s['roomId'] ?? ''); ?>
                                            <td>
                                                <span class="badge bg-light text-dark border"><?= e($roomNumberById[$studentRoomId] ?? ($s['roomNumber'] ?? '—')) ?></span>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
                <div class="card-footer bg-white py-3 d-flex justify-content-between align-items-center">
                    <a href="<?= url('views/house-master/attendance/index/index.php') ?>" class="btn btn-outline-secondary">
                        Cancel
                    </a>
                    <button type="submit" class="btn btn-success">
                        <i class="bi bi-check2-circle me-1"></i>Save Attendance Records
                    </button>
                </div>
            </div>
        </form>
    </div>
</div>

<script>
    document.getElementById('selectAllStudents')?.addEventListener('change', function() {
        document.querySelectorAll('.student-checkbox').forEach(checkbox => {
            checkbox.checked = this.checked;
        });
    });
</script>

<?php require APP_ROOT . '/app/views/components/footer/footer.php'; ?>
