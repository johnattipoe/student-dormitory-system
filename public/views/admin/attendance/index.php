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
require APP_ROOT . '/app/middleware/RoleMiddleware.php';

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
    redirect(base_url('index.php?route=/views/admin/attendance/index.php'));
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

$pageTitle = 'Attendance';
$navItems = [
    ['icon' => 'bi-speedometer2', 'label' => 'Dashboard', 'href' => url('views/admin/dashboard.php')],
    ['icon' => 'bi-calendar2-check', 'label' => 'Attendance', 'href' => url('views/admin/attendance/index.php'), 'active' => true],
];
require APP_ROOT . '/app/views/components/header.php';
require APP_ROOT . '/app/views/components/sidebar.php';
?>
<div class="main-content">
    <?php require APP_ROOT . '/app/views/components/navbar.php'; ?>
    <?php require APP_ROOT . '/app/views/components/alerts.php'; ?>
    <div class="content-wrapper">
        <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
            <div>
                <h5 class="mb-1">Attendance Dashboard</h5>
                <p class="text-muted mb-0">Read and write attendance records across all houses and students.</p>
            </div>
            <div class="d-flex gap-2 align-items-center flex-wrap">
                <form method="GET" class="d-flex gap-2 align-items-center">
                    <input type="hidden" name="route" value="/views/admin/attendance/index.php">
                    <input type="date" name="date" class="form-control" value="<?= e($date) ?>">
                    <button class="btn btn-primary btn-sm">View</button>
                </form>
                <button id="exportCsv" data-table="#adminAttendanceTable" class="btn btn-outline-secondary btn-sm">Export CSV</button>
            </div>
        </div>

        <div class="row g-3 mb-4">
            <div class="col-md-4">
                <div class="card stat-card p-3 text-center">
                    <div class="text-muted small">Today's Attendance</div>
                    <div class="fs-2 fw-bold"><?= e($summary['total'] ?? 0) ?></div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card stat-card p-3 text-center">
                    <div class="text-muted small">Present</div>
                    <div class="fs-2 fw-bold"><?= e($summary['present'] ?? 0) ?></div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card stat-card p-3 text-center">
                    <div class="text-muted small">Absent / Late</div>
                    <div class="fs-2 fw-bold"><?= e(($summary['absent'] ?? 0) + ($summary['late'] ?? 0)) ?></div>
                </div>
            </div>
        </div>

        <div class="card stat-card p-4 mb-4">
            <h6 class="mb-3">Mark Attendance</h6>
            <form method="POST" class="row g-3">
                <input type="hidden" name="action" value="mark_attendance">
                <div class="col-md-4">
                    <label class="form-label">Student</label>
                    <select name="studentId" class="form-select" required>
                        <option value="">Select student</option>
                        <?php foreach ($students as $student): ?>
                            <option value="<?= e((string) ($student['id'] ?? '')) ?>"><?= e(($student['firstName'] ?? '') . ' ' . ($student['lastName'] ?? '') . ' (' . ($student['admissionNo'] ?? '') . ')') ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label">House</label>
                    <select name="houseId" class="form-select">
                        <option value="">Select house</option>
                        <?php foreach ($houses as $house): ?>
                            <option value="<?= e((string) ($house['id'] ?? '')) ?>"><?= e($house['name'] ?? '') ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label">Date</label>
                    <input type="date" name="date" class="form-control" value="<?= e($date) ?>" required>
                </div>
                <div class="col-md-2">
                    <label class="form-label">Status</label>
                    <select name="status" class="form-select">
                        <option value="present">Present</option>
                        <option value="absent">Absent</option>
                        <option value="excused">Excused</option>
                        <option value="late">Late</option>
                    </select>
                </div>
                <div class="col-md-1 d-flex align-items-end">
                    <button type="submit" class="btn btn-primary w-100">Save</button>
                </div>
            </form>
        </div>

        <div class="card stat-card p-3 mb-4">
            <h6 class="mb-3">Attendance by House</h6>
            <table class="table table-hover data-table w-100">
                <thead>
                    <tr>
                        <th>House</th>
                        <th>Present</th>
                        <th>Absent</th>
                        <th>Late</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($houseStats)): ?>
                        <tr><td colspan="4" class="text-muted text-center">No house attendance available.</td></tr>
                    <?php else: ?>
                        <?php foreach ($houseStats as $entry): ?>
                            <tr>
                                <td><?= e($entry['name']) ?></td>
                                <td><?= e($entry['present']) ?></td>
                                <td><?= e($entry['absent']) ?></td>
                                <td><?= e($entry['late']) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <div class="row g-3 mb-4">
            <div class="col-md-6">
                <div class="card stat-card p-3">
                    <h6 class="mb-3">Absent Students</h6>
                    <ul class="list-group list-group-flush">
                        <?php if (empty($absentStudents)): ?>
                            <li class="list-group-item ps-0 text-muted">No absent students today.</li>
                        <?php else: ?>
                            <?php foreach ($absentStudents as $record): ?>
                                <?php
                                $student = null;
                                foreach ($students as $candidate) {
                                    if (($candidate['id'] ?? '') === ($record['studentId'] ?? '')) {
                                        $student = $candidate;
                                        break;
                                    }
                                }
                                ?>
                                <li class="list-group-item ps-0">
                                    <?= e(($student['firstName'] ?? '') . ' ' . ($student['lastName'] ?? '')) ?: e($record['studentId'] ?? '-') ?>
                                </li>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </ul>
                </div>
            </div>
            <div class="col-md-6">
                <div class="card stat-card p-3">
                    <h6 class="mb-3">Late Students</h6>
                    <ul class="list-group list-group-flush">
                        <?php if (empty($lateStudents)): ?>
                            <li class="list-group-item ps-0 text-muted">No late arrivals today.</li>
                        <?php else: ?>
                            <?php foreach ($lateStudents as $record): ?>
                                <?php
                                $student = null;
                                foreach ($students as $candidate) {
                                    if (($candidate['id'] ?? '') === ($record['studentId'] ?? '')) {
                                        $student = $candidate;
                                        break;
                                    }
                                }
                                ?>
                                <li class="list-group-item ps-0">
                                    <?= e(($student['firstName'] ?? '') . ' ' . ($student['lastName'] ?? '')) ?: e($record['studentId'] ?? '-') ?>
                                </li>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </ul>
                </div>
            </div>
        </div>

        <div class="card stat-card p-3 mb-4">
            <h6 class="mb-3">Attendance by Student</h6>
            <table class="table table-hover data-table w-100">
                <thead>
                    <tr>
                        <th>Student</th>
                        <th>Admission</th>
                        <th>Present</th>
                        <th>Absent</th>
                        <th>Late</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($studentStats)): ?>
                        <tr><td colspan="5" class="text-muted text-center">No student attendance data available.</td></tr>
                    <?php else: ?>
                        <?php foreach ($studentStats as $entry): ?>
                            <tr>
                                <td><?= e($entry['name']) ?></td>
                                <td><?= e($entry['admissionNo']) ?></td>
                                <td><?= e($entry['present']) ?></td>
                                <td><?= e($entry['absent']) ?></td>
                                <td><?= e($entry['late']) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <div class="card stat-card p-3 mb-4">
            <h6 class="mb-3">Monthly Attendance Report</h6>
            <table class="table table-hover data-table w-100">
                <thead>
                    <tr>
                        <th>Month</th>
                        <th>Present</th>
                        <th>Absent</th>
                        <th>Late</th>
                        <th>Total</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($monthly as $month): ?>
                        <tr>
                            <td><?= e($month['month']) ?></td>
                            <td><?= e($month['present']) ?></td>
                            <td><?= e($month['absent']) ?></td>
                            <td><?= e($month['late']) ?></td>
                            <td><?= e($month['total']) ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <div class="card stat-card p-3">
            <h6 class="mb-3">Attendance History</h6>
            <table id="adminAttendanceTable" class="table table-hover data-table w-100">
                <thead>
                    <tr>
                        <th>Student</th>
                        <th>Bed</th>
                        <th>Status</th>
                        <th>Date</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($todayRecords)): ?>
                        <tr><td colspan="5" class="text-muted text-center">No attendance records for this date yet.</td></tr>
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
                            ?>
                            <tr>
                                <td><?= e(($student['firstName'] ?? '') . ' ' . ($student['lastName'] ?? '') . ' (' . ($student['admissionNo'] ?? '') . ')') ?: e($record['studentId'] ?? '-') ?></td>
                                <td><?= e($bedMap[(string) ($record['studentId'] ?? '')] ?? '—') ?></td>
                                <td><span class="badge bg-<?= ($record['status'] ?? 'present') === 'present' ? 'success' : (($record['status'] ?? '') === 'absent' ? 'danger' : 'warning') ?>"><?= e($record['status'] ?? 'present') ?></span></td>
                                <td><?= e($record['date'] ?? '-') ?></td>
                                <td class="text-nowrap"><a class="btn btn-sm btn-outline-primary" href="<?= url('views/admin/attendance/view.php?id=' . urlencode((string) ($record['id'] ?? ''))) ?>">View</a> <a class="btn btn-sm btn-outline-secondary" href="<?= url('views/admin/attendance/edit.php?id=' . urlencode((string) ($record['id'] ?? ''))) ?>">Edit</a> <a class="btn btn-sm btn-outline-danger" href="<?= url('views/admin/attendance/delete.php?id=' . urlencode((string) ($record['id'] ?? ''))) ?>">Delete</a></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
<?php require APP_ROOT . '/app/views/components/footer.php'; ?>