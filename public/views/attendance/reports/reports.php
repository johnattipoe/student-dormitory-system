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
$allowedRoles = [ROLE_ADMIN, ROLE_HOUSE_MASTER, ROLE_SENIOR_HOUSEPARENT];
require APP_ROOT . '/app/middleware/RoleMiddleware/RoleMiddleware.php';

use App\Services\FirebaseService;

$pageTitle = 'Attendance Reports';
$today = date('Y-m-d');
$records = FirebaseService::getInstance()->where(COL_ATTENDANCE, 'date', '=', $today, 200);
$present = count(array_filter($records, fn($r) => ($r['status'] ?? '') === 'present'));
$absent = count(array_filter($records, fn($r) => ($r['status'] ?? '') === 'absent'));
$late = count(array_filter($records, fn($r) => ($r['status'] ?? '') === 'late'));

$navItems = [
    ['icon' => 'bi-speedometer2', 'label' => 'Dashboard', 'href' => url('views/admin/dashboard.php')],
    ['icon' => 'bi-bar-chart', 'label' => 'Attendance Reports', 'href' => url('views/attendance/reports/reports.php'), 'active' => true],
];

require APP_ROOT . '/app/views/components/header/header.php';
require APP_ROOT . '/app/views/components/sidebar/sidebar.php';
?>
<div class="main-content">
    <?php require APP_ROOT . '/app/views/components/navbar/navbar.php'; ?>
    <div class="content-wrapper">
        <h5 class="mb-3">Attendance Report</h5>
        <div class="row g-3 mb-4">
            <div class="col-md-4"><div class="card stat-card p-3 text-center"><div class="text-muted small">Present</div><div class="fs-3 fw-bold"><?= e((string) $present) ?></div></div></div>
            <div class="col-md-4"><div class="card stat-card p-3 text-center"><div class="text-muted small">Absent</div><div class="fs-3 fw-bold"><?= e((string) $absent) ?></div></div></div>
            <div class="col-md-4"><div class="card stat-card p-3 text-center"><div class="text-muted small">Late</div><div class="fs-3 fw-bold"><?= e((string) $late) ?></div></div></div>
        </div>

        <div class="card stat-card p-3">
            <table class="table table-hover w-100">
                <thead>
                <tr><th>Student</th><th>Date</th><th>Status</th></tr>
                </thead>
                <tbody>
                <?php foreach ($records as $r): ?>
                    <tr>
                        <td><?= e($r['studentId'] ?? '-') ?></td>
                        <td><?= e($r['date'] ?? '-') ?></td>
                        <td><?= e($r['status'] ?? '-') ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
<?php require APP_ROOT . '/app/views/components/footer/footer.php'; ?>
