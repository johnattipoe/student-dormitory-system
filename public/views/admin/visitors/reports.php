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
        <div class="card stat-card p-3 mt-4"><div class="d-flex justify-content-between align-items-center mb-3"><h6 class="mb-0">Visitor records</h6><a class="btn btn-success btn-sm" href="<?= url('reports/export.php?type=visitors&format=csv') ?>">Download CSV</a></div><table class="table table-hover"><thead><tr><th>Visitor</th><th>Student</th><th>Status</th><th>Created</th></tr></thead><tbody><?php foreach ($visitors as $visitor): ?><tr><td><?= e($visitor['visitorName'] ?? '-') ?></td><td><?= e($visitor['studentId'] ?? '-') ?></td><td><?= e($visitor['status'] ?? '-') ?></td><td><?= e($visitor['createdAt'] ?? '-') ?></td></tr><?php endforeach; ?></tbody></table></div>
    </div>
</div>
<?php require APP_ROOT . '/app/views/components/footer.php'; ?>