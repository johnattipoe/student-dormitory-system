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
use App\Services\StudentService;
use App\Services\UserService;
use App\Services\FirebaseService;

$houseId = current_user()['houseId'] ?? null;
$students = StudentService::all();
$studentMap = [];
$markedByMap = [];

// 1. Map all students
foreach ($students as $student) {
    $sName = trim(($student['firstName'] ?? '') . ' ' . ($student['lastName'] ?? ''));
    $adm = !empty($student['admissionNo']) ? ' [' . $student['admissionNo'] . ']' : '';
    $studentMap[(string) ($student['id'] ?? '')] = $student;
    if ($sName !== '') {
        foreach ([$student['id'] ?? null, $student['studentId'] ?? null, $student['admissionNo'] ?? null, $student['userId'] ?? null, $student['uid'] ?? null] as $key) {
            if ($key !== null && $key !== '') {
                $markedByMap[(string) $key] = $sName . $adm . ' (Student)';
            }
        }
    }
}

// 2. Map all users (Admins, House Masters, Staff)
try {
    foreach ((new UserService())->all() as $user) {
        $name = trim(($user['name'] ?? '') ?: (($user['fullName'] ?? '') ?: (($user['firstName'] ?? '') . ' ' . ($user['lastName'] ?? ''))));
        if ($name === '') $name = $user['displayName'] ?? $user['username'] ?? $user['email'] ?? null;
        if ($name) {
            $roleLabel = !empty($user['role']) ? ' (' . ucfirst(str_replace(['_', '-'], ' ', (string) $user['role'])) . ')' : '';
            $displayName = $name . $roleLabel;
            foreach ([$user['id'] ?? null, $user['uid'] ?? null, $user['userId'] ?? null, $user['email'] ?? null] as $key) {
                if ($key !== null && $key !== '') {
                    $markedByMap[(string) $key] = $displayName;
                }
            }
        }
    }
} catch (\Throwable $e) {}

// 3. Fallback resolver for markedBy
$getMarkedByName = function (array $entry) use (&$markedByMap, $studentMap): string {
    if (!empty($entry['markedByName']) && trim((string) $entry['markedByName']) !== '' && !str_starts_with((string) $entry['markedByName'], 'Staff/User')) {
        return (string) $entry['markedByName'];
    }
    $rawId = trim((string) ($entry['markedBy'] ?? ''));
    if ($rawId === '') {
        return '—';
    }
    if (isset($markedByMap[$rawId])) {
        return $markedByMap[$rawId];
    }

    try {
        $db = FirebaseService::getInstance();
        $u = $db->getDocument('users', $rawId);
        if ($u) {
            $name = trim(($u['name'] ?? '') ?: (($u['fullName'] ?? '') ?: (($u['firstName'] ?? '') . ' ' . ($u['lastName'] ?? ''))));
            if ($name === '') $name = $u['displayName'] ?? $u['username'] ?? $u['email'] ?? '';
            if ($name !== '') {
                $roleLabel = !empty($u['role']) ? ' (' . ucfirst(str_replace(['_', '-'], ' ', (string) $u['role'])) . ')' : '';
                $markedByMap[$rawId] = $name . $roleLabel;
                return $markedByMap[$rawId];
            }
        }
        $s = $db->getDocument('students', $rawId);
        if ($s) {
            $sName = trim(($s['firstName'] ?? '') . ' ' . ($s['lastName'] ?? ''));
            if ($sName !== '') {
                $adm = !empty($s['admissionNo']) ? ' [' . $s['admissionNo'] . ']' : '';
                $markedByMap[$rawId] = $sName . $adm . ' (Student)';
                return $markedByMap[$rawId];
            }
        }
    } catch (\Throwable $e) {}

    $curr = current_user();
    if ($rawId === ($curr['uid'] ?? '') || $rawId === ($curr['id'] ?? '')) {
        $name = trim(($curr['name'] ?? '') ?: (($curr['firstName'] ?? '') . ' ' . ($curr['lastName'] ?? '')));
        if ($name !== '') {
            $markedByMap[$rawId] = $name . ' (House Master)';
            return $markedByMap[$rawId];
        }
    }

    return $rawId;
};

$attendanceHistory = AttendanceService::byHouse($houseId);
$attendanceStudent = sanitize($_GET['studentId'] ?? '');
$attendanceStatus = sanitize($_GET['status'] ?? '');
$attendanceDate = sanitize($_GET['date'] ?? '');

$attendanceHistory = array_values(array_filter($attendanceHistory, function ($entry) use ($attendanceStudent, $attendanceStatus, $attendanceDate) {
    return ($attendanceStudent === '' || ($entry['studentId'] ?? '') === $attendanceStudent)
        && ($attendanceStatus === '' || ($entry['status'] ?? '') === $attendanceStatus)
        && ($attendanceDate === '' || ($entry['date'] ?? '') === $attendanceDate);
}));

$houseStudents = array_values(array_filter($students, fn($s) => ($s['houseId'] ?? null) === $houseId));

$presentCount = count(array_filter($attendanceHistory, fn($e) => ($e['status'] ?? '') === 'present'));
$absentCount = count(array_filter($attendanceHistory, fn($e) => ($e['status'] ?? '') === 'absent'));
$lateCount = count(array_filter($attendanceHistory, fn($e) => ($e['status'] ?? '') === 'late'));

$pageTitle = 'Attendance History';
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
    <div class="content-wrapper">
        <?php require APP_ROOT . '/app/views/components/alerts/alerts.php'; ?>

        <!-- Hero Header -->
        <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-3">
            <div>
                <h4 class="mb-1 fw-bold text-dark"><i class="bi bi-clock-history text-primary me-2"></i>House Attendance History</h4>
                <p class="text-muted mb-0">Historical records of roll calls, curfew attendance, and exeat tracking</p>
            </div>
            <div class="d-flex gap-2 flex-wrap">
                <a href="<?= url('views/house-master/attendance/mark-attendance/mark-attendance.php') ?>" class="btn btn-primary btn-sm">
                    <i class="bi bi-calendar2-check me-1"></i>Mark Roll Call
                </a>
                <a href="<?= url('views/house-master/reports/export/export.php?type=attendance') ?>" class="btn btn-outline-success btn-sm">
                    <i class="bi bi-filetype-csv me-1"></i>Export CSV
                </a>
                <a href="<?= url('views/house-master/attendance/index/index.php') ?>" class="btn btn-outline-secondary btn-sm">
                    <i class="bi bi-arrow-left me-1"></i>Today's Attendance
                </a>
            </div>
        </div>

        <!-- KPI Stats Cards -->
        <div class="row g-3 mb-4">
            <div class="col-md-3">
                <div class="card stat-card h-100 p-3 border-start border-4 border-primary shadow-sm">
                    <div class="d-flex justify-content-between align-items-start">
                        <div>
                            <span class="text-muted small text-uppercase fw-semibold">Total Records</span>
                            <h3 class="fw-bold my-1 text-primary"><?= e((string) count($attendanceHistory)) ?></h3>
                            <span class="small text-muted">Filtered logs</span>
                        </div>
                        <div class="rounded-3 bg-primary bg-opacity-10 p-2 text-primary"><i class="bi bi-journal-text fs-4"></i></div>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card stat-card h-100 p-3 border-start border-4 border-success shadow-sm">
                    <div class="d-flex justify-content-between align-items-start">
                        <div>
                            <span class="text-muted small text-uppercase fw-semibold">Present</span>
                            <h3 class="fw-bold my-1 text-success"><?= e((string) $presentCount) ?></h3>
                            <span class="small text-muted">On time</span>
                        </div>
                        <div class="rounded-3 bg-success bg-opacity-10 p-2 text-success"><i class="bi bi-check-circle fs-4"></i></div>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card stat-card h-100 p-3 border-start border-4 border-danger shadow-sm">
                    <div class="d-flex justify-content-between align-items-start">
                        <div>
                            <span class="text-muted small text-uppercase fw-semibold">Absent</span>
                            <h3 class="fw-bold my-1 text-danger"><?= e((string) $absentCount) ?></h3>
                            <span class="small text-muted">Unexcused</span>
                        </div>
                        <div class="rounded-3 bg-danger bg-opacity-10 p-2 text-danger"><i class="bi bi-x-circle fs-4"></i></div>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card stat-card h-100 p-3 border-start border-4 border-warning shadow-sm">
                    <div class="d-flex justify-content-between align-items-start">
                        <div>
                            <span class="text-muted small text-uppercase fw-semibold">Late</span>
                            <h3 class="fw-bold my-1 text-warning"><?= e((string) $lateCount) ?></h3>
                            <span class="small text-muted">Curfew delays</span>
                        </div>
                        <div class="rounded-3 bg-warning bg-opacity-10 p-2 text-warning"><i class="bi bi-clock fs-4"></i></div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Filter Bar -->
        <div class="card stat-card shadow-sm mb-4 border-0">
            <div class="card-body p-3">
                <form method="GET" class="row g-2 align-items-end">
                    <div class="col-md-4">
                        <label class="form-label small fw-semibold">Student</label>
                        <select name="studentId" class="form-select form-select-sm">
                            <option value="">All students in house</option>
                            <?php foreach ($houseStudents as $student): ?>
                                <option value="<?= e((string) ($student['id'] ?? '')) ?>" <?= $attendanceStudent === ($student['id'] ?? '') ? 'selected' : '' ?>>
                                    <?= e(trim(($student['firstName'] ?? '') . ' ' . ($student['lastName'] ?? ''))) ?> (<?= e($student['admissionNo'] ?? '') ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label small fw-semibold">Date</label>
                        <input type="date" name="date" class="form-control form-control-sm" value="<?= e($attendanceDate) ?>">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label small fw-semibold">Status</label>
                        <select name="status" class="form-select form-select-sm">
                            <option value="">All statuses</option>
                            <option value="present" <?= $attendanceStatus === 'present' ? 'selected' : '' ?>>Present</option>
                            <option value="absent" <?= $attendanceStatus === 'absent' ? 'selected' : '' ?>>Absent</option>
                            <option value="late" <?= $attendanceStatus === 'late' ? 'selected' : '' ?>>Late</option>
                            <option value="excused" <?= $attendanceStatus === 'excused' ? 'selected' : '' ?>>Excused</option>
                        </select>
                    </div>
                    <div class="col-md-2 d-flex gap-2">
                        <button class="btn btn-primary btn-sm flex-fill" type="submit"><i class="bi bi-funnel me-1"></i>Filter</button>
                        <a href="<?= url('views/house-master/attendance/history/history.php') ?>" class="btn btn-outline-secondary btn-sm">Reset</a>
                    </div>
                </form>
            </div>
        </div>

        <!-- History Table -->
        <div class="card stat-card shadow-sm border-0">
            <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
                <h6 class="mb-0 fw-bold"><i class="bi bi-calendar-check me-2"></i>Attendance Log Entries</h6>
                <small class="text-muted">Showing <strong><?= count($attendanceHistory) ?></strong> record(s)</small>
            </div>
            <div class="card-body p-0">
                <table class="table table-hover align-middle mb-0 data-table w-100">
                    <thead class="table-light">
                        <tr>
                            <th>Date</th>
                            <th>Student</th>
                            <th>Status</th>
                            <th>Marked By</th>
                            <th class="text-end">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!empty($attendanceHistory)): ?>
                            <?php foreach ($attendanceHistory as $entry): ?>
                                <?php 
                                    $entryStudent = $studentMap[(string) ($entry['studentId'] ?? '')] ?? null; 
                                    $studentName = $entryStudent ? trim(($entryStudent['firstName'] ?? '') . ' ' . ($entryStudent['lastName'] ?? '')) : ($entry['studentId'] ?? '—');
                                    $markedByName = $getMarkedByName($entry);
                                    $status = $entry['status'] ?? 'present';
                                    $statusBadge = match($status) {
                                        'present' => 'bg-success',
                                        'absent' => 'bg-danger',
                                        'late' => 'bg-warning text-dark',
                                        'excused' => 'bg-info text-dark',
                                        default => 'bg-secondary'
                                    };
                                ?>
                                <tr>
                                    <td class="small text-muted text-nowrap"><?= e($entry['date'] ?? '') ?></td>
                                    <td><strong class="text-dark"><?= e($studentName) ?></strong></td>
                                    <td>
                                        <span class="badge <?= $statusBadge ?>">
                                            <?= e(ucfirst($status)) ?>
                                        </span>
                                    </td>
                                    <td class="small"><?= e($markedByName) ?></td>
                                    <td class="text-end text-nowrap">
                                        <a class="btn btn-sm btn-outline-primary" href="<?= url('views/house-master/attendance/view/view.php?id=' . urlencode((string) ($entry['id'] ?? ''))) ?>"><i class="bi bi-eye me-1"></i>View</a> 
                                        <a class="btn btn-sm btn-outline-secondary" href="<?= url('views/house-master/attendance/edit/edit.php?id=' . urlencode((string) ($entry['id'] ?? ''))) ?>"><i class="bi bi-pencil me-1"></i>Edit</a> 
                                        <a class="btn btn-sm btn-outline-danger" href="<?= url('views/house-master/attendance/delete/delete.php?id=' . urlencode((string) ($entry['id'] ?? ''))) ?>"><i class="bi bi-trash"></i></a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="5" class="text-center text-muted py-5"><i class="bi bi-inbox fs-3 d-block mb-2"></i>No historical attendance records found.</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
<?php require APP_ROOT . '/app/views/components/footer/footer.php'; ?>
