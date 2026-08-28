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
require APP_ROOT . '/app/middleware/RoleMiddleware/RoleMiddleware.php';

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
$attendedCount = $summary['present'] + $summary['excused'];
$attendanceRate = $total > 0 ? round(($attendedCount / $total) * 100) : 100;
$latestRecord = $attendance[0] ?? null;
$latestStatus = strtolower((string) ($latestRecord['status'] ?? 'unknown'));
$latestStatusClass = match ($latestStatus) {
    'present' => 'success',
    'absent' => 'danger',
    'late' => 'warning',
    'excused' => 'info',
    default => 'secondary',
};

$filterActive = $dateFrom !== '' || $dateTo !== '' || $statusFilter !== '';
$studentName = trim(($student['firstName'] ?? '') . ' ' . ($student['lastName'] ?? '')) ?: ($currentUser['name'] ?? 'Student');
$pageTitle = 'My Attendance';

$navItems = [
    ['icon' => 'bi-speedometer2', 'label' => 'Dashboard', 'href' => url('views/student/dashboard/index.php')],
    ['icon' => 'bi-calendar-check', 'label' => 'My Attendance', 'href' => url('views/student/attendance/index/index.php'), 'active' => true],
    ['icon' => 'bi-people', 'label' => 'Visitors', 'href' => url('views/student/visitors/index/index.php')],
    ['icon' => 'bi-flag', 'label' => 'Incidents', 'href' => url('views/student/incidents/index/index.php')],
    ['icon' => 'bi-door-closed', 'label' => 'My Room', 'href' => url('views/student/room/index.php')],
];

require APP_ROOT . '/app/views/components/header/header.php';
require APP_ROOT . '/app/views/components/sidebar/sidebar.php';
?>
<div class="main-content">
    <?php require APP_ROOT . '/app/views/components/navbar/navbar.php'; ?>
    <?php require APP_ROOT . '/app/views/components/alerts/alerts.php'; ?>
    <div class="content-wrapper">

        <!-- Page Hero -->
        <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-3">
            <div>
                <h4 class="mb-1 fw-bold text-dark">
                    <i class="bi bi-calendar-check-fill text-primary me-2"></i>My Residential Roll Call History
                </h4>
                <p class="text-muted mb-0">Review your daily dormitory attendance logs, presence rate, and excused absences</p>
            </div>
            <div class="d-flex gap-2 flex-wrap">
                <a href="<?= url('views/student/attendance/export/export.php?format=csv') ?>" class="btn btn-outline-success btn-sm">
                    <i class="bi bi-filetype-csv me-1"></i> Export CSV
                </a>
                <a href="<?= url('views/student/attendance/export/export.php?format=pdf') ?>" class="btn btn-primary btn-sm">
                    <i class="bi bi-filetype-pdf me-1"></i> PDF Transcript
                </a>
            </div>
        </div>

        <?php if ($pageNotice): ?>
            <div class="alert alert-warning mb-4"><i class="bi bi-exclamation-triangle me-2"></i><?= e($pageNotice) ?></div>
        <?php endif; ?>

        <!-- KPI Stats Cards -->
        <div class="row g-3 mb-4">
            <div class="col-sm-6 col-lg-3">
                <div class="card stat-card h-100 p-3 border-start border-4 border-success shadow-sm">
                    <div class="d-flex justify-content-between align-items-start">
                        <div>
                            <span class="text-muted small text-uppercase fw-semibold">Present Roll Calls</span>
                            <h3 class="fw-bold my-1 text-success"><?= e((string) $summary['present']) ?></h3>
                            <span class="small text-muted">Confirmed on time</span>
                        </div>
                        <div class="rounded-3 bg-success bg-opacity-10 p-2 text-success"><i class="bi bi-check-circle fs-4"></i></div>
                    </div>
                </div>
            </div>

            <div class="col-sm-6 col-lg-3">
                <div class="card stat-card h-100 p-3 border-start border-4 border-danger shadow-sm">
                    <div class="d-flex justify-content-between align-items-start">
                        <div>
                            <span class="text-muted small text-uppercase fw-semibold">Absences</span>
                            <h3 class="fw-bold my-1 text-danger"><?= e((string) $summary['absent']) ?></h3>
                            <span class="small text-muted">Unexcused misses</span>
                        </div>
                        <div class="rounded-3 bg-danger bg-opacity-10 p-2 text-danger"><i class="bi bi-x-circle fs-4"></i></div>
                    </div>
                </div>
            </div>

            <div class="col-sm-6 col-lg-3">
                <div class="card stat-card h-100 p-3 border-start border-4 border-warning shadow-sm">
                    <div class="d-flex justify-content-between align-items-start">
                        <div>
                            <span class="text-muted small text-uppercase fw-semibold">Late Arrivals</span>
                            <h3 class="fw-bold my-1 text-warning"><?= e((string) $summary['late']) ?></h3>
                            <span class="small text-muted">Tardy roll calls</span>
                        </div>
                        <div class="rounded-3 bg-warning bg-opacity-10 p-2 text-warning"><i class="bi bi-clock-history fs-4"></i></div>
                    </div>
                </div>
            </div>

            <div class="col-sm-6 col-lg-3">
                <div class="card stat-card h-100 p-3 border-start border-4 border-primary shadow-sm">
                    <div class="d-flex justify-content-between align-items-start">
                        <div>
                            <span class="text-muted small text-uppercase fw-semibold">Attendance Rate</span>
                            <h3 class="fw-bold my-1 text-primary"><?= e((string) $attendanceRate) ?>%</h3>
                            <span class="small text-muted">Overall performance</span>
                        </div>
                        <div class="rounded-3 bg-primary bg-opacity-10 p-2 text-primary"><i class="bi bi-graph-up-arrow fs-4"></i></div>
                    </div>
                </div>
            </div>
        </div>

        <div class="row g-4">
            <!-- Attendance History Table Column -->
            <div class="col-xl-8">
                <!-- Filter Search Bar -->
                <div class="card stat-card shadow-sm mb-4 border-0">
                    <div class="card-body p-3">
                        <form method="GET" class="row g-2 align-items-center">
                            <input type="hidden" name="route" value="/views/student/attendance/index/index.php">
                            <div class="col-md-4">
                                <label class="form-label small text-muted mb-1">From Date</label>
                                <input type="date" name="dateFrom" class="form-control form-control-sm" value="<?= e($dateFrom) ?>">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label small text-muted mb-1">To Date</label>
                                <input type="date" name="dateTo" class="form-control form-control-sm" value="<?= e($dateTo) ?>">
                            </div>
                            <div class="col-md-2">
                                <label class="form-label small text-muted mb-1">Status</label>
                                <select name="status" class="form-select form-select-sm">
                                    <option value="">All</option>
                                    <option value="present" <?= $statusFilter === 'present' ? 'selected' : '' ?>>Present</option>
                                    <option value="absent" <?= $statusFilter === 'absent' ? 'selected' : '' ?>>Absent</option>
                                    <option value="late" <?= $statusFilter === 'late' ? 'selected' : '' ?>>Late</option>
                                    <option value="excused" <?= $statusFilter === 'excused' ? 'selected' : '' ?>>Excused</option>
                                </select>
                            </div>
                            <div class="col-md-2 d-flex align-items-end gap-1">
                                <button type="submit" class="btn btn-primary btn-sm flex-grow-1">Filter</button>
                                <a href="<?= url('views/student/attendance/index/index.php') ?>" class="btn btn-outline-secondary btn-sm">Reset</a>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- History Table -->
                <div class="card stat-card shadow-sm border-0">
                    <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
                        <h6 class="mb-0 fw-bold"><i class="bi bi-clock-history me-2 text-primary"></i>Roll Call Records Log</h6>
                        <span class="small text-muted">Showing <?= $total ?> records</span>
                    </div>
                    <div class="card-body p-0">
                        <?php if ($attendance): ?>
                            <div class="table-responsive">
                                <table class="table table-hover align-middle mb-0">
                                    <thead class="table-light">
                                        <tr>
                                            <th>Roll Call Date</th>
                                            <th>Bed Space</th>
                                            <th>Status</th>
                                            <th>Notes / Reason</th>
                                            <th class="text-end">Action</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($attendance as $entry): ?>
                                            <?php
                                            $status = strtolower((string) ($entry['status'] ?? 'unknown'));
                                            $badge = match ($status) {
                                                'present' => 'bg-success',
                                                'absent' => 'bg-danger',
                                                'late' => 'bg-warning text-dark',
                                                'excused' => 'bg-info',
                                                default => 'bg-secondary',
                                            };
                                            ?>
                                            <tr>
                                                <td><strong><?= e($entry['date'] ?? '') ?></strong></td>
                                                <td><span class="badge bg-light text-dark border">Bed <?= e($assignedBed['bedNumber'] ?? '-') ?></span></td>
                                                <td><span class="badge <?= $badge ?>"><?= e(ucfirst($status)) ?></span></td>
                                                <td><small class="text-muted"><?= e($entry['reason'] ?? $entry['notes'] ?? '—') ?></small></td>
                                                <td class="text-end">
                                                    <a class="btn btn-sm btn-outline-primary" href="<?= url('views/student/attendance/view/view.php?id=' . urlencode((string) ($entry['id'] ?? ''))) ?>">
                                                        <i class="bi bi-eye me-1"></i> View
                                                    </a>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php else: ?>
                            <div class="text-center text-muted py-5">
                                <i class="bi bi-calendar-x fs-2 d-block mb-2 text-secondary"></i>
                                <h6>No attendance records found</h6>
                                <p class="small mb-0">No roll call entries match your selected date or status filters.</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Profile & Residence Sidebar Column -->
            <div class="col-xl-4">
                <div class="card stat-card shadow-sm border-0 mb-4">
                    <div class="card-header bg-white py-3">
                        <h6 class="mb-0 fw-bold"><i class="bi bi-person-badge me-2 text-primary"></i>My Residence Snapshot</h6>
                    </div>
                    <div class="card-body p-0">
                        <ul class="list-group list-group-flush">
                            <li class="list-group-item d-flex justify-content-between align-items-center py-3">
                                <span class="text-muted small fw-semibold">Resident Student</span>
                                <strong class="text-dark"><?= e($studentName) ?></strong>
                            </li>
                            <li class="list-group-item d-flex justify-content-between align-items-center py-3">
                                <span class="text-muted small fw-semibold">Admission No.</span>
                                <span class="font-monospace text-muted"><?= e($student['admissionNo'] ?? '—') ?></span>
                            </li>
                            <li class="list-group-item d-flex justify-content-between align-items-center py-3">
                                <span class="text-muted small fw-semibold">Assigned Bed Space</span>
                                <span class="badge bg-primary">Bed <?= e($assignedBed['bedNumber'] ?? 'Unassigned') ?></span>
                            </li>
                            <li class="list-group-item d-flex justify-content-between align-items-center py-3">
                                <span class="text-muted small fw-semibold">Latest Roll Call</span>
                                <span class="badge bg-<?= e($latestStatusClass) ?>"><?= e($latestRecord ? ucfirst($latestStatus) : 'None') ?></span>
                            </li>
                        </ul>
                    </div>
                    <div class="card-footer bg-white p-3 border-top d-grid gap-2">
                        <a href="<?= url('views/student/attendance/history/history.php') ?>" class="btn btn-outline-primary btn-sm">
                            <i class="bi bi-clock-history me-1"></i> Full Semester History
                        </a>
                        <a href="<?= url('views/student/room/index.php') ?>" class="btn btn-outline-secondary btn-sm">
                            <i class="bi bi-door-closed me-1"></i> My Room Details
                        </a>
                    </div>
                </div>
            </div>
        </div>

    </div>
</div>
<?php require APP_ROOT . '/app/views/components/footer/footer.php'; ?>
