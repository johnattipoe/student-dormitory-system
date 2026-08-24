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
use App\Services\BedService;
use App\Services\StudentService;

$date = sanitize($_GET['date'] ?? date('Y-m-d'));
$statusFilter = sanitize($_GET['status'] ?? '');
$houseId = current_user()['houseId'] ?? null;
$students = StudentService::all($houseId);
$bedMap = [];
foreach (BedService::all() as $bed) {
    if (!empty($bed['studentId'])) $bedMap[(string) $bed['studentId']] = (string) ($bed['bedNumber'] ?? '—');
}
$attendance = AttendanceService::forDate($date, $houseId);
$studentFilter = sanitize($_GET['studentId'] ?? '');
if ($studentFilter !== '') {
    $attendance = array_values(array_filter($attendance, fn($record) => (string) ($record['studentId'] ?? '') === $studentFilter));
}

if ($statusFilter) {
    $attendance = array_filter($attendance, fn($record) => ($record['status'] ?? 'present') === $statusFilter);
}

$summary = AttendanceService::summary($date, $houseId);
$pageTitle = 'Attendance History';
$navItems = [
    ['icon' => 'bi-speedometer2', 'label' => 'Dashboard', 'href' => url('views/houseparent/dashboard/index.php')],
    ['icon' => 'bi-mortarboard', 'label' => 'Students', 'href' => url('views/houseparent/students/index.php')],
    ['icon' => 'bi-calendar-check', 'label' => 'Attendance', 'href' => url('views/houseparent/attendance/index.php')],
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
        <div class="card stat-card p-4">
            <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
                <h5 class="mb-0">Attendance History</h5>
                <form method="GET" class="d-flex gap-2 align-items-center flex-wrap">
                    <input type="hidden" name="route" value="/views/houseparent/attendance/history.php">
                    <input type="date" name="date" class="form-control" value="<?= e($date) ?>">
                    <select name="studentId" class="form-select form-select-sm"><option value="">All Students</option><?php foreach ($students as $student): ?><option value="<?= e((string) ($student['id'] ?? '')) ?>" <?= $studentFilter === (string) ($student['id'] ?? '') ? 'selected' : '' ?>><?= e(trim(($student['firstName'] ?? '') . ' ' . ($student['lastName'] ?? ''))) ?></option><?php endforeach; ?></select>
                    <select name="status" class="form-select form-select-sm" style="max-width: 120px;">
                        <option value="">All Statuses</option>
                        <option value="present" <?= $statusFilter === 'present' ? 'selected' : '' ?>>Present</option>
                        <option value="absent" <?= $statusFilter === 'absent' ? 'selected' : '' ?>>Absent</option>
                        <option value="late" <?= $statusFilter === 'late' ? 'selected' : '' ?>>Late</option>
                        <option value="excused" <?= $statusFilter === 'excused' ? 'selected' : '' ?>>Excused</option>
                    </select>
                    <button class="btn btn-primary btn-sm">Filter</button>
                </form>
            </div>

            <div class="row g-3 mb-4">
                <div class="col-md-4">
                    <div class="card stat-card p-3 text-center h-100">
                        <div class="text-muted small">Present</div>
                        <div class="fs-2 fw-bold"><?= e((string) ($summary['present'] ?? 0)) ?></div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="card stat-card p-3 text-center h-100">
                        <div class="text-muted small">Absent</div>
                        <div class="fs-2 fw-bold"><?= e((string) ($summary['absent'] ?? 0)) ?></div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="card stat-card p-3 text-center h-100">
                        <div class="text-muted small">Late</div>
                        <div class="fs-2 fw-bold"><?= e((string) ($summary['late'] ?? 0)) ?></div>
                    </div>
                </div>
            </div>

            <div class="table-responsive">
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
                                <td colspan="6" class="text-center text-muted">No attendance history found for this date.</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($attendance as $record): ?>
                                <?php
                                $student = null;
                                foreach ($students as $candidate) {
                                    if (($candidate['id'] ?? '') === ($record['studentId'] ?? '')) {
                                        $student = $candidate;
                                        break;
                                    }
                                }
                                ?>
                                <tr>
                                    <td><?= e($record['date'] ?? '-') ?></td>
                                    <td><?= e(trim(($student['firstName'] ?? '') . ' ' . ($student['lastName'] ?? ''))) ?: e($record['studentId'] ?? '-') ?></td>
                                    <td><?= e($bedMap[(string) ($record['studentId'] ?? '')] ?? '—') ?></td>
                                    <td><span class="badge bg-<?= ($record['status'] ?? 'present') === 'present' ? 'success' : (($record['status'] ?? '') === 'absent' ? 'danger' : 'warning') ?>"><?= e($record['status'] ?? 'present') ?></span></td>
                                    <td><?= e($record['markedBy'] ?? '—') ?></td>
                                    <td class="text-nowrap"><a class="btn btn-sm btn-outline-primary" href="<?= url('views/houseparent/attendance/view.php?id=' . urlencode((string) ($record['id'] ?? ''))) ?>">View</a> <a class="btn btn-sm btn-outline-secondary" href="<?= url('views/houseparent/attendance/edit.php?id=' . urlencode((string) ($record['id'] ?? ''))) ?>">Edit</a> <a class="btn btn-sm btn-outline-danger" href="<?= url('views/houseparent/attendance/delete.php?id=' . urlencode((string) ($record['id'] ?? ''))) ?>">Delete</a></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
<?php require APP_ROOT . '/app/views/components/footer.php'; ?>
