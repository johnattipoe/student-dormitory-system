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
$allowedRoles = [ROLE_ADMIN, ROLE_HOUSE_MASTER, ROLE_SENIOR_HOUSEPARENT, ROLE_SECURITY];
require APP_ROOT . '/app/middleware/RoleMiddleware/RoleMiddleware.php';

use App\Services\FirebaseService;

$pageTitle = 'Visitors';
$visitors = FirebaseService::getInstance()->getCollection(COL_VISITORS, [], 200);
$navItems = [
    ['icon' => 'bi-speedometer2', 'label' => 'Dashboard', 'href' => url('views/admin/dashboard.php')],
    ['icon' => 'bi-person-badge', 'label' => 'Visitors', 'href' => url('views/visitors/index/index.php'), 'active' => true],
];

require APP_ROOT . '/app/views/components/header/header.php';
require APP_ROOT . '/app/views/components/sidebar/sidebar.php';
?>
<div class="main-content">
    <?php require APP_ROOT . '/app/views/components/navbar/navbar.php'; ?>
    <?php require APP_ROOT . '/app/views/components/alerts/alerts.php'; ?>
    <div class="content-wrapper">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <h5 class="mb-0">Visitors</h5>
            <a href="<?= url('views/visitors/check-in/check-in.php') ?>" class="btn btn-primary btn-sm">Check In</a>
        </div>

        <div class="card stat-card p-3">
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
                <?php foreach ($visitors as $visitor): ?>
                    <tr>
                        <td><?= e($visitor['visitorName'] ?? '') ?></td>
                        <td><?= e($visitor['studentId'] ?? '-') ?></td>
                        <td><?= e($visitor['purpose'] ?? '-') ?></td>
                        <td><span class="badge bg-<?= ($visitor['status'] ?? '') === 'inside' ? 'success' : 'secondary' ?>"><?= e($visitor['status'] ?? '-') ?></span></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
<?php require APP_ROOT . '/app/views/components/footer/footer.php'; ?>
