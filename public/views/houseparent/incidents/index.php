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

use App\Services\IncidentService;
use App\Services\StudentService;

$houseId = current_user()['houseId'] ?? null;
$students = StudentService::all($houseId);
$studentMap = [];
foreach ($students as $student) {
    $studentMap[(string) ($student['id'] ?? '')] = $student;
}

$incidents = (new IncidentService())->byHouse($houseId);
$openIncidents = array_values(array_filter($incidents, fn($incident) => ($incident['status'] ?? 'open') === 'open'));
$resolvedIncidents = array_values(array_filter($incidents, fn($incident) => ($incident['status'] ?? '') === 'resolved'));

$pageTitle = 'Houseparent Incidents';
$navItems = [
    ['icon' => 'bi-speedometer2', 'label' => 'Dashboard', 'href' => url('views/houseparent/dashboard/index.php')],
    ['icon' => 'bi-mortarboard', 'label' => 'Students', 'href' => url('views/houseparent/students/index.php')],
    ['icon' => 'bi-calendar-check', 'label' => 'Attendance', 'href' => url('views/houseparent/attendance/index.php')],
    ['icon' => 'bi-door-open', 'label' => 'Rooms', 'href' => url('views/houseparent/rooms/index.php')],
    ['icon' => 'bi-people', 'label' => 'Visitors', 'href' => url('views/houseparent/visitors/index.php')],
    ['icon' => 'bi-shield-exclamation', 'label' => 'Incidents', 'href' => url('views/houseparent/incidents/index.php'), 'active' => true],
    ['icon' => 'bi-bell', 'label' => 'Notifications', 'href' => url('views/houseparent/notifications/index.php')],
];

require APP_ROOT . '/app/views/components/header.php';
require APP_ROOT . '/app/views/components/sidebar.php';
?>
<div class="main-content">
    <?php require APP_ROOT . '/app/views/components/navbar.php'; ?>
    <?php require APP_ROOT . '/app/views/components/alerts.php'; ?>
    <div class="content-wrapper">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <h5 class="mb-0">Incident Reports</h5>
            <span class="badge bg-secondary bg-opacity-10 text-secondary"><?= count($incidents) ?> total</span>
        </div>

        <div class="row g-3 mb-4">
            <div class="col-md-4">
                <div class="card stat-card p-3 text-center h-100">
                    <div class="text-muted small">Total</div>
                    <div class="fs-2 fw-bold"><?= e((string) count($incidents)) ?></div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card stat-card p-3 text-center h-100">
                    <div class="text-muted small">Open</div>
                    <div class="fs-2 fw-bold"><?= e((string) count($openIncidents)) ?></div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card stat-card p-3 text-center h-100">
                    <div class="text-muted small">Resolved</div>
                    <div class="fs-2 fw-bold"><?= e((string) count($resolvedIncidents)) ?></div>
                </div>
            </div>
        </div>

        <div class="card stat-card p-3">
            <table class="table table-hover data-table w-100">
                <thead>
                    <tr>
                        <th>Title</th>
                        <th>Student</th>
                        <th>Priority</th>
                        <th>Status</th>
                        <th>Reported By</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!empty($incidents)): ?>
                        <?php foreach ($incidents as $incident): ?>
                            <?php $incidentStudent = $studentMap[(string) ($incident['studentId'] ?? '')] ?? null; ?>
                            <tr>
                                <td><?= e($incident['title'] ?? 'Incident') ?></td>
                                <td><?= e(trim((($incidentStudent['firstName'] ?? '') . ' ' . ($incidentStudent['lastName'] ?? '')))) ?: e($incident['studentId'] ?? '—') ?></td>
                                <td><?= e($incident['priority'] ?? 'medium') ?></td>
                                <td><span class="badge bg-<?= ($incident['status'] ?? '') === 'resolved' ? 'success' : (($incident['status'] ?? '') === 'open' ? 'warning' : 'secondary') ?>"><?= e($incident['status'] ?? 'open') ?></span></td>
                                <td><?= e($incident['reportedByName'] ?? ($incident['reportedBy'] ?? '—')) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="5" class="text-center text-muted">No incidents found for your house.</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
<?php require APP_ROOT . '/app/views/components/footer.php'; ?>