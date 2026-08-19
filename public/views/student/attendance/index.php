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
$allowedRoles = [ROLE_STUDENT];
require APP_ROOT . '/app/middleware/RoleMiddleware.php';

use App\Services\AttendanceService;

$studentId = current_user()['uid'];
$date = sanitize($_GET['date'] ?? date('Y-m-d'));
$dateFrom = sanitize($_GET['dateFrom'] ?? '');
$dateTo = sanitize($_GET['dateTo'] ?? '');
$statusFilter = sanitize($_GET['status'] ?? '');

// Get attendance records
$attendance = AttendanceService::history($studentId, 100);

// Apply date range filter
if (!empty($dateFrom)) {
    $attendance = array_filter($attendance, fn($r) => strtotime($r['date'] ?? '') >= strtotime($dateFrom));
}
if (!empty($dateTo)) {
    $attendance = array_filter($attendance, fn($r) => strtotime($r['date'] ?? '') <= strtotime($dateTo));
}

// Apply status filter
if (!empty($statusFilter)) {
    $attendance = array_filter($attendance, fn($r) => ($r['status'] ?? 'present') === $statusFilter);
}

// Calculate summary from filtered results
$summary = [
    'present' => count(array_filter($attendance, fn($r) => ($r['status'] ?? '') === 'present')),
    'absent' => count(array_filter($attendance, fn($r) => ($r['status'] ?? '') === 'absent')),
    'late' => count(array_filter($attendance, fn($r) => ($r['status'] ?? '') === 'late')),
    'excused' => count(array_filter($attendance, fn($r) => ($r['status'] ?? '') === 'excused')),
];
$total = count($attendance);
$attendanceRate = $total > 0 ? round(((($summary['present'] + $summary['excused']) / $total) * 100)) : 0;
$navItems = [
    ['icon' => 'bi-speedometer2', 'label' => 'Dashboard', 'href' => url('views/student/dashboard/index.php')],
    ['icon' => 'bi-calendar-check', 'label' => 'Attendance', 'href' => url('views/student/attendance/index.php'), 'active' => true],
    ['icon' => 'bi-people', 'label' => 'Visitors', 'href' => url('views/student/visitors/index.php')],
    ['icon' => 'bi-flag', 'label' => 'Incidents', 'href' => url('views/student/incidents/index.php')],
    ['icon' => 'bi-bell', 'label' => 'Notifications', 'href' => url('views/student/notifications/index.php')],
    ['icon' => 'bi-person-circle', 'label' => 'Profile', 'href' => url('views/student/profile/index.php')],
    ['icon' => 'bi-house-door', 'label' => 'Room', 'href' => url('views/student/room/index.php')],
    ['icon' => 'bi-gear', 'label' => 'Settings', 'href' => url('views/student/settings/index.php')],
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
                    <h5 class="mb-0">My Attendance</h5>
                    <small class="text-muted">View your attendance records and download reports</small>
                </div>
                <div class="btn-group" role="group">
                    <a href="<?= url('reports/export.php?type=student_attendance&format=csv') ?>" class="btn btn-success btn-sm">
                        <i class="bi bi-download"></i> CSV
                    </a>
                    <a href="<?= url('reports/export.php?type=student_attendance&format=pdf') ?>" class="btn btn-danger btn-sm">
                        <i class="bi bi-file-pdf"></i> PDF
                    </a>
                </div>
            </div>
        </div>

        <!-- Advanced Filters -->
        <div class="card stat-card p-3 mb-3">
            <form method="GET" class="row g-3">
                <input type="hidden" name="route" value="/views/student/attendance/index.php">
                
                <div class="col-md-3">
                    <label class="form-label small">Date From</label>
                    <input type="date" name="dateFrom" class="form-control form-control-sm" value="<?= e($dateFrom) ?>">
                </div>

                <div class="col-md-3">
                    <label class="form-label small">Date To</label>
                    <input type="date" name="dateTo" class="form-control form-control-sm" value="<?= e($dateTo) ?>">
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

                <div class="col-md-3 d-flex align-items-end gap-2">
                    <button type="submit" class="btn btn-primary btn-sm w-100">Filter</button>
                    <a href="<?= url('views/student/attendance/index.php') ?>" class="btn btn-outline-secondary btn-sm">Reset</a>
                </div>
            </form>
        </div>

        <!-- Statistics Cards -->
        <div class="row g-3 mb-4">
            <div class="col-md-3">
                <div class="card stat-card p-3 text-center">
                    <div class="text-muted small">Present</div>
                    <div class="fs-2 fw-bold text-success"><?= e($summary['present']) ?></div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card stat-card p-3 text-center">
                    <div class="text-muted small">Absent</div>
                    <div class="fs-2 fw-bold text-danger"><?= e($summary['absent']) ?></div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card stat-card p-3 text-center">
                    <div class="text-muted small">Late</div>
                    <div class="fs-2 fw-bold text-warning"><?= e($summary['late']) ?></div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card stat-card p-3 text-center">
                    <div class="text-muted small">Attendance Rate</div>
                    <div class="fs-2 fw-bold <?= $attendanceRate >= 80 ? 'text-success' : ($attendanceRate >= 70 ? 'text-warning' : 'text-danger') ?>"><?= e($attendanceRate) ?>%</div>
                </div>
            </div>
        </div>

        <div class="card stat-card p-3">
            <div class="mb-3 small">
                Showing <strong><?= count($attendance) ?></strong> record(s)
                <?php if (!empty($dateFrom) || !empty($dateTo) || !empty($statusFilter)): ?>
                    (filtered)
                <?php endif; ?>
            </div>
            <table class="table table-hover data-table w-100">
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>Status</th>
                        <th>Reason</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!empty($attendance)): ?>
                        <?php foreach ($attendance as $entry): ?>
                            <tr>
                                <td><?= e($entry['date'] ?? '') ?></td>
                                <td>
                                    <span class="badge bg-<?= match(($entry['status'] ?? '')) {
                                        'present' => 'success',
                                        'absent' => 'danger',
                                        'late' => 'warning',
                                        'excused' => 'info',
                                        default => 'secondary'
                                    } ?>">
                                        <?= e(ucfirst($entry['status'] ?? 'unknown')) ?>
                                    </span>
                                </td>
                                <td><?= e($entry['reason'] ?? $entry['notes'] ?? '—') ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="3" class="text-center text-muted">No attendance records matching your filters.</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <script>
        // Quick export handlers
        const currentUrl = new URL(window.location);
        document.querySelectorAll('a[href*="format="]').forEach(link => {
            const format = new URL(link.href).searchParams.get('format');
            if (format) {
                link.href = '/views/student/attendance/export.php?' + currentUrl.searchParams.toString() + '&format=' + format;
            }
        });
    </script>
</div>
<?php require APP_ROOT . '/app/views/components/footer.php'; ?>
