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
use App\Services\VisitorService;

$visitorService = new VisitorService();
$incidentService = new IncidentService();
$visitors = $visitorService->all();
$incidents = $incidentService->all();
$insideCount = $visitorService->currentlyInside();
$pendingCount = $visitorService->pendingCount();

$pageTitle = 'Security Dashboard';
$navItems = [
    ['icon' => 'bi-speedometer2', 'label' => 'Dashboard', 'href' => url('views/security/dashboard/dashboard.php'), 'active' => true],
    ['icon' => 'bi-people', 'label' => 'Visitors', 'href' => url('views/security/visitors/visitors.php')],
    ['icon' => 'bi-journal-text', 'label' => 'Visitor History', 'href' => url('views/security/visitor-history/visitor-history.php')],
    ['icon' => 'bi-exclamation-triangle', 'label' => 'Incidents', 'href' => url('views/security/incidents/incidents.php')],
    ['icon' => 'bi-file-earmark-text', 'label' => 'Visitor Report', 'href' => url('reports/export.php?type=visitors&format=pdf')],
    ['icon' => 'bi-file-earmark-text', 'label' => 'Incident Report', 'href' => url('reports/export.php?type=incidents&format=pdf')],
    ['icon' => 'bi-bell', 'label' => 'Notifications', 'href' => url('views/security/notifications/notifications.php')],
];

require APP_ROOT . '/app/views/components/header.php';
require APP_ROOT . '/app/views/components/sidebar.php';
?>
<div class="main-content">
    <?php require APP_ROOT . '/app/views/components/navbar.php'; ?>
    <?php require APP_ROOT . '/app/views/components/alerts.php'; ?>
    <div class="content-wrapper">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <div>
                <h5 class="mb-1">Welcome, <?= e(current_user()['name'] ?? '') ?></h5>
                <p class="text-muted mb-0">Security overview for visitor access and campus monitoring.</p>
            </div>
        </div>

        <div class="row g-3 mb-4">
            <div class="col-md-3">
                <div class="card stat-card p-3 text-center">
                    <div class="text-muted small">Total Visitors</div>
                    <div class="fs-2 fw-bold"><?= e(count($visitors)) ?></div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card stat-card p-3 text-center">
                    <div class="text-muted small">Inside</div>
                    <div class="fs-2 fw-bold"><?= e($insideCount) ?></div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card stat-card p-3 text-center">
                    <div class="text-muted small">Pending</div>
                    <div class="fs-2 fw-bold"><?= e($pendingCount) ?></div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card stat-card p-3 text-center">
                    <div class="text-muted small">Incidents</div>
                    <div class="fs-2 fw-bold"><?= e(count($incidents)) ?></div>
                </div>
            </div>
        </div>

        <div class="card stat-card p-3">
            <h6 class="mb-3">Recent Visitor Activity</h6>
            <table class="table table-hover data-table w-100">
                <thead>
                    <tr>
                        <th>Name</th>
                        <th>Student</th>
                        <th>Purpose</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($visitors)): ?>
                        <tr><td colspan="4" class="text-center text-muted">No visitors registered.</td></tr>
                    <?php else: ?>
                        <?php foreach (array_slice($visitors, 0, 8) as $visitor): ?>
                            <tr>
                                <td><?= e($visitor['visitorName'] ?? '—') ?></td>
                                <td><?= e($visitor['studentId'] ?? '—') ?></td>
                                <td><?= e($visitor['purpose'] ?? '—') ?></td>
                                <td><span class="badge bg-<?= ($visitor['status'] ?? '') === 'inside' ? 'success' : 'secondary' ?>"><?= e($visitor['status'] ?? 'pending') ?></span></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
<?php require APP_ROOT . '/app/views/components/footer.php'; ?>
