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
use App\Services\IncidentService;

$pageTitle = 'Incidents';
$incidentService = new IncidentService();
$incidents = $incidentService->all();
$search = strtolower(sanitize($_GET['search'] ?? ''));
if ($search !== '') $incidents = array_values(array_filter($incidents, fn($incident) => str_contains(strtolower((string) ($incident['title'] ?? '')), $search) || str_contains(strtolower((string) ($incident['studentId'] ?? '')), $search)));
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
            <div><a href="<?= url('views/admin/incidents/reports.php') ?>" class="btn btn-sm btn-outline-primary">Reports</a><form method="GET" class="d-inline-flex gap-2 ms-2"><input name="search" class="form-control form-control-sm" placeholder="Search incidents" value="<?= e($search) ?>"><button class="btn btn-primary btn-sm">Filter</button></form></div>
        </div>
        <div class="card stat-card p-3">
            <table class="table table-hover data-table w-100">
                <thead>
                <tr>
                    <th>Title</th>
                    <th>Priority</th>
                    <th>Status</th>
                    <th>Reported By</th>
                    <th>Actions</th>
                </tr>
                </thead>
                <tbody>
                <?php foreach ($incidents as $incident): ?>
                    <tr>
                        <td><?= e($incident['title'] ?? '') ?></td>
                        <td><?= e($incident['priority'] ?? '') ?></td>
                        <td><span class="badge bg-<?= ($incident['status'] ?? '') === 'open' ? 'danger' : 'success' ?>"><?= e($incident['status'] ?? '') ?></span></td>
                        <td><?= e($incident['reportedBy'] ?? '-') ?></td>
                        <td><a class="btn btn-sm btn-outline-primary" href="<?= url('views/admin/incidents/view.php?id=' . urlencode((string) ($incident['id'] ?? ''))) ?>">View</a> <a class="btn btn-sm btn-outline-secondary" href="<?= url('views/admin/incidents/edit.php?id=' . urlencode((string) ($incident['id'] ?? ''))) ?>">Edit</a> <a class="btn btn-sm btn-outline-danger" href="<?= url('views/admin/incidents/delete.php?id=' . urlencode((string) ($incident['id'] ?? ''))) ?>">Delete</a></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
<?php require APP_ROOT . '/app/views/components/footer.php'; ?>