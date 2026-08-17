<?php
require __DIR__ . '/../../../bootstrap.php';
$allowedRoles = [ROLE_HOUSEPARENT];
require APP_ROOT . '/app/middleware/RoleMiddleware.php';

use App\Services\AttendanceService;
use App\Services\StudentService;

$date = sanitize($_GET['date'] ?? date('Y-m-d'));
$houseId = current_user()['houseId'] ?? null;
$students = StudentService::all($houseId);
$attendance = AttendanceService::forDate($date, $houseId);
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
                <form method="GET" class="d-flex gap-2 align-items-center">
                    <input type="hidden" name="route" value="/views/houseparent/attendance/history.php">
                    <input type="date" name="date" class="form-control" value="<?= e($date) ?>">
                    <button class="btn btn-primary btn-sm">View</button>
                </form>
            </div>
            <div class="table-responsive">
                <table class="table table-hover data-table w-100">
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Student</th>
                            <th>Status</th>
                            <th>Marked By</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($attendance)): ?>
                            <tr>
                                <td colspan="4" class="text-center text-muted">No attendance history found for this date.</td>
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
</div>
<?php require APP_ROOT . '/app/views/components/footer.php'; ?>
