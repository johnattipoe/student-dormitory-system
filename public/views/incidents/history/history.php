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
$allowedRoles = [ROLE_ADMIN, ROLE_HOUSE_MASTER, ROLE_SENIOR_HOUSEPARENT, ROLE_SECURITY, ROLE_NURSE];
require APP_ROOT . '/app/middleware/RoleMiddleware/RoleMiddleware.php';

use App\Services\FirebaseService;

$pageTitle = 'Incident History';
$incidents = FirebaseService::getInstance()->getCollection(COL_INCIDENTS, [], 200);
$navItems = [
    ['icon' => 'bi-speedometer2', 'label' => 'Dashboard', 'href' => url('views/admin/dashboard.php')],
    ['icon' => 'bi-clock-history', 'label' => 'Incident History', 'href' => url('views/incidents/history/history.php'), 'active' => true],
];

require APP_ROOT . '/app/views/components/header/header.php';
require APP_ROOT . '/app/views/components/sidebar/sidebar.php';
?>
<div class="main-content">
    <?php require APP_ROOT . '/app/views/components/navbar/navbar.php'; ?>
    <div class="content-wrapper">
        <h5 class="mb-3">Incident History</h5>
        <div class="card stat-card p-3">
            <table class="table table-hover w-100">
                <thead>
                <tr><th>Title</th><th>Priority</th><th>Status</th><th>Student</th></tr>
                </thead>
                <tbody>
                <?php foreach ($incidents as $incident): ?>
                    <tr>
                        <td><?= e($incident['title'] ?? '') ?></td>
                        <td><?= e($incident['priority'] ?? '') ?></td>
                        <td><?= e($incident['status'] ?? '') ?></td>
                        <td><?= e($incident['studentId'] ?? '-') ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
<?php require APP_ROOT . '/app/views/components/footer/footer.php'; ?>
