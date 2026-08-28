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
$allowedRoles = [ROLE_ADMIN];
require APP_ROOT . '/app/middleware/RoleMiddleware/RoleMiddleware.php';

use App\Services\AttendanceService;
use App\Services\BedService;
use App\Services\HouseService;
use App\Services\StudentService;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'mark_attendance') {
    $result = AttendanceService::mark([
        'studentId' => sanitize($_POST['studentId'] ?? ''),
        'status' => sanitize($_POST['status'] ?? 'present'),
        'date' => sanitize($_POST['date'] ?? date('Y-m-d')),
        'houseId' => sanitize($_POST['houseId'] ?? ''),
        'markedBy' => current_user()['uid'] ?? current_user()['id'] ?? 'admin',
    ]);
    flash($result['success'] ? 'success' : 'error', $result['message'] ?? 'Attendance saved.');
    redirect(base_url('index.php?route=/views/admin/attendance/index/index.php'));
}

$date = sanitize($_GET['date'] ?? date('Y-m-d'));
$allRecords = AttendanceService::all();
$todayRecords = AttendanceService::forDate($date);
$summary = AttendanceService::summary($date);
$students = StudentService::all();
$bedMap = [];
foreach (BedService::all() as $bed) {
    if (!empty($bed['studentId'])) $bedMap[(string) $bed['studentId']] = (string) ($bed['bedNumber'] ?? '—');
}
$houses = HouseService::all();
$houseStats = [];
foreach ($houses as $house) {
    $houseId = $house['id'] ?? $house['houseId'] ?? null;
    $houseStats[] = [
        'name' => $house['name'] ?? 'House',
        'present' => count(array_filter($todayRecords, fn($record) => ($record['houseId'] ?? null) === $houseId && ($record['status'] ?? '') === 'present')),
        'absent' => count(array_filter($todayRecords, fn($record) => ($record['houseId'] ?? null) === $houseId && ($record['status'] ?? '') === 'absent')),
        'late' => count(array_filter($todayRecords, fn($record) => ($record['houseId'] ?? null) === $houseId && ($record['status'] ?? '') === 'late')),
    ];
}
$absentStudents = array_values(array_filter($todayRecords, fn($record) => ($record['status'] ?? '') === 'absent'));
$lateStudents = array_values(array_filter($todayRecords, fn($record) => ($record['status'] ?? '') === 'late'));

$studentStats = [];
foreach ($students as $student) {
    $studentId = (string) ($student['id'] ?? '');
    $studentStats[] = [
        'name' => trim(($student['firstName'] ?? '') . ' ' . ($student['lastName'] ?? '')) ?: ($student['admissionNo'] ?? 'Student'),
        'admissionNo' => $student['admissionNo'] ?? '-',
        'present' => count(array_filter($allRecords, fn($record) => ($record['studentId'] ?? '') === $studentId && ($record['status'] ?? '') === 'present')),
        'absent' => count(array_filter($allRecords, fn($record) => ($record['studentId'] ?? '') === $studentId && ($record['status'] ?? '') === 'absent')),
        'late' => count(array_filter($allRecords, fn($record) => ($record['studentId'] ?? '') === $studentId && ($record['status'] ?? '') === 'late')),
    ];
}

$monthly = [];
for ($i = 5; $i >= 0; $i--) {
    $monthDate = new DateTimeImmutable('first day of this month');
    $monthDate = $monthDate->modify("-$i months");
    $key = $monthDate->format('Y-m');
    $monthRecords = array_values(array_filter($allRecords, fn($record) => !empty($record['date']) && substr($record['date'], 0, 7) === $key));
    $monthly[] = [
        'month' => $monthDate->format('M Y'),
        'present' => count(array_filter($monthRecords, fn($record) => ($record['status'] ?? '') === 'present')),
        'absent' => count(array_filter($monthRecords, fn($record) => ($record['status'] ?? '') === 'absent')),
        'late' => count(array_filter($monthRecords, fn($record) => ($record['status'] ?? '') === 'late')),
        'total' => count($monthRecords),
    ];
}

$pageTitle = 'Attendance Management';
$navItems = [
    ['icon' => 'bi-speedometer2', 'label' => 'Dashboard', 'href' => url('views/admin/dashboard.php')],
    ['icon' => 'bi-calendar-check', 'label' => 'Attendance', 'href' => url('views/admin/attendance/index/index.php'), 'active' => true],
    ['icon' => 'bi-bar-chart', 'label' => 'Reports', 'href' => url('views/admin/attendance/reports/reports.php')],
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
                    <i class="bi bi-calendar-check-fill text-success me-2"></i>Campus Attendance Command
                </h4>
                <p class="text-muted mb-0">Record roll calls, monitor house presence rates, and audit absenteeism history</p>
            </div>
            <div class="d-flex gap-2 align-items-center flex-wrap">
                <form method="GET" class="d-flex gap-2 align-items-center">
                    <input type="hidden" name="route" value="/views/admin/attendance/index/index.php">
                    <input type="date" name="date" class="form-control form-control-sm" value="<?= e($date) ?>">
                    <button class="btn btn-primary btn-sm"><i class="bi bi-calendar3 me-1"></i> Filter Date</button>
                </form>
                <a href="<?= url('views/admin/attendance/reports/reports.php') ?>" class="btn btn-outline-secondary btn-sm">
                    <i class="bi bi-bar-chart me-1"></i> Reports
                </a>
            </div>
        </div>

        <!-- KPI Cards -->
        <div class="row g-3 mb-4">
            <div class="col-sm-6 col-lg-4">
                <div class="card stat-card h-100 p-3 border-start border-4 border-primary shadow-sm">
                    <div class="d-flex justify-content-between align-items-start">
                        <div>
                            <span class="text-muted small text-uppercase fw-semibold">Recorded for <?= e(date('M d', strtotime($date))) ?></span>
                            <h3 class="fw-bold my-1 text-primary"><?= e((string) ($summary['total'] ?? 0)) ?></h3>
                            <span class="small text-muted">Total marked today</span>
                        </div>
                        <div class="rounded-3 bg-primary bg-opacity-10 p-2 text-primary"><i class="bi bi-calendar-check fs-4"></i></div>
                    </div>
                </div>
            </div>

            <div class="col-sm-6 col-lg-4">
                <div class="card stat-card h-100 p-3 border-start border-4 border-success shadow-sm">
                    <div class="d-flex justify-content-between align-items-start">
                        <div>
                            <span class="text-muted small text-uppercase fw-semibold">Present</span>
                            <h3 class="fw-bold my-1 text-success"><?= e((string) ($summary['present'] ?? 0)) ?></h3>
                            <span class="small text-muted"><?= ($summary['total'] ?? 0) > 0 ? round((($summary['present'] ?? 0) / $summary['total']) * 100) : 0 ?>% presence rate</span>
                        </div>
                        <div class="rounded-3 bg-success bg-opacity-10 p-2 text-success"><i class="bi bi-person-check fs-4"></i></div>
                    </div>
                </div>
            </div>

            <div class="col-sm-6 col-lg-4">
                <div class="card stat-card h-100 p-3 border-start border-4 border-danger shadow-sm">
                    <div class="d-flex justify-content-between align-items-start">
                        <div>
                            <span class="text-muted small text-uppercase fw-semibold">Absent / Late</span>
                            <h3 class="fw-bold my-1 text-danger"><?= e((string) (($summary['absent'] ?? 0) + ($summary['late'] ?? 0))) ?></h3>
                            <span class="small text-muted"><?= e((string) ($summary['absent'] ?? 0)) ?> absent &bull; <?= e((string) ($summary['late'] ?? 0)) ?> late</span>
                        </div>
                        <div class="rounded-3 bg-danger bg-opacity-10 p-2 text-danger"><i class="bi bi-person-x fs-4"></i></div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Mark Attendance Form -->
        <div class="card stat-card shadow-sm border-0 mb-4">
            <div class="card-header bg-white py-3">
                <h6 class="mb-0 fw-bold"><i class="bi bi-pencil-square me-2 text-primary"></i>Direct Roll Call Entry</h6>
            </div>
            <div class="card-body p-4">
                <form method="POST" class="row g-3 align-items-end">
                    <input type="hidden" name="action" value="mark_attendance">
                    <div class="col-md-4">
                        <label class="form-label fw-semibold">Student</label>
                        <select name="studentId" class="form-select select2" required>
                            <option value="">-- Choose student --</option>
                            <?php foreach ($students as $student): ?>
                                <option value="<?= e((string) ($student['id'] ?? '')) ?>"><?= e(($student['firstName'] ?? '') . ' ' . ($student['lastName'] ?? '') . ' (' . ($student['admissionNo'] ?? '') . ')') ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label fw-semibold">Dormitory House</label>
                        <select name="houseId" class="form-select select2">
                            <option value="">-- Select house --</option>
                            <?php foreach ($houses as $house): ?>
                                <option value="<?= e((string) ($house['id'] ?? '')) ?>"><?= e($house['name'] ?? '') ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label fw-semibold">Roll Call Date</label>
                        <input type="date" name="date" class="form-control" value="<?= e($date) ?>" required>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label fw-semibold">Status</label>
                        <select name="status" class="form-select">
                            <option value="present">Present</option>
                            <option value="absent">Absent</option>
                            <option value="excused">Excused</option>
                            <option value="late">Late</option>
                        </select>
                    </div>
                    <div class="col-md-1">
                        <button type="submit" class="btn btn-primary w-100"><i class="bi bi-check-lg"></i></button>
                    </div>
                </form>
            </div>
        </div>

        <!-- 2-Column Tables: House Stats & Absenteeism -->
        <div class="row g-4 mb-4">
            <div class="col-lg-6">
                <div class="card stat-card shadow-sm border-0 h-100">
                    <div class="card-header bg-white py-3">
                        <h6 class="mb-0 fw-bold"><i class="bi bi-building me-2 text-success"></i>House Presence for <?= e($date) ?></h6>
                    </div>
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-hover align-middle mb-0">
                                <thead class="table-light">
                                    <tr>
                                        <th>House</th>
                                        <th>Present</th>
                                        <th>Absent</th>
                                        <th>Late</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (empty($houseStats)): ?>
                                        <tr><td colspan="4" class="text-muted text-center py-3">No house records available.</td></tr>
                                    <?php else: ?>
                                        <?php foreach ($houseStats as $entry): ?>
                                            <tr>
                                                <td><strong><?= e($entry['name']) ?></strong></td>
                                                <td><span class="badge bg-success"><?= e((string) $entry['present']) ?></span></td>
                                                <td><span class="badge bg-danger"><?= e((string) $entry['absent']) ?></span></td>
                                                <td><span class="badge bg-warning text-dark"><?= e((string) $entry['late']) ?></span></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-lg-6">
                <div class="card stat-card shadow-sm border-0 h-100">
                    <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
                        <h6 class="mb-0 fw-bold"><i class="bi bi-exclamation-triangle me-2 text-danger"></i>Absentees for <?= e($date) ?></h6>
                        <span class="badge bg-danger"><?= count($absentStudents) ?> Absent</span>
                    </div>
                    <div class="card-body p-0">
                        <?php if (empty($absentStudents)): ?>
                            <div class="text-center text-muted py-4">
                                <i class="bi bi-check-circle fs-3 text-success d-block mb-1"></i>
                                Full attendance recorded — zero absentees today!
                            </div>
                        <?php else: ?>
                            <ul class="list-group list-group-flush">
                                <?php foreach ($absentStudents as $record): ?>
                                    <?php
                                    $student = null;
                                    foreach ($students as $candidate) {
                                        if (($candidate['id'] ?? '') === ($record['studentId'] ?? '')) {
                                            $student = $candidate;
                                            break;
                                        }
                                    }
                                    $sName = trim(($student['firstName'] ?? '') . ' ' . ($student['lastName'] ?? '')) ?: ($record['studentId'] ?? 'Student');
                                    ?>
                                    <li class="list-group-item py-2 d-flex justify-content-between align-items-center">
                                        <div>
                                            <strong><?= e($sName) ?></strong>
                                            <small class="text-muted d-block">Adm: <?= e($student['admissionNo'] ?? '—') ?></small>
                                        </div>
                                        <span class="badge bg-danger">Absent</span>
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- Attendance History Table -->
        <div class="card stat-card shadow-sm border-0">
            <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
                <h6 class="mb-0 fw-bold"><i class="bi bi-clock-history me-2 text-primary"></i>Daily Attendance Audit Log</h6>
                <span class="small text-muted">Date: <?= e($date) ?></span>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Student</th>
                                <th>Bed Space</th>
                                <th>Status</th>
                                <th>Date</th>
                                <th class="text-end">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($todayRecords)): ?>
                                <tr><td colspan="5" class="text-muted text-center py-4">No attendance records submitted for this date.</td></tr>
                            <?php else: ?>
                                <?php foreach ($todayRecords as $record): ?>
                                    <?php
                                    $student = null;
                                    foreach ($students as $candidate) {
                                        if (($candidate['id'] ?? '') === ($record['studentId'] ?? '')) {
                                            $student = $candidate;
                                            break;
                                        }
                                    }
                                    $st = strtolower((string) ($record['status'] ?? 'present'));
                                    $badge = match($st) {
                                        'present' => 'bg-success',
                                        'absent' => 'bg-danger',
                                        'late' => 'bg-warning text-dark',
                                        'excused' => 'bg-info',
                                        default => 'bg-secondary',
                                    };
                                    $rId = (string) ($record['id'] ?? '');
                                    ?>
                                    <tr>
                                        <td>
                                            <strong><?= e(($student['firstName'] ?? '') . ' ' . ($student['lastName'] ?? '')) ?: e($record['studentId'] ?? 'Student') ?></strong>
                                            <small class="text-muted d-block">Adm: <?= e($student['admissionNo'] ?? '—') ?></small>
                                        </td>
                                        <td><span class="badge bg-light text-dark border">Bed <?= e($bedMap[(string) ($record['studentId'] ?? '')] ?? '—') ?></span></td>
                                        <td><span class="badge <?= $badge ?>"><?= ucfirst(e($st)) ?></span></td>
                                        <td><?= e($record['date'] ?? '-') ?></td>
                                        <td class="text-end">
                                            <a class="btn btn-sm btn-outline-primary" href="<?= url('views/admin/attendance/view/view.php?id=' . urlencode($rId)) ?>" title="View"><i class="bi bi-eye"></i></a>
                                            <a class="btn btn-sm btn-outline-secondary" href="<?= url('views/admin/attendance/edit/edit.php?id=' . urlencode($rId)) ?>" title="Edit"><i class="bi bi-pencil"></i></a>
                                            <a class="btn btn-sm btn-outline-danger" href="<?= url('views/admin/attendance/delete/delete.php?id=' . urlencode($rId)) ?>" title="Delete"><i class="bi bi-trash"></i></a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

    </div>
</div>
<?php require APP_ROOT . '/app/views/components/footer/footer.php'; ?>