<?php
require __DIR__ . '/../../bootstrap.php';
$allowedRoles = [ROLE_ADMIN, ROLE_HOUSE_MASTER, ROLE_HOUSEPARENT, ROLE_STUDENT];
require APP_ROOT . '/app/middleware/RoleMiddleware.php';

use App\Services\FirebaseService;

$pageTitle = 'Attendance History';
$studentId = $_GET['studentId'] ?? current_user()['studentId'] ?? '';
$records = $studentId ? FirebaseService::getInstance()->where(COL_ATTENDANCE, 'studentId', '=', $studentId, 120) : [];
$navItems = [
    ['icon' => 'bi-speedometer2', 'label' => 'Dashboard', 'href' => url('views/admin/dashboard.php')],
    ['icon' => 'bi-clock-history', 'label' => 'Attendance History', 'href' => url('views/attendance/history.php'), 'active' => true],
];

require APP_ROOT . '/app/views/components/header.php';
require APP_ROOT . '/app/views/components/sidebar.php';
?>
<div class="main-content">
    <?php require APP_ROOT . '/app/views/components/navbar.php'; ?>
    <div class="content-wrapper">
        <h5 class="mb-3">Attendance History</h5>
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
<?php require APP_ROOT . '/app/views/components/footer.php'; ?>
