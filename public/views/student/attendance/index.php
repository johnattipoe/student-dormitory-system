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
use App\Services\BedService;
use App\Services\StudentService;

$currentUser = current_user() ?? [];
$studentId = $currentUser['studentId'] ?? $currentUser['uid'] ?? null;
$dateFrom = sanitize($_GET['dateFrom'] ?? '');
$dateTo = sanitize($_GET['dateTo'] ?? '');
$statusFilter = sanitize($_GET['status'] ?? '');
$student = null;
$assignedBed = null;
$pageNotice = null;
$attendance = [];

try {
    if ($studentId) {
        $student = StudentService::find((string) $studentId);
    }

    if (!$student && !empty($currentUser['email'])) {
        foreach (StudentService::all() as $candidate) {
            if ((string) ($candidate['email'] ?? '') === (string) $currentUser['email']) {
                $student = $candidate;
                $studentId = $candidate['id'] ?? $studentId;
                break;
            }
        }
    }

    if ($studentId) {
        $attendance = AttendanceService::history((string) $studentId, 180);
    }

    $studentDocumentId = (string) ($student['id'] ?? $studentId ?? '');
    foreach (BedService::all() as $bed) {
        if ((string) ($bed['studentId'] ?? '') === $studentDocumentId) {
            $assignedBed = $bed;
            break;
        }
    }
} catch (Throwable $e) {
    $pageNotice = 'Attendance records are temporarily unavailable. Please try again later.';
}

usort($attendance, static function ($a, $b) {
    return strcmp((string) ($b['date'] ?? ''), (string) ($a['date'] ?? ''));
});

if ($dateFrom !== '') {
    $attendance = array_values(array_filter($attendance, static fn($r) => strtotime($r['date'] ?? '') >= strtotime($dateFrom)));
}
if ($dateTo !== '') {
    $attendance = array_values(array_filter($attendance, static fn($r) => strtotime($r['date'] ?? '') <= strtotime($dateTo)));
}
if ($statusFilter !== '') {
    $attendance = array_values(array_filter($attendance, static fn($r) => strtolower((string) ($r['status'] ?? '')) === strtolower($statusFilter)));
}

$summary = [
    'present' => count(array_filter($attendance, static fn($r) => ($r['status'] ?? '') === 'present')),
    'absent' => count(array_filter($attendance, static fn($r) => ($r['status'] ?? '') === 'absent')),
    'late' => count(array_filter($attendance, static fn($r) => ($r['status'] ?? '') === 'late')),
    'excused' => count(array_filter($attendance, static fn($r) => ($r['status'] ?? '') === 'excused')),
];
$total = count($attendance);
$positive = $summary['present'] + $summary['excused'];
$attendanceRate = $total > 0 ? round(($positive / $total) * 100) : 0;
$latestRecord = $attendance[0] ?? null;
$latestStatus = strtolower((string) ($latestRecord['status'] ?? 'none'));
$latestStatusClass = match ($latestStatus) {
    'present' => 'success',
    'absent' => 'danger',
    'late' => 'warning',
    'excused' => 'info',
    default => 'secondary',
};
$studentName = trim(($student['firstName'] ?? '') . ' ' . ($student['lastName'] ?? ''));
if ($studentName === '') {
    $studentName = $currentUser['name'] ?? 'Student';
}
$filterActive = $dateFrom !== '' || $dateTo !== '' || $statusFilter !== '';
$exportQuery = http_build_query([
    'dateFrom' => $dateFrom,
    'dateTo' => $dateTo,
    'status' => $statusFilter,
]);

$pageTitle = 'My Attendance';
$pageStyles = ['student.css'];
$navItems = [
    ['icon' => 'bi-speedometer2', 'label' => 'Dashboard', 'href' => url('views/student/dashboard/index.php')],
    ['icon' => 'bi-calendar-check', 'label' => 'Attendance', 'href' => url('views/student/attendance/index.php'), 'active' => true],
    ['icon' => 'bi-people', 'label' => 'Visitors', 'href' => url('views/student/visitors/index.php')],
    ['icon' => 'bi-flag', 'label' => 'Incidents', 'href' => url('views/student/incidents/index.php')],
    ['icon' => 'bi-bell', 'label' => 'Notifications', 'href' => url('views/student/notifications/index.php')],
    ['icon' => 'bi-person-circle', 'label' => 'Profile', 'href' => url('views/student/profile/index.php')],
    ['icon' => 'bi-house-door', 'label' => 'Room', 'href' => url('views/student/room/index.php')],
    ['icon' => 'bi-grid-3x3-gap', 'label' => 'Beds', 'href' => url('views/student/beds/index.php')],
    ['icon' => 'bi-gear', 'label' => 'Settings', 'href' => url('views/student/settings/index.php')],
];

require APP_ROOT . '/app/views/components/header.php';
require APP_ROOT . '/app/views/components/sidebar.php';
?>
<div class="main-content">
    <?php require APP_ROOT . '/app/views/components/navbar.php'; ?>
    <?php require APP_ROOT . '/app/views/components/alerts.php'; ?>
    <div class="content-wrapper">
        <section class="student-attendance-hero mb-4">
            <div class="student-attendance-hero-icon"><i class="bi bi-calendar-check"></i></div>
            <div>
                <span class="student-settings-kicker">Attendance tracking</span>
                <h1>My Attendance</h1>
                <p>Review your roll-call history, attendance rate, recent status, bed details, and exportable reports.</p>
                <div class="student-settings-badges">
                    <span class="badge bg-primary"><i class="bi bi-person me-1"></i><?= e($studentName) ?></span>
                    <span class="badge bg-info"><i class="bi bi-bed me-1"></i>Bed <?= e($assignedBed['bedNumber'] ?? 'Not assigned') ?></span>
                    <span class="badge bg-<?= e($latestStatusClass) ?>"><i class="bi bi-clock-history me-1"></i><?= e($latestRecord ? ucfirst($latestStatus) : 'No records') ?></span>
                </div>
            </div>
            <div class="student-attendance-actions">
                <a href="<?= url('views/student/attendance/export.php?' . $exportQuery . '&format=csv') ?>" class="btn btn-light"><i class="bi bi-filetype-csv me-1"></i>CSV</a>
                <a href="<?= url('views/student/attendance/export.php?' . $exportQuery . '&format=pdf') ?>" class="btn btn-primary"><i class="bi bi-filetype-pdf me-1"></i>PDF</a>
            </div>
        </section>

        <?php if ($pageNotice): ?>
            <div class="alert alert-warning"><i class="bi bi-exclamation-triangle me-1"></i><?= e($pageNotice) ?></div>
        <?php endif; ?>

        <div class="row g-3 mb-4">
            <div class="col-md-3"><div class="student-attendance-stat"><span class="student-attendance-stat-icon green"><i class="bi bi-check-circle"></i></span><div><small>Present</small><strong><?= e((string) $summary['present']) ?></strong></div></div></div>
            <div class="col-md-3"><div class="student-attendance-stat"><span class="student-attendance-stat-icon red"><i class="bi bi-x-circle"></i></span><div><small>Absent</small><strong><?= e((string) $summary['absent']) ?></strong></div></div></div>
            <div class="col-md-3"><div class="student-attendance-stat"><span class="student-attendance-stat-icon orange"><i class="bi bi-clock"></i></span><div><small>Late</small><strong><?= e((string) $summary['late']) ?></strong></div></div></div>
            <div class="col-md-3"><div class="student-attendance-stat"><span class="student-attendance-stat-icon blue"><i class="bi bi-graph-up-arrow"></i></span><div><small>Rate</small><strong><?= e((string) $attendanceRate) ?>%</strong></div></div></div>
        </div>

        <div class="row g-4">
            <div class="col-xl-8">
                <div class="student-attendance-main-card mb-4">
                    <div class="student-attendance-card-header">
                        <div>
                            <span class="student-settings-kicker">Filters</span>
                            <h2>Search attendance records</h2>
                            <p>Filter by date range or status before exporting your attendance report.</p>
                        </div>
                        <?php if ($filterActive): ?><span class="badge bg-info">Filtered</span><?php endif; ?>
                    </div>
                    <form method="GET" class="student-attendance-filter">
                        <input type="hidden" name="route" value="/views/student/attendance/index.php">
                        <div>
                            <label class="form-label">Date from</label>
                            <input type="date" name="dateFrom" class="form-control" value="<?= e($dateFrom) ?>">
                        </div>
                        <div>
                            <label class="form-label">Date to</label>
                            <input type="date" name="dateTo" class="form-control" value="<?= e($dateTo) ?>">
                        </div>
                        <div>
                            <label class="form-label">Status</label>
                            <select name="status" class="form-select">
                                <option value="">All statuses</option>
                                <option value="present" <?= $statusFilter === 'present' ? 'selected' : '' ?>>Present</option>
                                <option value="absent" <?= $statusFilter === 'absent' ? 'selected' : '' ?>>Absent</option>
                                <option value="late" <?= $statusFilter === 'late' ? 'selected' : '' ?>>Late</option>
                                <option value="excused" <?= $statusFilter === 'excused' ? 'selected' : '' ?>>Excused</option>
                            </select>
                        </div>
                        <div class="student-attendance-filter-actions">
                            <button type="submit" class="btn btn-primary"><i class="bi bi-funnel me-1"></i>Filter</button>
                            <a href="<?= url('views/student/attendance/index.php') ?>" class="btn btn-outline-secondary"><i class="bi bi-arrow-counterclockwise me-1"></i>Reset</a>
                        </div>
                    </form>
                </div>

                <div class="student-attendance-main-card">
                    <div class="student-attendance-card-header">
                        <div>
                            <span class="student-settings-kicker">Records</span>
                            <h2>Attendance history</h2>
                            <p>Showing <?= e((string) $total) ?> record<?= $total === 1 ? '' : 's' ?><?= $filterActive ? ' matching your filters' : '' ?>.</p>
                        </div>
                    </div>

                    <?php if ($attendance): ?>
                        <div class="table-responsive">
                            <table class="table table-hover data-table w-100" data-export="true">
                                <thead>
                                    <tr>
                                        <th>Date</th>
                                        <th>Bed</th>
                                        <th>Status</th>
                                        <th>Reason / Notes</th>
                                        <th>Action</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($attendance as $entry): ?>
                                        <?php
                                        $status = strtolower((string) ($entry['status'] ?? 'unknown'));
                                        $badge = match ($status) {
                                            'present' => 'success',
                                            'absent' => 'danger',
                                            'late' => 'warning',
                                            'excused' => 'info',
                                            default => 'secondary',
                                        };
                                        ?>
                                        <tr>
                                            <td><?= e($entry['date'] ?? '') ?></td>
                                            <td><?= e($assignedBed['bedNumber'] ?? '-') ?></td>
                                            <td><span class="badge bg-<?= e($badge) ?>"><?= e(ucfirst($status)) ?></span></td>
                                            <td><?= e($entry['reason'] ?? $entry['notes'] ?? '-') ?></td>
                                            <td><a class="btn btn-sm btn-outline-primary" href="<?= url('views/student/attendance/view.php?id=' . urlencode((string) ($entry['id'] ?? ''))) ?>">View</a></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php else: ?>
                        <div class="student-attendance-empty">
                            <i class="bi bi-calendar-x"></i>
                            <h3>No attendance records found</h3>
                            <p>No attendance records match the current filters.</p>
                            <a href="<?= url('views/student/attendance/index.php') ?>" class="btn btn-outline-primary">Clear filters</a>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <div class="col-xl-4">
                <aside class="student-attendance-side-card mb-4">
                    <span class="student-settings-kicker">Performance</span>
                    <h2>Attendance rate</h2>
                    <div class="student-attendance-ring" style="--rate: <?= e((string) $attendanceRate) ?>;">
                        <span><?= e((string) $attendanceRate) ?>%</span>
                    </div>
                    <p class="text-muted mb-0">Present and excused records count toward your attendance rate.</p>
                </aside>

                <aside class="student-attendance-side-card">
                    <span class="student-settings-kicker">Latest update</span>
                    <h2>Current status</h2>
                    <div class="student-attendance-info-list">
                        <div><i class="bi bi-calendar-event text-primary"></i><span>Latest date</span><strong><?= e($latestRecord['date'] ?? 'No record') ?></strong></div>
                        <div><i class="bi bi-check2-circle text-success"></i><span>Latest status</span><strong><?= e($latestRecord ? ucfirst($latestStatus) : 'No record') ?></strong></div>
                        <div><i class="bi bi-bed text-info"></i><span>Assigned bed</span><strong><?= e($assignedBed['bedNumber'] ?? 'Not assigned') ?></strong></div>
                        <div><i class="bi bi-journal-text text-warning"></i><span>Total records</span><strong><?= e((string) $total) ?></strong></div>
                    </div>
                    <div class="d-grid gap-2 mt-3">
                        <a href="<?= url('views/student/attendance/history.php') ?>" class="btn btn-outline-primary"><i class="bi bi-clock-history me-1"></i>Full history</a>
                        <a href="<?= url('views/student/room/index.php') ?>" class="btn btn-outline-info"><i class="bi bi-house-door me-1"></i>Room details</a>
                    </div>
                </aside>
            </div>
        </div>
    </div>
</div>
<?php require APP_ROOT . '/app/views/components/footer.php'; ?>
