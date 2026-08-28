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
$allowedRoles = [ROLE_SECURITY];
require APP_ROOT . '/app/middleware/RoleMiddleware/RoleMiddleware.php';

use App\Services\FirebaseService;

$id = sanitize($_GET['id'] ?? '');
if ($id === '') {
    flash('error', 'Log ID is required.');
    redirect(url('views/security/activity-logs/index.php'));
}

$firebase = FirebaseService::getInstance();
$log = $firebase->getDocument(COL_ACTIVITY_LOGS, $id);
if (!$log) {
    $log = $firebase->getDocument('visitors', $id);
    if ($log) {
        $log['event'] = 'Visitor Access';
        $log['details'] = ($log['visitorName'] ?? $log['name'] ?? 'Visitor') . ' visited ' . ($log['studentName'] ?? 'Student');
        $log['performedByName'] = $log['checkedInByName'] ?? 'Gate Security';
    }
}

if (!$log) {
    flash('error', 'Security log entry not found.');
    redirect(url('views/security/activity-logs/index.php'));
}

$rawTime = (string) ($log['timestamp'] ?? $log['createdAt'] ?? '');
$formattedTime = $rawTime !== '' ? (date('F d, Y - H:i:s', strtotime($rawTime)) ?: $rawTime) : 'Not recorded';

$pageTitle = 'Security Log Details';
$navItems = [
    ['icon' => 'bi-speedometer2', 'label' => 'Dashboard', 'href' => url('views/security/dashboard/dashboard.php')],
    ['icon' => 'bi-clock-history', 'label' => 'Activity Logs', 'href' => url('views/security/activity-logs/index.php'), 'active' => true],
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
                <h5 class="mb-1">Security Audit Record</h5>
                <p class="text-muted mb-0">Gate access details and patrol audit.</p>
            </div>
            <a class="btn btn-outline-secondary btn-sm" href="<?= url('views/security/activity-logs/index.php') ?>">
                <i class="bi bi-arrow-left me-1"></i> Back to Security Logs
            </a>
        </div>

        <div class="card stat-card p-4" style="max-width: 850px;">
            <div class="d-flex justify-content-between align-items-start border-bottom pb-3 mb-3 flex-wrap gap-2">
                <div>
                    <span class="badge bg-primary-subtle text-primary border mb-2 fs-6">
                        <?= e(ucwords(str_replace(['_', '-'], ' ', (string)($log['event'] ?? $log['action'] ?? 'Security Activity')))) ?>
                    </span>
                    <h5 class="mb-1 fw-bold"><?= e($log['details'] ?? $log['description'] ?? '—') ?></h5>
                    <div class="small text-muted"><i class="bi bi-clock me-1"></i> <?= e($formattedTime) ?></div>
                </div>
                <span class="badge bg-secondary p-2"><?= e(ucfirst((string)($log['priority'] ?? 'Normal'))) ?></span>
            </div>

            <div class="row g-3 mb-4 bg-light rounded p-3">
                <div class="col-sm-6">
                    <span class="text-muted small d-block">Duty Officer</span>
                    <strong><?= e($log['performedByName'] ?? $log['userName'] ?? 'Security Officer') ?></strong>
                </div>
                <div class="col-sm-6">
                    <span class="text-muted small d-block">Terminal IP Address</span>
                    <span class="font-monospace small"><?= e($log['ip'] ?? '127.0.0.1') ?></span>
                </div>
            </div>

            <div class="d-flex justify-content-end">
                <a class="btn btn-outline-secondary" href="<?= url('views/security/activity-logs/index.php') ?>">Close</a>
            </div>
        </div>
    </div>
</div>
<?php require APP_ROOT . '/app/views/components/footer/footer.php'; ?>

