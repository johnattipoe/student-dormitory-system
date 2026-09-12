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
$allowedRoles = [ROLE_HOUSE_MASTER, ROLE_HOUSE_MISTRESS];
require APP_ROOT . '/app/middleware/RoleMiddleware/RoleMiddleware.php';

use App\Services\FirebaseService;
use App\Services\HouseService;

$id = sanitize($_GET['id'] ?? '');
if ($id === '') {
    flash('error', 'Activity Log ID is required.');
    redirect(url('views/house-master/activity-logs/index.php'));
}

$firebase = FirebaseService::getInstance();
$log = $firebase->getDocument(COL_ACTIVITY_LOGS, $id);
if (!$log) {
    flash('error', 'Activity log entry not found.');
    redirect(url('views/house-master/activity-logs/index.php'));
}

$actorName = (string) ($log['performedByName'] ?? $log['userName'] ?? '');
$rawActorId = (string) ($log['userId'] ?? $log['performedBy'] ?? '');
if ($rawActorId === 'default-admin') {
    $actorName = 'Administrator (Admin)';
} elseif ($actorName === '' || $actorName === 'default-admin' || str_starts_with($actorName, 'Staff/User')) {
    if ($rawActorId !== '') {
        try {
            $u = $firebase->getDocument('users', $rawActorId);
            if ($u) {
                $name = trim(($u['name'] ?? '') ?: (($u['fullName'] ?? '') ?: (($u['firstName'] ?? '') . ' ' . ($u['lastName'] ?? ''))));
                if ($name !== '') {
                    $roleLabel = !empty($u['role']) ? ' (' . ucfirst(str_replace(['_', '-'], ' ', (string) $u['role'])) . ')' : '';
                    $actorName = $name . $roleLabel;
                }
            }
        } catch (\Throwable $e) {}
    }
}
if ($actorName === '') $actorName = 'House Master / Staff';

$houseId = (string) ($log['houseId'] ?? current_user()['houseId'] ?? '');
$house = $houseId !== '' ? HouseService::find($houseId) : null;
$houseName = $house['name'] ?? ($houseId ?: 'Assigned House');

$rawTime = (string) ($log['timestamp'] ?? $log['createdAt'] ?? $log['time'] ?? '');
$formattedTime = $rawTime !== '' ? (date('F d, Y - H:i:s', strtotime($rawTime)) ?: $rawTime) : 'Not recorded';

$pageTitle = 'Activity Log Details';
$navItems = [
    ['icon' => 'bi-speedometer2', 'label' => 'Dashboard', 'href' => url('views/house-master/dashboard/index.php')],
    ['icon' => 'bi-clock-history', 'label' => 'Activity Logs', 'href' => url('views/house-master/activity-logs/index.php'), 'active' => true],
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
                <h5 class="mb-1">House Activity Log Details</h5>
                <p class="text-muted mb-0">Record inspection for <?= e($houseName) ?>.</p>
            </div>
            <a class="btn btn-outline-secondary btn-sm" href="<?= url('views/house-master/activity-logs/index.php') ?>">
                <i class="bi bi-arrow-left me-1"></i> Back to Logs
            </a>
        </div>

        <div class="card stat-card p-4" style="max-width: 850px;">
            <div class="d-flex justify-content-between align-items-start border-bottom pb-3 mb-3 flex-wrap gap-2">
                <div>
                    <span class="badge bg-primary-subtle text-primary border mb-2 fs-6">
                        <?= e(ucwords(str_replace(['_', '-'], ' ', (string)($log['event'] ?? $log['action'] ?? 'Activity')))) ?>
                    </span>
                    <h5 class="mb-1 fw-bold"><?= e($log['details'] ?? $log['description'] ?? '—') ?></h5>
                    <div class="small text-muted">
                        <i class="bi bi-clock me-1"></i> <?= e($formattedTime) ?> • <i class="bi bi-house me-1"></i> <?= e($houseName) ?>
                    </div>
                </div>
                <?php if (!empty($log['isManual'])): ?>
                    <span class="badge bg-info p-2">Supervisory Note</span>
                <?php endif; ?>
            </div>

            <div class="row g-3 mb-4 bg-light rounded p-3">
                <div class="col-sm-6">
                    <span class="text-muted small d-block">Performed By</span>
                    <strong><?= e($actorName) ?></strong>
                </div>
                <div class="col-sm-6">
                    <span class="text-muted small d-block">IP Address</span>
                    <span class="font-monospace small"><?= e($log['ip'] ?? '—') ?></span>
                </div>
            </div>

            <?php if (!empty($log['studentName']) || !empty($log['roomNumber'])): ?>
                <div class="card bg-body-tertiary p-3 mb-4 border">
                    <h6 class="fw-bold mb-2">Linked Targets</h6>
                    <div class="row g-2">
                        <?php if (!empty($log['studentName'])): ?>
                            <div class="col-sm-6">
                                <span class="text-muted small d-block">Student</span>
                                <strong><?= e($log['studentName']) ?></strong>
                            </div>
                        <?php endif; ?>
                        <?php if (!empty($log['roomNumber'])): ?>
                            <div class="col-sm-6">
                                <span class="text-muted small d-block">Room</span>
                                <strong>Room <?= e($log['roomNumber']) ?></strong>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endif; ?>

            <div class="d-flex justify-content-end">
                <a class="btn btn-outline-secondary" href="<?= url('views/house-master/activity-logs/index.php') ?>">Close</a>
            </div>
        </div>
    </div>
</div>
<?php require APP_ROOT . '/app/views/components/footer/footer.php'; ?>

