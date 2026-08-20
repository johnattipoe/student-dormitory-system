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
use App\Services\StudentService;

$houseId = current_user()['houseId'] ?? null;
$students = StudentService::all($houseId);
$studentMap = [];
foreach ($students as $student) {
    $studentMap[(string) ($student['id'] ?? '')] = $student;
}
$attendanceHistory = AttendanceService::byHouse($houseId);
$attendanceStudent = sanitize($_GET['studentId'] ?? '');
$attendanceStatus = sanitize($_GET['status'] ?? '');
$attendanceDate = sanitize($_GET['date'] ?? '');
$attendanceHistory = array_values(array_filter($attendanceHistory, function ($entry) use ($attendanceStudent, $attendanceStatus, $attendanceDate) {
    return ($attendanceStudent === '' || ($entry['studentId'] ?? '') === $attendanceStudent)
        && ($attendanceStatus === '' || ($entry['status'] ?? '') === $attendanceStatus)
        && ($attendanceDate === '' || ($entry['date'] ?? '') === $attendanceDate);
}));

$pageTitle = 'House Master Attendance History';
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
        <div class="card stat-card p-3">
            <div class="d-flex justify-content-between align-items-center mb-3"><h5 class="mb-0">Attendance History</h5><a href="<?= url('views/house-master/reports/export.php?type=attendance') ?>" class="btn btn-outline-success btn-sm"><i class="bi bi-filetype-csv"></i> Export CSV</a></div>
            <form method="GET" class="row g-2 mb-3"><div class="col-md-4"><select name="studentId" class="form-select form-select-sm"><option value="">All students</option><?php foreach ($students as $student): ?><option value="<?= e((string) ($student['id'] ?? '')) ?>" <?= $attendanceStudent === ($student['id'] ?? '') ? 'selected' : '' ?>><?= e(trim(($student['firstName'] ?? '') . ' ' . ($student['lastName'] ?? ''))) ?></option><?php endforeach; ?></select></div><div class="col-md-3"><input type="date" name="date" class="form-control form-control-sm" value="<?= e($attendanceDate) ?>"></div><div class="col-md-3"><select name="status" class="form-select form-select-sm"><option value="">All statuses</option><option value="present" <?= $attendanceStatus === 'present' ? 'selected' : '' ?>>Present</option><option value="absent" <?= $attendanceStatus === 'absent' ? 'selected' : '' ?>>Absent</option><option value="late" <?= $attendanceStatus === 'late' ? 'selected' : '' ?>>Late</option><option value="excused" <?= $attendanceStatus === 'excused' ? 'selected' : '' ?>>Excused</option></select></div><div class="col-md-2"><button class="btn btn-primary btn-sm">Filter</button></div></form>
            <table class="table table-hover data-table w-100">
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>Student</th>
                        <th>Status</th>
                        <th>Marked By</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!empty($attendanceHistory)): ?>
                        <?php foreach ($attendanceHistory as $entry): ?>
                            <?php $entryStudent = $studentMap[(string) ($entry['studentId'] ?? '')] ?? null; ?>
                            <tr>
                                <td><?= e($entry['date'] ?? '') ?></td>
                                <td><?= e(trim((($entryStudent['firstName'] ?? '') . ' ' . ($entryStudent['lastName'] ?? '')))) ?: e($entry['studentId'] ?? '—') ?></td>
                                <td><?= e($entry['status'] ?? 'unknown') ?></td>
                                <td><?= e($entry['markedByName'] ?? ($entry['markedBy'] ?? '—')) ?></td>
                                <td class="text-nowrap"><a class="btn btn-sm btn-outline-primary" href="<?= url('views/house-master/attendance/view.php?id=' . urlencode((string) ($entry['id'] ?? ''))) ?>">View</a> <a class="btn btn-sm btn-outline-secondary" href="<?= url('views/house-master/attendance/edit.php?id=' . urlencode((string) ($entry['id'] ?? ''))) ?>">Edit</a> <a class="btn btn-sm btn-outline-danger" href="<?= url('views/house-master/attendance/delete.php?id=' . urlencode((string) ($entry['id'] ?? ''))) ?>">Delete</a></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="5" class="text-center text-muted">No historical attendance records found.</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
<?php require APP_ROOT . '/app/views/components/footer.php'; ?>
