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
use App\Services\AttendanceService;

$pageTitle = 'Attendance History';
$date = $_GET['date'] ?? date('Y-m-d');
$records = AttendanceService::forDate($date);
$navItems = [
    ['icon' => 'bi-speedometer2', 'label' => 'Dashboard', 'href' => url('views/admin/dashboard.php')],
    ['icon' => 'bi-calendar2-check', 'label' => 'Attendance', 'href' => url('views/admin/attendance/index.php')],
    ['icon' => 'bi-clock-history', 'label' => 'History', 'href' => url('views/admin/attendance/history.php?date=' . urlencode($date)), 'active' => true],
];
require APP_ROOT . '/app/views/components/header.php';
require APP_ROOT . '/app/views/components/sidebar.php';
?>
<div class="main-content">
    <?php require APP_ROOT . '/app/views/components/navbar.php'; ?>
    <div class="content-wrapper">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <div>
                <h5 class="mb-0">Attendance History</h5>
                <p class="text-muted mb-0">Date: <?= e($date) ?></p>
            </div>
            <a href="<?= url('views/admin/attendance/index.php') ?>" class="btn btn-outline-secondary btn-sm">Back</a>
        </div>
        <div class="card stat-card p-3">
            <table class="table table-hover data-table w-100">
                <thead>
                <tr>
                    <th>Student</th>
                    <th>Status</th>
                    <th>Recorded At</th>
                </tr>
                </thead>
                <tbody>
                <?php foreach ($records as $record): ?>
                    <tr>
                        <td><?= e($record['studentName'] ?? '-') ?></td>
                        <td><?= e($record['status'] ?? '-') ?></td>
                        <td><?= e($record['recordedAt'] ?? '-') ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
<?php require APP_ROOT . '/app/views/components/footer.php'; ?>