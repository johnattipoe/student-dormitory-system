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
$allowedRoles = [ROLE_HOUSEPARENT];
require APP_ROOT . '/app/middleware/RoleMiddleware.php';

use App\Services\AttendanceService;
use App\Services\HouseService;
use App\Services\StudentService;

$date = sanitize($_GET['date'] ?? date('Y-m-d'));
$searchQuery = sanitize($_GET['search'] ?? '');
$statusFilter = sanitize($_GET['status'] ?? '');
$houseFilter = sanitize($_GET['house'] ?? '');

$attendance = AttendanceService::forDate($date);
$students = StudentService::all();
$houses = HouseService::all();

// Apply filters
if (!empty($searchQuery)) {
    $attendance = array_filter($attendance, function($record) use ($searchQuery, $students) {
        $student = current(array_filter($students, fn($s) => ((string) ($s['id'] ?? '')) === ((string) ($record['studentId'] ?? ''))));
        $name = ($student['firstName'] ?? '') . ' ' . ($student['lastName'] ?? '');
        $admNo = $student['admissionNo'] ?? '';
        return stripos($name, $searchQuery) !== false || stripos($admNo, $searchQuery) !== false;
    });
}

if (!empty($statusFilter)) {
    $attendance = array_filter($attendance, fn($record) => ($record['status'] ?? 'present') === $statusFilter);
}

if (!empty($houseFilter)) {
    $attendance = array_filter($attendance, function($record) use ($houseFilter, $students) {
        $student = current(array_filter($students, fn($s) => ((string) ($s['id'] ?? '')) === ((string) ($record['studentId'] ?? ''))));
        return ((string) ($student['houseId'] ?? '')) === $houseFilter;
    });
}

$summary = AttendanceService::summary($date);
$houseMap = [];
foreach ($houses as $house) {
    $houseMap[(string) ($house['id'] ?? '')] = $house['name'] ?? 'House';
}

$pageTitle = 'Senior Houseparent Attendance';
$navItems = [
    ['icon' => 'bi-speedometer2', 'label' => 'Dashboard', 'href' => url('views/houseparent/dashboard/index.php')],
    ['icon' => 'bi-mortarboard', 'label' => 'Students', 'href' => url('views/houseparent/students/index.php')],
    ['icon' => 'bi-calendar-check', 'label' => 'Attendance', 'href' => url('views/houseparent/attendance/index.php'), 'active' => true],
    ['icon' => 'bi-door-open', 'label' => 'Rooms', 'href' => url('views/houseparent/rooms/index.php')],
    ['icon' => 'bi-people', 'label' => 'Visitors', 'href' => url('views/houseparent/visitors/index.php')],
    ['icon' => 'bi-shield-exclamation', 'label' => 'Incidents', 'href' => url('views/houseparent/incidents/index.php')],
    ['icon' => 'bi-bell', 'label' => 'Notifications', 'href' => url('views/houseparent/notifications/index.php')],
];

require APP_ROOT . '/app/views/components/header.php';
require APP_ROOT . '/app/views/components/sidebar.php';
?>
<div class="main-content">
    <?php require APP_ROOT . '/app/views/components/navbar.php'; ?>
    <?php require APP_ROOT . '/app/views/components/alerts.php'; ?>
    <div class="content-wrapper">
        <div class="mb-3">
            <h5 class="mb-2">Attendance Overview</h5>
            <small class="text-muted">View and manage attendance records for your house.</small>
        </div>

        <!-- Advanced Filters -->
        <div class="card stat-card p-3 mb-3">
            <form method="GET" class="row g-3">
                <input type="hidden" name="route" value="/views/houseparent/attendance/index.php">
                
                <div class="col-md-3">
                    <label class="form-label small">Date</label>
                    <input type="date" name="date" class="form-control form-control-sm" value="<?= e($date) ?>">
                </div>

                <div class="col-md-3">
                    <label class="form-label small">Search Student</label>
                    <input type="text" name="search" class="form-control form-control-sm" placeholder="Name or admission no." value="<?= e($searchQuery) ?>">
                </div>

                <div class="col-md-2">
                    <label class="form-label small">Status</label>
                    <select name="status" class="form-select form-select-sm">
                        <option value="">All Statuses</option>
                        <option value="present" <?= $statusFilter === 'present' ? 'selected' : '' ?>>Present</option>
                        <option value="absent" <?= $statusFilter === 'absent' ? 'selected' : '' ?>>Absent</option>
                        <option value="late" <?= $statusFilter === 'late' ? 'selected' : '' ?>>Late</option>
                        <option value="excused" <?= $statusFilter === 'excused' ? 'selected' : '' ?>>Excused</option>
                    </select>
                </div>

                <div class="col-md-2">
                    <label class="form-label small">House</label>
                    <select name="house" class="form-select form-select-sm">
                        <option value="">All Houses</option>
                        <?php foreach ($houses as $h): ?>
                            <option value="<?= e((string) ($h['id'] ?? '')) ?>" <?= $houseFilter === ((string) ($h['id'] ?? '')) ? 'selected' : '' ?>>
                                <?= e($h['name'] ?? '') ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="col-md-2 d-flex align-items-end gap-2">
                    <button type="submit" class="btn btn-primary btn-sm w-100">Filter</button>
                    <a href="<?= url('views/houseparent/attendance/index.php') ?>" class="btn btn-outline-secondary btn-sm">Reset</a>
                </div>
            </form>
        </div>

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

        <div class="card stat-card p-3">
            <div class="mb-3 small">
                Showing <strong><?= count($attendance) ?></strong> record(s)
                <?php if (!empty($searchQuery) || !empty($statusFilter) || !empty($houseFilter)): ?>
                    (filtered)
                <?php endif; ?>
            </div>
            <table class="table table-hover data-table w-100">
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>Student</th>
                        <th>House</th>
                        <th>Status</th>
                        <th>Marked By</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($attendance)): ?>
                        <tr>
                            <td colspan="5" class="text-center text-muted">No attendance records found matching your filters.</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($attendance as $record): ?>
                            <?php
                            $student = null;
                            foreach ($students as $s) {
                                if (($s['id'] ?? '') === ($record['studentId'] ?? '')) {
                                    $student = $s;
                                    break;
                                }
                            }
                            ?>
                            <tr>
                                <td><?= e($record['date'] ?? '-') ?></td>
                                <td><?= e(($student['firstName'] ?? '') . ' ' . ($student['lastName'] ?? '') . ' (' . ($student['admissionNo'] ?? '') . ')') ?: e($record['studentId'] ?? '-') ?></td>
                                <td><?= e($houseMap[(string) ($record['houseId'] ?? '')] ?? ($student['houseId'] ?? ($record['houseId'] ?? '—'))) ?></td>
                                <td><span class="badge bg-<?= ($record['status'] ?? 'present') === 'present' ? 'success' : (($record['status'] ?? '') === 'absent' ? 'danger' : 'warning') ?>"><?= e($record['status'] ?? 'present') ?></span></td>
                                <td><?= e($record['markedBy'] ?? '—') ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
<?php require APP_ROOT . '/app/views/components/footer.php'; ?>