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

use App\Services\FirebaseService;

$id = sanitize($_GET['id'] ?? '');
if ($id === '') {
    flash('error', 'Log ID is required.');
    redirect(url('views/student/activity-logs/index.php'));
}

$firebase = FirebaseService::getInstance();
$log = $firebase->getDocument(COL_ACTIVITY_LOGS, $id);
if (!$log) {
    $log = $firebase->getDocument('attendance', $id);
    if ($log) {
        $log['event'] = 'Daily Roll Call';
        $log['details'] = 'Roll call recorded as "' . ucfirst((string)($log['status'] ?? 'present')) . '"';
        $log['performedByName'] = $log['markedByName'] ?? 'House Master';
    }
}
if (!$log) {
    $log = $firebase->getDocument('exeats', $id);
    if ($log) {
        $log['event'] = 'Exeat Application';
        $log['details'] = 'Exeat reason: ' . ($log['reason'] ?? 'Leave') . ' (Status: ' . ucfirst((string)($log['status'] ?? 'pending')) . ')';
        $log['performedByName'] = $log['approvedByName'] ?? 'House Staff';
    }
}

if (!$log) {
    flash('error', 'Activity record not found.');
    redirect(url('views/student/activity-logs/index.php'));
}

$rawTime = (string) ($log['timestamp'] ?? $log['createdAt'] ?? $log['date'] ?? '');
$formattedTime = $rawTime !== '' ? (date('F d, Y - H:i:s', strtotime($rawTime)) ?: $rawTime) : 'Not recorded';

$pageTitle = 'Activity Details';
$navItems = [
    ['icon' => 'bi-speedometer2', 'label' => 'Dashboard', 'href' => url('views/student/dashboard/index.php')],
    ['icon' => 'bi-clock-history', 'label' => 'Activity History', 'href' => url('views/student/activity-logs/index.php'), 'active' => true],
];

require APP_ROOT . '/app/views/components/header/header.php';
require APP_ROOT . '/app/views/components/sidebar/sidebar.php';
?>
<div class="main-content">
    <?php require APP_ROOT . '/app/views/components/navbar/navbar.php'; ?>
    <?php require APP_ROOT . '/app/views/components/alerts/alerts.php'; ?>
    <div class="content-wrapper">
        <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
            <div>
                <h5 class="mb-1">Dormitory Activity Record</h5>
                <p class="text-muted mb-0">Record breakdown for your student dormitory history.</p>
            </div>
            <a class="btn btn-outline-secondary btn-sm" href="<?= url('views/student/activity-logs/index.php') ?>">
                <i class="bi bi-arrow-left me-1"></i> Back to History
            </a>
        </div>

        <div class="card stat-card p-4" style="max-width: 800px;">
            <div class="d-flex justify-content-between align-items-start border-bottom pb-3 mb-3 flex-wrap gap-2">
                <div>
                    <span class="badge bg-primary-subtle text-primary border mb-2 fs-6">
                        <?= e(ucwords(str_replace(['_', '-'], ' ', (string)($log['event'] ?? $log['action'] ?? 'Activity')))) ?>
                    </span>
                    <h5 class="mb-1 fw-bold"><?= e($log['details'] ?? $log['description'] ?? '—') ?></h5>
                    <div class="small text-muted"><i class="bi bi-clock me-1"></i> <?= e($formattedTime) ?></div>
                </div>
            </div>

            <div class="row g-3 mb-4 bg-light rounded p-3">
                <div class="col-sm-6">
                    <span class="text-muted small d-block">Recorded By</span>
                    <strong><?= e($log['performedByName'] ?? $log['userName'] ?? 'House Staff') ?></strong>
                </div>
                <div class="col-sm-6">
                    <span class="text-muted small d-block">Record Date</span>
                    <span><?= e(substr($formattedTime, 0, 12)) ?></span>
                </div>
            </div>

            <div class="d-flex justify-content-end">
                <a class="btn btn-outline-secondary" href="<?= url('views/student/activity-logs/index.php') ?>">Close</a>
            </div>
        </div>
    </div>
</div>
<?php require APP_ROOT . '/app/views/components/footer/footer.php'; ?>

