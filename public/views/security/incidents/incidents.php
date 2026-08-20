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
$allowedRoles = [ROLE_SECURITY];
require APP_ROOT . '/app/middleware/RoleMiddleware.php';

use App\Services\IncidentService;

$incidents = (new IncidentService())->all();
$search = strtolower(sanitize($_GET['search'] ?? ''));
if ($search !== '') $incidents = array_values(array_filter($incidents, fn($incident) => str_contains(strtolower((string) ($incident['title'] ?? '')), $search) || str_contains(strtolower((string) ($incident['studentId'] ?? '')), $search)));

$pageTitle = 'Security Incidents';
$navItems = [
    ['icon' => 'bi-speedometer2', 'label' => 'Dashboard', 'href' => url('views/security/dashboard/dashboard.php')],
    ['icon' => 'bi-people', 'label' => 'Visitors', 'href' => url('views/security/visitors/visitors.php')],
    ['icon' => 'bi-journal-text', 'label' => 'Visitor History', 'href' => url('views/security/visitor-history/visitor-history.php')],
    ['icon' => 'bi-exclamation-triangle', 'label' => 'Incidents', 'href' => url('views/security/incidents/incidents.php'), 'active' => true],
    ['icon' => 'bi-bell', 'label' => 'Notifications', 'href' => url('views/security/notifications/notifications.php')],
    ['icon' => 'bi-person-plus', 'label' => 'Register Visitor', 'href' => url('views/security/register-visitor/register-visitor.php')],
];

require APP_ROOT . '/app/views/components/header.php';
require APP_ROOT . '/app/views/components/sidebar.php';
?>
<div class="main-content">
    <?php require APP_ROOT . '/app/views/components/navbar.php'; ?>
    <?php require APP_ROOT . '/app/views/components/alerts.php'; ?>
    <div class="content-wrapper">
        <div class="card stat-card p-3">
            <div class="d-flex justify-content-between align-items-center mb-3"><h5 class="mb-0">Incident Log</h5><form method="GET" class="d-flex gap-2"><input name="search" class="form-control form-control-sm" placeholder="Search incidents" value="<?= e($search) ?>"><button class="btn btn-primary btn-sm">Filter</button></form></div>
            <table class="table table-hover data-table w-100">
                <thead>
                    <tr>
                        <th>Title</th>
                        <th>Student</th>
                        <th>Priority</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!empty($incidents)): ?>
                        <?php foreach ($incidents as $incident): ?>
                            <tr>
                                <td><?= e($incident['title'] ?? '') ?></td>
                                <td><?= e($incident['studentId'] ?? '—') ?></td>
                                <td><?= e($incident['priority'] ?? 'medium') ?></td>
                                <td><span class="badge bg-warning"><?= e($incident['status'] ?? 'open') ?></span></td>
                                <td><a class="btn btn-sm btn-outline-primary" href="<?= url('views/security/incidents/view.php?id=' . urlencode((string) ($incident['id'] ?? ''))) ?>">View</a> <a class="btn btn-sm btn-outline-secondary" href="<?= url('views/security/incidents/edit.php?id=' . urlencode((string) ($incident['id'] ?? ''))) ?>">Edit</a> <a class="btn btn-sm btn-outline-danger" href="<?= url('views/security/incidents/delete.php?id=' . urlencode((string) ($incident['id'] ?? ''))) ?>">Delete</a></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="5" class="text-center text-muted">No incidents recorded.</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
<?php require APP_ROOT . '/app/views/components/footer.php'; ?>
