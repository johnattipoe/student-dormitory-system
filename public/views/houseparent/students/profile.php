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

use App\Services\StudentService;

$studentId = sanitize($_GET['studentId'] ?? '');
$student = $studentId ? StudentService::find($studentId) : null;
$students = StudentService::all(current_user()['houseId'] ?? null);

use App\Services\AttendanceService;
use App\Services\IncidentService;
use App\Services\VisitorService;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'flag_student') {
    $flag = sanitize($_POST['flag'] ?? '');
    $reason = sanitize($_POST['reason'] ?? '');
    if ($studentId && $flag) {
        try {
            StudentService::updateFlags($studentId, ['flagged' => true, 'flagReason' => $reason, 'flaggedAt' => date('Y-m-d H:i:s'), 'flaggedBy' => current_user()['uid']]);
            flash('success', 'Student flagged for attention');
            redirect(base_url('index.php?route=/views/houseparent/students/profile.php?studentId=' . urlencode($studentId)));
        } catch (Exception $e) {
            flash('error', 'Failed to flag student: ' . $e->getMessage());
        }
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'unflag_student') {
    if ($studentId) {
        try {
            StudentService::updateFlags($studentId, ['flagged' => false]);
            flash('success', 'Student flag removed');
            redirect(base_url('index.php?route=/views/houseparent/students/profile.php?studentId=' . urlencode($studentId)));
        } catch (Exception $e) {
            flash('error', 'Failed to remove flag: ' . $e->getMessage());
        }
    }
}

$recentAttendance = $studentId ? AttendanceService::history($studentId, 10) : [];
$studentIncidents = $studentId ? (new IncidentService())->studentIncidents($studentId) : [];
$studentVisitors = $studentId ? (new VisitorService())->studentVisitors($studentId) : [];

$attendanceSummary = [
    'present' => 0,
    'absent' => 0,
    'late' => 0,
    'excused' => 0,
];
foreach ($recentAttendance as $record) {
    $status = (string) ($record['status'] ?? 'present');
    if (isset($attendanceSummary[$status])) {
        $attendanceSummary[$status]++;
    }
}

$attendanceRate = 0;
if (!empty($recentAttendance)) {
    $presentDays = $attendanceSummary['present'] + $attendanceSummary['excused'];
    $attendanceRate = round(($presentDays / count($recentAttendance)) * 100);
}

$pageTitle = 'Student Profile';
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
                <h5 class="mb-0">Student Profile</h5>
                <a href="<?= url('views/houseparent/students/index.php') ?>" class="btn btn-outline-secondary btn-sm">Back to students</a>
            </div>

            <?php if (!$student): ?>
                <p class="text-muted">Select a student from the list to view their profile.</p>
                <div class="list-group">
                    <?php foreach ($students as $item): ?>
                        <a href="<?= url('views/houseparent/students/profile.php?studentId=' . urlencode((string) ($item['id'] ?? ''))) ?>" class="list-group-item list-group-item-action">
                            <?= e(trim(($item['firstName'] ?? '') . ' ' . ($item['lastName'] ?? ''))) ?> (<?= e($item['admissionNo'] ?? '—') ?>)
                        </a>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <?php if ($student['flagged'] ?? false): ?>
                    <div class="alert alert-warning d-flex justify-content-between align-items-center" role="alert">
                        <div>
                            <strong>⚠ Student Flagged</strong>
                            <p class="mb-0 small"><?= e($student['flagReason'] ?? 'No reason provided') ?></p>
                        </div>
                        <form method="POST" class="d-inline">
                            <input type="hidden" name="action" value="unflag_student">
                            <button class="btn btn-sm btn-outline-warning">Remove Flag</button>
                        </form>
                    </div>
                <?php endif; ?>

                <div class="row g-3 mb-4">
                    <div class="col-md-3">
                        <div class="card stat-card p-3 text-center h-100">
                            <div class="text-muted small">Attendance Rate</div>
                            <div class="fs-2 fw-bold text-<?= $attendanceRate >= 80 ? 'success' : ($attendanceRate >= 70 ? 'warning' : 'danger') ?>"><?= e((string) $attendanceRate) ?>%</div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card stat-card p-3 text-center h-100">
                            <div class="text-muted small">Present</div>
                            <div class="fs-2 fw-bold"><?= e((string) $attendanceSummary['present']) ?></div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card stat-card p-3 text-center h-100">
                            <div class="text-muted small">Absent</div>
                            <div class="fs-2 fw-bold text-danger"><?= e((string) $attendanceSummary['absent']) ?></div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card stat-card p-3 text-center h-100">
                            <div class="text-muted small">Incidents</div>
                            <div class="fs-2 fw-bold"><?= e((string) count($studentIncidents)) ?></div>
                        </div>
                    </div>
                </div>

                <div class="mb-3">
                    <div class="btn-group" role="group">
                        <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-toggle="modal" data-bs-target="#contactModal">📧 Contact Guardian</button>
                        <button type="button" class="btn btn-outline-warning btn-sm" data-bs-toggle="modal" data-bs-target="#flagModal">🚩 Flag Student</button>
                        <a href="mailto:<?= e($student['email'] ?? '') ?>" class="btn btn-outline-primary btn-sm">✉️ Email Student</a>
                    </div>
                </div>

                <div class="row g-3 mt-1">
                    <div class="col-md-6">
                        <div class="card p-3 h-100">
                            <h6>Basic Details</h6>
                            <p class="mb-1"><strong>Name:</strong> <span><?= e(trim(($student['firstName'] ?? '') . ' ' . ($student['lastName'] ?? ''))) ?></span></p>
                            <p class="mb-1"><strong>Admission No.:</strong> <span><?= e($student['admissionNo'] ?? '—') ?></span></p>
                            <p class="mb-1"><strong>Course:</strong> <span><?= e($student['course'] ?? '—') ?></span></p>
                            <p class="mb-1"><strong>Level:</strong> <span><?= e($student['level'] ?? '—') ?></span></p>
                            <p class="mb-1"><strong>House:</strong> <span><?= e($student['houseId'] ?? '—') ?></span></p>
                            <p class="mb-1"><strong>Status:</strong> <span><?= e($student['status'] ?? 'active') ?></span></p>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="card p-3 h-100">
                            <h6>Contact</h6>
                            <p class="mb-1"><strong>Email:</strong> <span><?= e($student['email'] ?? '—') ?></span></p>
                            <p class="mb-1"><strong>Phone:</strong> <span><?= e($student['phone'] ?? '—') ?></span></p>
                            <p class="mb-1"><strong>Guardian:</strong> <span><?= e($student['guardianName'] ?? '—') ?></span></p>
                            <p class="mb-1"><strong>Guardian Phone:</strong> <span><?= e($student['guardianPhone'] ?? '—') ?></span></p>
                            <p class="mb-1"><strong>Room:</strong> <span><?= e($student['roomId'] ?? '—') ?></span></p>
                            <p class="mb-1"><strong>Gender:</strong> <span><?= e($student['gender'] ?? '—') ?></span></p>
                        </div>
                    </div>
                </div>

                <div class="row g-3 mt-2">
                    <div class="col-lg-6">
                        <div class="card p-3 h-100">
                            <h6>Recent attendance</h6>
                            <div class="table-responsive">
                                <table class="table table-sm table-hover mb-0">
                                    <thead>
                                        <tr>
                                            <th>Date</th>
                                            <th>Status</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php if (empty($recentAttendance)): ?>
                                            <tr><td colspan="2" class="text-center text-muted">No recent attendance.</td></tr>
                                        <?php else: ?>
                                            <?php foreach ($recentAttendance as $record): ?>
                                                <tr>
                                                    <td><?= e($record['date'] ?? '-') ?></td>
                                                    <td><span class="badge bg-<?= ($record['status'] ?? 'present') === 'present' ? 'success' : (($record['status'] ?? '') === 'absent' ? 'danger' : 'warning') ?>"><?= e($record['status'] ?? 'present') ?></span></td>
                                                </tr>
                                            <?php endforeach; ?>
                                        <?php endif; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>

                    <div class="col-lg-6">
                        <div class="card p-3 h-100">
                            <h6>Visitor activity</h6>
                            <div class="table-responsive">
                                <table class="table table-sm table-hover mb-0">
                                    <thead>
                                        <tr>
                                            <th>Name</th>
                                            <th>Status</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php if (empty($studentVisitors)): ?>
                                            <tr><td colspan="2" class="text-center text-muted">No visitor records.</td></tr>
                                        <?php else: ?>
                                            <?php foreach (array_slice($studentVisitors, 0, 5) as $visitor): ?>
                                                <tr>
                                                    <td><?= e($visitor['visitorName'] ?? '—') ?></td>
                                                    <td><span class="badge bg-secondary"><?= e($visitor['status'] ?? 'pending') ?></span></td>
                                                </tr>
                                            <?php endforeach; ?>
                                        <?php endif; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endif; ?>

            <!-- Contact Guardian Modal -->
            <div class="modal fade" id="contactModal" tabindex="-1">
                <div class="modal-dialog">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h5 class="modal-title">Contact Guardian</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                        </div>
                        <div class="modal-body">
                            <p><strong>Guardian:</strong> <?= e($student['guardianName'] ?? '—') ?></p>
                            <p><strong>Phone:</strong> <a href="tel:<?= e($student['guardianPhone'] ?? '') ?>"><?= e($student['guardianPhone'] ?? '—') ?></a></p>
                            <p><strong>Email:</strong> <a href="mailto:<?= e($student['guardianEmail'] ?? '') ?>"><?= e($student['guardianEmail'] ?? '—') ?></a></p>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Flag Student Modal -->
            <div class="modal fade" id="flagModal" tabindex="-1">
                <div class="modal-dialog">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h5 class="modal-title">Flag Student</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                        </div>
                        <form method="POST">
                            <div class="modal-body">
                                <input type="hidden" name="action" value="flag_student">
                                <div class="mb-3">
                                    <label class="form-label">Flag Type</label>
                                    <select name="flag" class="form-select" required>
                                        <option value="">-- Select --</option>
                                        <option value="attendance_concern">Low Attendance</option>
                                        <option value="academic_concern">Academic Concern</option>
                                        <option value="behavioral_concern">Behavioral Concern</option>
                                        <option value="health_concern">Health Concern</option>
                                        <option value="other">Other</option>
                                    </select>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">Reason</label>
                                    <textarea name="reason" class="form-control" placeholder="Provide details about the flag..." required></textarea>
                                </div>
                            </div>
                            <div class="modal-footer">
                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                <button type="submit" class="btn btn-warning">Flag Student</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
<?php require APP_ROOT . '/app/views/components/footer.php'; ?>
