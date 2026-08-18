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
use App\Services\FirebaseService;

$pageTitle = 'Activity Logs';
$logs = FirebaseService::getInstance()->getCollection(COL_ACTIVITY_LOGS, [], 200);
$navItems = [
    ['icon' => 'bi-speedometer2', 'label' => 'Dashboard', 'href' => url('views/admin/dashboard.php')],
    ['icon' => 'bi-list-check', 'label' => 'Activity Logs', 'href' => url('views/admin/activity-logs/index.php'), 'active' => true],
];
require APP_ROOT . '/app/views/components/header.php';
require APP_ROOT . '/app/views/components/sidebar.php';
?>
<div class="main-content">
    <?php require APP_ROOT . '/app/views/components/navbar.php'; ?>
    <?php require APP_ROOT . '/app/views/components/alerts.php'; ?>
    <div class="content-wrapper">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <h5 class="mb-0">Activity Logs</h5>
        </div>
        <div class="card stat-card p-3">
            <table class="table table-hover data-table w-100">
                <thead>
                <tr>
                    <th>Event</th>
                    <th>User</th>
                    <th>Timestamp</th>
                </tr>
                </thead>
                <tbody>
                <?php if (empty($logs)): ?>
                    <tr>
                        <td colspan="3" class="text-muted text-center">No activity logs found.</td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($logs as $log): ?>
                        <?php
                        $event = $log['event'] ?? $log['action'] ?? 'activity';
                        $user = $log['user'] ?? $log['userId'] ?? 'System';
                        $timestamp = $log['timestamp'] ?? $log['createdAt'] ?? $log['updatedAt'] ?? '-';
                        ?>
                        <tr>
                            <td>
                                <?= e((string) $event) ?>
                                <?php if (!empty($log['description'])): ?>
                                    <div class="small text-muted"><?= e((string) $log['description']) ?></div>
                                <?php endif; ?>
                            </td>
                            <td><?= e((string) $user) ?></td>
                            <td><?= e((string) $timestamp) ?></td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
<?php require APP_ROOT . '/app/views/components/footer.php'; ?>