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
$allowedRoles = [ROLE_ADMIN, ROLE_SECURITY, ROLE_SENIOR_HOUSEPARENT];
require APP_ROOT . '/app/middleware/RoleMiddleware/RoleMiddleware.php';

use App\Services\FirebaseService;

$pageTitle = 'Visitor History';
$visitors = FirebaseService::getInstance()->getCollection(COL_VISITORS, [], 200);
$navItems = [
    ['icon' => 'bi-speedometer2', 'label' => 'Dashboard', 'href' => url('views/admin/dashboard.php')],
    ['icon' => 'bi-clock-history', 'label' => 'Visitor History', 'href' => url('views/visitors/history/history.php'), 'active' => true],
];

require APP_ROOT . '/app/views/components/header/header.php';
require APP_ROOT . '/app/views/components/sidebar/sidebar.php';
?>
<div class="main-content">
    <?php require APP_ROOT . '/app/views/components/navbar/navbar.php'; ?>
    <div class="content-wrapper">
        <h5 class="mb-3">Visitor History</h5>
        <div class="card stat-card p-3">
            <table class="table table-hover w-100">
                <thead>
                <tr><th>Name</th><th>Student</th><th>Purpose</th><th>Status</th></tr>
                </thead>
                <tbody>
                <?php foreach ($visitors as $visitor): ?>
                    <tr>
                        <td><?= e($visitor['visitorName'] ?? '') ?></td>
                        <td><?= e($visitor['studentId'] ?? '-') ?></td>
                        <td><?= e($visitor['purpose'] ?? '-') ?></td>
                        <td><?= e($visitor['status'] ?? '-') ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
<?php require APP_ROOT . '/app/views/components/footer/footer.php'; ?>
