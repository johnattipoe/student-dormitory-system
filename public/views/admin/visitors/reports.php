<?php
require __DIR__ . '/../../../bootstrap.php';
$allowedRoles = [ROLE_ADMIN];
require APP_ROOT . '/app/middleware/RoleMiddleware.php';
use App\Services\VisitorService;

$pageTitle = 'Visitor Reports';
$visitorService = new VisitorService();
$visitors = $visitorService->all();
$statusCounts = [];
foreach ($visitors as $visitor) {
    $status = $visitor['status'] ?? 'unknown';
    $statusCounts[$status] = ($statusCounts[$status] ?? 0) + 1;
}
$navItems = [
    ['icon' => 'bi-speedometer2', 'label' => 'Dashboard', 'href' => url('views/admin/dashboard.php')],
    ['icon' => 'bi-bar-chart', 'label' => 'Reports', 'href' => url('views/admin/visitors/reports.php'), 'active' => true],
];
require APP_ROOT . '/app/views/components/header.php';
require APP_ROOT . '/app/views/components/sidebar.php';
?>
<div class="main-content">
    <?php require APP_ROOT . '/app/views/components/navbar.php'; ?>
    <div class="content-wrapper">
        <div class="mb-4">
            <h5 class="mb-1">Visitor Reports</h5>
            <p class="text-muted">Breakdown by visitor status.</p>
        </div>
        <div class="row g-3">
            <?php foreach ($statusCounts as $status => $count): ?>
                <div class="col-md-3">
                    <div class="card stat-card p-3 text-center">
                        <div class="text-muted small text-uppercase"><?= e($status) ?></div>
                        <div class="fs-2 fw-bold"><?= e($count) ?></div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</div>
<?php require APP_ROOT . '/app/views/components/footer.php'; ?>