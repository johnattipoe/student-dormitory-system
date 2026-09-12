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
$allowedRoles = [ROLE_SENIOR_HOUSEPARENT];
require APP_ROOT . '/app/middleware/RoleMiddleware/RoleMiddleware.php';

use App\Services\FirebaseService;
use App\Services\UserService;
use App\Services\StudentService;
use App\Services\HouseService;

$id = sanitize($_GET['id'] ?? '');
if ($id === '') {
    flash('error', 'Activity Log ID is required.');
    redirect(url('views/senior-houseparent/activity-logs/index.php'));
}

$firebase = FirebaseService::getInstance();
$log = $firebase->getDocument(COL_ACTIVITY_LOGS, $id);
if (!$log) {
    flash('error', 'Activity log entry not found.');
    redirect(url('views/senior-houseparent/activity-logs/index.php'));
}

// User resolution
$actorName = (string) ($log['performedByName'] ?? $log['userName'] ?? '');
$rawActorId = (string) ($log['userId'] ?? $log['performedBy'] ?? $log['actorId'] ?? '');

if ($rawActorId === 'default-admin') {
    $actorName = 'Administrator (Admin)';
} elseif ($actorName === '' || $actorName === 'default-admin' || str_starts_with($actorName, 'Staff/User')) {
    if ($rawActorId !== '') {
        try {
            $u = $firebase->getDocument('users', $rawActorId);
            if ($u) {
                $name = trim(($u['name'] ?? '') ?: (($u['fullName'] ?? '') ?: (($u['firstName'] ?? '') . ' ' . ($u['lastName'] ?? ''))));
                if ($name === '') $name = $u['displayName'] ?? $u['username'] ?? '';
                if ($name !== '') {
                    $roleLabel = !empty($u['role']) ? ' (' . ucfirst(str_replace(['_', '-'], ' ', (string) $u['role'])) . ')' : '';
                    $actorName = $name . $roleLabel;
                }
            }
        } catch (\Throwable $e) {}
    }
}
if ($actorName === '') $actorName = 'System / Administrator';

$houseId = (string) ($log['houseId'] ?? $log['house_id'] ?? current_user()['houseId'] ?? '');
$house = $houseId !== '' ? HouseService::find($houseId) : null;
$houseName = $house['name'] ?? ($houseId ?: 'Assigned House');

$rawTime = (string) ($log['timestamp'] ?? $log['createdAt'] ?? $log['time'] ?? '');
$formattedTime = $rawTime !== '' ? (date('F d, Y - H:i:s', strtotime($rawTime)) ?: $rawTime) : 'Not recorded';

$action = (string) ($log['event'] ?? $log['action'] ?? $log['type'] ?? 'Activity');
$details = (string) ($log['details'] ?? $log['description'] ?? $log['message'] ?? '—');
$ip = (string) ($log['ip'] ?? $log['ipAddress'] ?? '—');

$pageTitle = 'Activity Log Details';
$navItems = [
    ['icon' => 'bi-speedometer2', 'label' => 'Dashboard', 'href' => url('views/senior-houseparent/dashboard/index.php')],
    ['icon' => 'bi-clock-history', 'label' => 'Activity Logs', 'href' => url('views/senior-houseparent/activity-logs/index.php'), 'active' => true],
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
                <h5 class="mb-1">Audit Trail Record Details</h5>
                <p class="text-muted mb-0">Complete record breakdown and network forensics.</p>
            </div>
            <a class="btn btn-outline-secondary btn-sm" href="<?= url('views/senior-houseparent/activity-logs/index.php') ?>">
                <i class="bi bi-arrow-left me-1"></i> Back to Logs
            </a>
        </div>

        <div class="card stat-card p-4" style="max-width: 850px;">
            <div class="d-flex justify-content-between align-items-start border-bottom pb-3 mb-3 flex-wrap gap-2">
                <div>
                    <span class="badge bg-primary-subtle text-primary border mb-2 fs-6">
                        <?= e(ucwords(str_replace(['_', '-'], ' ', $action))) ?>
                    </span>
                    <h5 class="mb-1 fw-bold"><?= e($details) ?></h5>
                    <div class="small text-muted">
                        <span><i class="bi bi-clock me-1"></i> <?= e($formattedTime) ?></span>
                        <span class="mx-2">•</span>
                        <span><i class="bi bi-house me-1"></i> <?= e($houseName) ?></span>
                    </div>
                </div>
                <?php if (!empty($log['isManual'])): ?>
                    <span class="badge bg-info p-2">Manual Observation</span>
                <?php else: ?>
                    <span class="badge bg-secondary-subtle text-secondary border p-2">Automated Audit</span>
                <?php endif; ?>
            </div>

            <div class="row g-3 mb-4 bg-light rounded p-3">
                <div class="col-sm-6">
                    <span class="text-muted small d-block">Performed By / Actor</span>
                    <strong><?= e($actorName) ?></strong>
                </div>
                <div class="col-sm-6">
                    <span class="text-muted small d-block">Actor User ID</span>
                    <code class="small"><?= e($rawActorId ?: '—') ?></code>
                </div>
                <div class="col-sm-6">
                    <span class="text-muted small d-block">Origin IP Address</span>
                    <span class="font-monospace small"><?= e($ip) ?></span>
                </div>
                <div class="col-sm-6">
                    <span class="text-muted small d-block">Log ID</span>
                    <code class="small"><?= e($id) ?></code>
                </div>
            </div>

            <?php if (!empty($log['studentName']) || !empty($log['studentId']) || !empty($log['roomNumber']) || !empty($log['roomId'])): ?>
                <div class="card bg-body-tertiary p-3 mb-4 border">
                    <h6 class="fw-bold mb-2">Linked Target Entities</h6>
                    <div class="row g-2">
                        <?php if (!empty($log['studentName']) || !empty($log['studentId'])): ?>
                            <div class="col-sm-6">
                                <span class="text-muted small d-block">Target Student</span>
                                <strong><?= e($log['studentName'] ?? 'Student') ?></strong>
                                <?php if (!empty($log['studentId'])): ?>
                                    <code class="small">[<?= e($log['studentId']) ?>]</code>
                                <?php endif; ?>
                            </div>
                        <?php endif; ?>
                        <?php if (!empty($log['roomNumber']) || !empty($log['roomId'])): ?>
                            <div class="col-sm-6">
                                <span class="text-muted small d-block">Target Room</span>
                                <strong>Room <?= e($log['roomNumber'] ?? $log['roomId']) ?></strong>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endif; ?>

            <div class="d-flex justify-content-end">
                <a class="btn btn-outline-secondary" href="<?= url('views/senior-houseparent/activity-logs/index.php') ?>">
                    Close
                </a>
            </div>
        </div>
    </div>
</div>
<?php require APP_ROOT . '/app/views/components/footer/footer.php'; ?>

