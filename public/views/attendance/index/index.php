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
$allowedRoles = [ROLE_ADMIN, ROLE_HOUSE_MASTER, ROLE_SENIOR_HOUSEPARENT, ROLE_SECURITY, ROLE_NURSE, ROLE_STUDENT];
require APP_ROOT . '/app/middleware/RoleMiddleware/RoleMiddleware.php';

use App\Services\FirebaseService;
use App\Services\AttendanceService;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'mark_attendance') {
    if (!in_array(current_role(), [ROLE_ADMIN, ROLE_HOUSE_MASTER, ROLE_SENIOR_HOUSEPARENT, ROLE_SECURITY], true)) {
        http_response_code(403);
        exit('You are not authorized to mark attendance.');
    }
    if (!verify_csrf_token($_POST['csrf_token'] ?? null)) {
        flash('error', 'The form expired. Please refresh the page and try again.');
        redirect(url('views/attendance/index/index.php'));
    }

    $result = AttendanceService::mark([
        'studentId' => sanitize($_POST['studentId'] ?? ''),
        'status' => sanitize($_POST['status'] ?? ''),
        'date' => sanitize($_POST['date'] ?? date('Y-m-d')),
        'markedBy' => current_user()['uid'] ?? null,
    ]);
    flash($result['success'] ? 'success' : 'error', $result['message']);
    redirect(url('views/attendance/index/index.php'));
}

$pageTitle = 'Attendance';
$page = max(1, (int) ($_GET['page'] ?? 1));
$limit = max(1, min(150, (int) ($_GET['limit'] ?? app_config()['pagination_per_page'] ?? 25)));
$firebase = FirebaseService::getInstance();
$totalRecords = $firebase->count(COL_ATTENDANCE);
$totalPages = max(1, (int) ceil($totalRecords / $limit));
$page = min($page, $totalPages);
$records = $firebase->getCollection(
    COL_ATTENDANCE,
    [],
    $limit,
    ($page - 1) * $limit,
    false,
    [['createdAt', 'DESC']]
);
$navItems = [
    ['icon' => 'bi-speedometer2', 'label' => 'Dashboard', 'href' => url('views/admin/dashboard.php')],
    ['icon' => 'bi-calendar-check', 'label' => 'Attendance', 'href' => url('views/attendance/index/index.php'), 'active' => true],
];

require APP_ROOT . '/app/views/components/header/header.php';
require APP_ROOT . '/app/views/components/sidebar/sidebar.php';
?>
<div class="main-content">
    <?php require APP_ROOT . '/app/views/components/navbar/navbar.php'; ?>
    <?php require APP_ROOT . '/app/views/components/alerts/alerts.php'; ?>
    <div class="content-wrapper">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <h5 class="mb-0">Attendance</h5>
            <?php if (in_array(current_role(), [ROLE_ADMIN, ROLE_HOUSE_MASTER, ROLE_SENIOR_HOUSEPARENT, ROLE_SECURITY], true)): ?>
                <button type="button" class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#markAttendanceModal">Mark Attendance</button>
            <?php endif; ?>
        </div>

        <div class="card stat-card p-3">
            <table class="table table-hover data-table w-100">
                <thead>
                <tr>
                    <th>Student</th>
                    <th>Date</th>
                    <th>Status</th>
                    <th>Marked By</th>
                    <th>Actions</th>
                </tr>
                </thead>
                <tbody>
                <?php foreach ($records as $r): ?>
                    <?php
                        $attendanceId = (string) ($r['id'] ?? '');
                        if ($attendanceId === '') {
                            $attendanceId = (string) (($r['studentId'] ?? '') . '-' . ($r['date'] ?? ''));
                        }
                    ?>
                    <tr>
                        <td><?= e($r['studentId'] ?? '-') ?></td>
                        <td><?= e($r['date'] ?? '-') ?></td>
                        <td><span class="badge bg-<?= ($r['status'] ?? '') === 'present' ? 'success' : 'warning' ?>"><?= e($r['status'] ?? '-') ?></span></td>
                        <td><?= e($r['markedBy'] ?? '-') ?></td>
                        <td class="text-nowrap">
                            <button type="button" class="btn btn-sm btn-outline-secondary" data-bs-toggle="modal" data-bs-target="#attendanceViewModal-<?= e($attendanceId) ?>"><i class="bi bi-eye"></i></button>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
            <?php $paginationBaseUrl = url('views/attendance/index/index.php'); require APP_ROOT . '/app/views/components/pagination/pagination.php'; ?>
        </div>
    </div>
</div>
<div class="modal fade" id="markAttendanceModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered"><div class="modal-content">
        <form method="POST" action="<?= url('views/attendance/index/index.php') ?>">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="mark_attendance">
            <div class="modal-header"><h5 class="modal-title">Mark Attendance</h5><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button></div>
            <div class="modal-body">
                <div class="mb-3"><label class="form-label">Student</label><select name="studentId" class="form-select" data-attendance-student-options="<?= e(url('views/attendance/student-options.php')) ?>" required><option value="">Select student</option></select></div>
                <div class="mb-3"><label class="form-label">Date</label><input type="date" name="date" class="form-control" value="<?= e(date('Y-m-d')) ?>" required></div>
                <div><label class="form-label">Status</label><select name="status" class="form-select" required><option value="present">Present</option><option value="absent">Absent</option><option value="late">Late</option><option value="excused">Excused</option></select></div>
            </div>
            <div class="modal-footer"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button><button type="submit" class="btn btn-primary">Save Attendance</button></div>
        </form>
    </div></div>
</div>
<?php foreach ($records as $r): ?>
    <?php
        $attendanceId = (string) ($r['id'] ?? '');
        if ($attendanceId === '') {
            $attendanceId = (string) (($r['studentId'] ?? '') . '-' . ($r['date'] ?? ''));
        }
    ?>
    <div class="modal fade" id="attendanceViewModal-<?= e($attendanceId) ?>" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Attendance Details</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <dl class="row mb-0">
                        <dt class="col-sm-4">Student</dt>
                        <dd class="col-sm-8"><?= e($r['studentId'] ?? '-') ?></dd>
                        <dt class="col-sm-4">Date</dt>
                        <dd class="col-sm-8"><?= e($r['date'] ?? '-') ?></dd>
                        <dt class="col-sm-4">Status</dt>
                        <dd class="col-sm-8"><span class="badge bg-<?= ($r['status'] ?? '') === 'present' ? 'success' : 'warning' ?>"><?= e($r['status'] ?? '-') ?></span></dd>
                        <dt class="col-sm-4">Marked By</dt>
                        <dd class="col-sm-8"><?= e($r['markedBy'] ?? '-') ?></dd>
                    </dl>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>
<?php endforeach; ?>
<?php require APP_ROOT . '/app/views/components/footer/footer.php'; ?>
