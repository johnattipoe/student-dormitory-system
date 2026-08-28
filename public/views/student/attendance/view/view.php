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
$allowedRoles = [ROLE_STUDENT];
require APP_ROOT . '/app/middleware/RoleMiddleware/RoleMiddleware.php';

use App\Services\AttendanceService;

$studentId = current_user()['studentId'] ?? current_user()['uid'] ?? null;
$id = sanitize($_GET['id'] ?? '');
$entry = null;

foreach (AttendanceService::history($studentId, 200) as $record) {
    if ((string) ($record['id'] ?? '') === $id) {
        $entry = $record;
        break;
    }
}

if (!$entry) {
    flash('error', 'Attendance record not found.');
    redirect(url('views/student/attendance/index/index.php'));
}

$pageTitle = 'Attendance Details';
$navItems = [
    ['icon' => 'bi-speedometer2', 'label' => 'Dashboard', 'href' => url('views/student/dashboard/index.php')],
    ['icon' => 'bi-calendar-check', 'label' => 'Attendance', 'href' => url('views/student/attendance/index/index.php'), 'active' => true],
];
require APP_ROOT . '/app/views/components/header/header.php';
require APP_ROOT . '/app/views/components/sidebar/sidebar.php';
?>
<div class="main-content">
    <?php require APP_ROOT . '/app/views/components/navbar/navbar.php'; ?>
    <div class="content-wrapper">
        <div class="card stat-card p-4" style="max-width:700px">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <h5 class="mb-0">Attendance Details</h5>
                <a class="btn btn-outline-secondary btn-sm" href="<?= url('views/student/attendance/index/index.php') ?>">Back</a>
            </div>
            <dl class="row mt-4">
                <dt class="col-sm-4">Date</dt>
                <dd class="col-sm-8"><?= e($entry['date'] ?? '') ?></dd>

                <dt class="col-sm-4">Status</dt>
                <dd class="col-sm-8">
                    <span class="badge bg-<?= ($entry['status'] ?? '') === 'present' ? 'success' : (($entry['status'] ?? '') === 'absent' ? 'danger' : 'warning') ?>">
                        <?= e(ucfirst($entry['status'] ?? 'present')) ?>
                    </span>
                </dd>

                <dt class="col-sm-4">Notes / Reason</dt>
                <dd class="col-sm-8"><?= e($entry['reason'] ?? $entry['notes'] ?? '—') ?></dd>
            </dl>
        </div>
    </div>
</div>
<?php require APP_ROOT . '/app/views/components/footer/footer.php'; ?>