<?php
require __DIR__ . '/../../bootstrap.php';
$allowedRoles = [ROLE_HOUSEPARENT];
require APP_ROOT . '/app/middleware/RoleMiddleware.php';

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
        </div>
        <div class="card stat-card p-3">
            <table class="table table-hover data-table w-100">
                <thead>
                    <tr>
                        <th>Title</th>
                        <th>Student</th>
                        <th>Status</th>
                        <th>Reported By</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!empty($incidents)): ?>
                        <?php foreach ($incidents as $incident): ?>
                            <tr>
                                <td><?= e($incident['title'] ?? '') ?></td>
                                <td><?= e($incident['studentId'] ?? '') ?></td>
                                <td><span class="badge bg-<?= ($incident['status'] ?? '') === 'open' ? 'warning' : 'secondary' ?>"><?= e($incident['status'] ?? 'unknown') ?></span></td>
                                <td><?= e($incident['reportedByName'] ?? ($incident['reportedBy'] ?? '—')) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="4" class="text-center text-muted">No incidents found for your house.</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
<?php require APP_ROOT . '/app/views/components/footer.php'; ?>