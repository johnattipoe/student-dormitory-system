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
$allowedRoles = [ROLE_SENIOR_HOUSEPARENT];
require APP_ROOT . '/app/middleware/RoleMiddleware/RoleMiddleware.php';

use App\Services\StudentService;
use App\Services\RoomService;
use App\Services\HouseService;
use App\Services\AttendanceService;
use App\Services\IncidentService;
use App\Services\VisitorService;

$studentId = sanitize($_GET['studentId'] ?? '');
$student = $studentId ? StudentService::find($studentId) : null;
$students = StudentService::all(current_user()['houseId'] ?? null);
$room = ($student && !empty($student['roomId'])) ? RoomService::find((string) $student['roomId']) : null;
$roomName = $room['roomNumber'] ?? ($student['roomId'] ?? '—');
$houseId = (string) ($student['houseId'] ?? '');
$house = $houseId !== '' ? HouseService::find($houseId) : null;
$houseName = $house['name'] ?? ($houseId !== '' ? $houseId : '—');

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'flag_student') {
    $flag = sanitize($_POST['flag'] ?? '');
    $reason = sanitize($_POST['reason'] ?? '');
    if ($studentId && $flag) {
        try {
            StudentService::updateFlags($studentId, [
                'flagged' => true,
                'flagType' => $flag,
                'flagReason' => $reason,
                'flaggedAt' => date('Y-m-d H:i:s'),
                'flaggedBy' => current_user()['uid'],
            ]);
            flash('success', 'Student flagged for attention');
            redirect(url('views/senior-houseparent/students/profile/profile.php?studentId=' . urlencode($studentId)));
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
            redirect(url('views/senior-houseparent/students/profile/profile.php?studentId=' . urlencode($studentId)));
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
    ['icon' => 'bi-speedometer2', 'label' => 'Dashboard', 'href' => url('views/senior-houseparent/dashboard/index.php')],
    ['icon' => 'bi-mortarboard', 'label' => 'Students', 'href' => url('views/senior-houseparent/students/index/index.php'), 'active' => true],
    ['icon' => 'bi-calendar-check', 'label' => 'Attendance', 'href' => url('views/senior-houseparent/attendance/index/index.php')],
    ['icon' => 'bi-door-open', 'label' => 'Rooms', 'href' => url('views/senior-houseparent/rooms/index/index.php')],
    ['icon' => 'bi-people', 'label' => 'Visitors', 'href' => url('views/senior-houseparent/visitors/index/index.php')],
    ['icon' => 'bi-shield-exclamation', 'label' => 'Incidents', 'href' => url('views/senior-houseparent/incidents/index/index.php')],
    ['icon' => 'bi-bell', 'label' => 'Notifications', 'href' => url('views/senior-houseparent/notifications/index/index.php')],
];

require APP_ROOT . '/app/views/components/header/header.php';
require APP_ROOT . '/app/views/components/sidebar/sidebar.php';
?>
<div class="main-content">
    <?php require APP_ROOT . '/app/views/components/navbar/navbar.php'; ?>
    <?php require APP_ROOT . '/app/views/components/alerts/alerts.php'; ?>
    <div class="content-wrapper">
        <div class="card stat-card p-4">
            <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
                <h5 class="mb-0">Student Profile</h5>
                <a href="<?= url('views/senior-houseparent/students/index/index.php') ?>" class="btn btn-outline-secondary btn-sm">Back to students</a>
            </div>

            <?php if (!$student): ?>
                <div class="card p-3">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <div>
                            <h6 class="mb-0">Select Student</h6>
                            <p class="text-muted small mb-0">Select a student to view their profile.</p>
                        </div>
                    </div>
                    <div class="list-group list-group-flush">
                    <?php foreach ($students as $item): ?>
                        <a href="<?= url('views/senior-houseparent/students/profile/profile.php?studentId=' . urlencode((string) ($item['id'] ?? ''))) ?>" class="list-group-item list-group-item-action d-flex justify-content-between align-items-center px-0">
                            <span><?= e(trim(($item['firstName'] ?? '') . ' ' . ($item['lastName'] ?? ''))) ?></span>
                            <span class="text-muted small"><?= e($item['admissionNo'] ?? '—') ?><i class="bi bi-chevron-right ms-2" aria-hidden="true"></i></span>
                        </a>
                    <?php endforeach; ?>
                    </div>
                </div>
            <?php else: ?>
                <?php if ($student['flagged'] ?? false): ?>
                    <div class="alert alert-warning d-flex justify-content-between align-items-center gap-3" role="alert">
                        <div>
                            <strong><i class="bi bi-flag-fill me-1"></i>Student Flagged (<?= e($student['flagType'] ?? 'Attention') ?>)</strong>
                            <p class="mb-0 small"><?= e($student['flagReason'] ?? 'No reason provided') ?></p>
                        </div>
                        <form method="POST" class="d-inline">
                            <input type="hidden" name="action" value="unflag_student">
                            <button class="btn btn-sm btn-outline-warning">Remove Flag</button>
                        </form>
                    </div>
                <?php endif; ?>

                <div class="row g-3 mb-4">
                    <div class="col-sm-6 col-xl-3">
                        <div class="card stat-card p-3 text-center h-100">
                            <div class="text-muted small">Attendance Rate</div>
                            <div class="fs-2 fw-bold text-<?= $attendanceRate >= 80 ? 'success' : ($attendanceRate >= 70 ? 'warning' : 'danger') ?>"><?= e((string) $attendanceRate) ?>%</div>
                        </div>
                    </div>
                    <div class="col-sm-6 col-xl-3">
                        <div class="card stat-card p-3 text-center h-100">
                            <div class="text-muted small">Present</div>
                            <div class="fs-2 fw-bold text-success"><?= e((string) $attendanceSummary['present']) ?></div>
                        </div>
                    </div>
                    <div class="col-sm-6 col-xl-3">
                        <div class="card stat-card p-3 text-center h-100">
                            <div class="text-muted small">Absent</div>
                            <div class="fs-2 fw-bold text-danger"><?= e((string) $attendanceSummary['absent']) ?></div>
                        </div>
                    </div>
                    <div class="col-sm-6 col-xl-3">
                        <div class="card stat-card p-3 text-center h-100">
                            <div class="text-muted small">Incidents</div>
                            <div class="fs-2 fw-bold"><?= e((string) count($studentIncidents)) ?></div>
                        </div>
                    </div>
                </div>

                <div class="d-flex flex-wrap gap-2 mb-4">
                    <button type="button" class="btn btn-outline-warning btn-sm" data-bs-toggle="modal" data-bs-target="#flagModal"><i class="bi bi-flag me-1"></i>Flag Student</button>
                </div>

                <div class="row g-3 mt-1">
                    <div class="col-md-6">
                        <div class="card p-3 h-100">
                            <h6 class="d-flex align-items-center gap-2 mb-3"><i class="bi bi-person-vcard text-primary"></i>Academic & Dormitory Details</h6>
                            <p class="mb-1"><strong>Name:</strong> <span><?= e(trim(($student['firstName'] ?? '') . ' ' . ($student['lastName'] ?? ''))) ?></span></p>
                            <p class="mb-1"><strong>Admission No.:</strong> <span><?= e($student['admissionNo'] ?? '—') ?></span></p>
                            <p class="mb-1"><strong>Class:</strong> <span><?= e($student['class'] ?? '—') ?></span></p>
                            <p class="mb-1"><strong>Form / Level:</strong> <span><?= e($student['form'] ?? $student['level'] ?? '—') ?></span></p>
                            <p class="mb-1"><strong>Course:</strong> <span><?= e($student['course'] ?? '—') ?></span></p>
                            <p class="mb-1"><strong>NHIS Number:</strong> <span><?= e($student['nhisNumber'] ?? '—') ?></span></p>
                            <p class="mb-1"><strong>House:</strong> <span><?= e($houseName) ?></span></p>
                            <p class="mb-1"><strong>Room:</strong> <span><?= e($roomName) ?></span></p>
                            <p class="mb-1"><strong>Status:</strong> <span class="badge bg-<?= ($student['status'] ?? '') === 'active' ? 'success' : 'secondary' ?>"><?= e(ucfirst($student['status'] ?? 'active')) ?></span></p>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="card p-3 h-100">
                            <h6 class="d-flex align-items-center gap-2 mb-3"><i class="bi bi-person-lines-fill text-primary"></i>Contact & Guardian Details</h6>
                            <p class="mb-1"><strong>Student Phone:</strong> <span><?= e($student['phone'] ?? '—') ?></span></p>
                            <p class="mb-1"><strong>Gender:</strong> <span><?= e($student['gender'] ?? '—') ?></span></p>
                            <p class="mb-1"><strong>Guardian Name:</strong> <span><?= e($student['guardianName'] ?? '—') ?></span></p>
                            <p class="mb-1"><strong>Guardian Phone:</strong> <span><?= e($student['guardianPhone'] ?? '—') ?></span></p>
                            <p class="mb-1"><strong>Guardian Email:</strong> <span><?= e($student['guardianEmail'] ?? '—') ?></span></p>
                        </div>
                    </div>
                </div>

                <div class="row g-3 mt-2">
                    <div class="col-lg-6">
                        <div class="card p-3 h-100">
                            <h6 class="d-flex align-items-center gap-2 mb-3"><i class="bi bi-calendar-check text-primary"></i>Recent Attendance</h6>
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
                            <h6 class="d-flex align-items-center gap-2 mb-3"><i class="bi bi-shield-exclamation text-primary"></i>Recent Incidents</h6>
                            <div class="table-responsive">
                                <table class="table table-sm table-hover mb-0">
                                    <thead>
                                        <tr>
                                            <th>Title</th>
                                            <th>Priority</th>
                                            <th>Status</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php if (empty($studentIncidents)): ?>
                                            <tr><td colspan="3" class="text-center text-muted">No incidents recorded.</td></tr>
                                        <?php else: ?>
                                            <?php foreach (array_slice($studentIncidents, 0, 5) as $incident): ?>
                                                <tr>
                                                    <td><?= e($incident['title'] ?? $incident['type'] ?? 'Incident') ?></td>
                                                    <td><span class="badge bg-<?= ($incident['priority'] ?? $incident['severity'] ?? '') === 'high' ? 'danger' : 'warning' ?>"><?= e(ucfirst($incident['priority'] ?? $incident['severity'] ?? 'medium')) ?></span></td>
                                                    <td><?= e(ucfirst($incident['status'] ?? 'open')) ?></td>
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
        </div>
    </div>

    <!-- Flag Modal -->
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
                                <option value="Academic">Academic Concern</option>
                                <option value="Disciplinary">Disciplinary Concern</option>
                                <option value="Medical">Medical / Health Watch</option>
                                <option value="Attendance">Attendance Irregularity</option>
                                <option value="General">General Follow-up</option>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Reason / Notes</label>
                            <textarea name="reason" class="form-control" rows="3" required placeholder="Describe the reason for flagging..."></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-warning">Save Flag</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
<?php require APP_ROOT . '/app/views/components/footer/footer.php'; ?>
