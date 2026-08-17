<?php
require __DIR__ . '/../../../bootstrap.php';
$allowedRoles = [ROLE_ADMIN];
require APP_ROOT . '/app/middleware/RoleMiddleware.php';
use App\Services\IncidentService;

$pageTitle = 'Incidents';
$incidentService = new IncidentService();
$incidents = $incidentService->all();
$navItems = [
    ['icon' => 'bi-speedometer2', 'label' => 'Dashboard', 'href' => url('views/admin/dashboard.php')],
    ['icon' => 'bi-exclamation-triangle', 'label' => 'Incidents', 'href' => url('views/admin/incidents/index.php'), 'active' => true],
];
require APP_ROOT . '/app/views/components/header.php';
require APP_ROOT . '/app/views/components/sidebar.php';
?>
<div class="main-content">
    <?php require APP_ROOT . '/app/views/components/navbar.php'; ?>
    <?php require APP_ROOT . '/app/views/components/alerts.php'; ?>
    <div class="content-wrapper">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <h5 class="mb-0">Incidents</h5>
            <a href="<?= url('views/admin/incidents/reports.php') ?>" class="btn btn-sm btn-outline-primary">Reports</a>
        </div>
        <div class="card stat-card p-3">
            <table class="table table-hover data-table w-100">
                <thead>
                <tr>
                    <th>Title</th>
                    <th>Priority</th>
                    <th>Status</th>
                    <th>Reported By</th>
                </tr>
                </thead>
                <tbody>
                <?php foreach ($incidents as $incident): ?>
                    <tr>
                        <td><?= e($incident['title'] ?? '') ?></td>
                        <td><?= e($incident['priority'] ?? '') ?></td>
                        <td><span class="badge bg-<?= ($incident['status'] ?? '') === 'open' ? 'danger' : 'success' ?>"><?= e($incident['status'] ?? '') ?></span></td>
                        <td><?= e($incident['reportedBy'] ?? '-') ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
<?php require APP_ROOT . '/app/views/components/footer.php'; ?>