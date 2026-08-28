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
use App\Services\IncidentService;
use App\Services\VisitorService;

$studentId = current_user()['studentId'] ?? current_user()['uid'] ?? null;
$attendance = AttendanceService::history($studentId, 200);
$visitors = (new VisitorService())->studentVisitors($studentId);
$incidents = (new IncidentService())->studentIncidents($studentId);
$present = count(array_filter($attendance, fn($r) => ($r['status'] ?? '') === 'present'));

$pageTitle = 'Student Reports';
$navItems = [
    ['icon' => 'bi-speedometer2', 'label' => 'Dashboard', 'href' => url('views/student/dashboard/index.php')],
    ['icon' => 'bi-file-earmark-text', 'label' => 'Reports', 'href' => url('views/student/reports/index.php'), 'active' => true],
    ['icon' => 'bi-calendar-check', 'label' => 'Attendance', 'href' => url('views/student/attendance/index/index.php')],
    ['icon' => 'bi-people', 'label' => 'Visitors', 'href' => url('views/student/visitors/index/index.php')],
    ['icon' => 'bi-flag', 'label' => 'Incidents', 'href' => url('views/student/incidents/index/index.php')],
];

require APP_ROOT . '/app/views/components/header/header.php';
require APP_ROOT . '/app/views/components/sidebar/sidebar.php';
?>
<div class="main-content">
    <?php require APP_ROOT . '/app/views/components/navbar/navbar.php'; ?>
    <div class="content-wrapper">
        <div class="mb-3">
            <h5 class="mb-1">My Reports</h5>
            <p class="text-muted mb-0">Personal activity summaries and downloadable attendance reports.</p>
        </div>
        <div class="row g-3 mb-4">
            <div class="col-md-3">
                <div class="card stat-card p-3">
                    <small class="text-muted">Attendance Records</small>
                    <strong class="fs-2"><?= e((string) count($attendance)) ?></strong>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card stat-card p-3">
                    <small class="text-muted">Days Present</small>
                    <strong class="fs-2 text-success"><?= e((string) $present) ?></strong>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card stat-card p-3">
                    <small class="text-muted">Visitors</small>
                    <strong class="fs-2"><?= e((string) count($visitors)) ?></strong>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card stat-card p-3">
                    <small class="text-muted">Incidents</small>
                    <strong class="fs-2"><?= e((string) count($incidents)) ?></strong>
                </div>
            </div>
        </div>
        <div class="card stat-card p-4" style="max-width: 600px;">
            <h6>Export Attendance History</h6>
            <p class="text-muted">Download your personal attendance records for review.</p>
            <div>
                <a class="btn btn-success" href="<?= url('views/student/attendance/export/export.php') ?>"><i class="bi bi-filetype-csv me-1"></i> Download CSV</a>
            </div>
        </div>
    </div>
</div>
<?php require APP_ROOT . '/app/views/components/footer/footer.php'; ?>