<?php
// Ensure bootstrap is loaded (safe at any view nesting depth)
if (!defined('APP_ROOT')) {
    $dir = __DIR__;
    for ($i = 0; $i < 10; $i++) {
        if (file_exists($dir . '/bootstrap.php')) { require $dir . '/bootstrap.php'; break; }
        $parent = dirname($dir);
        if ($parent === $dir) break;
        $dir = $parent;
    }
}
$allowedRoles = [ROLE_SENIOR_HOUSEPARENT];
require APP_ROOT . '/app/middleware/RoleMiddleware/RoleMiddleware.php';

use App\Services\FirebaseService;

$id = sanitize($_GET['id'] ?? '');
if ($id === '') {
    flash('error', 'Incident ID is required.');
    redirect(url('views/senior-houseparent/emergency-alerts/index.php'));
}

$firebase = FirebaseService::getInstance();
$incident = $firebase->getDocument('emergency_incidents', $id);
if (!$incident) {
    flash('error', 'Emergency log record not found.');
    redirect(url('views/senior-houseparent/emergency-alerts/index.php'));
}

$rawTime = (string) ($incident['triggeredAt'] ?? $incident['createdAt'] ?? '');
$formattedTime = $rawTime !== '' ? (date('F d, Y - H:i:s', strtotime($rawTime)) ?: $rawTime) : 'Not recorded';

$pageTitle = 'Emergency Incident Details';
$navItems = [
    ['icon' => 'bi-speedometer2', 'label' => 'Dashboard', 'href' => url('views/senior-houseparent/dashboard/index.php')],
    ['icon' => 'bi-telephone-inbound', 'label' => 'Emergency Alerts', 'href' => url('views/senior-houseparent/emergency-alerts/index.php'), 'active' => true],
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
                <h5 class="mb-1">Emergency Incident Record</h5>
                <p class="text-muted mb-0">Full dispatch audit and response breakdown.</p>
            </div>
            <a class="btn btn-outline-secondary btn-sm" href="<?= url('views/senior-houseparent/emergency-alerts/index.php') ?>">
                <i class="bi bi-arrow-left me-1"></i> Back to Emergency Center
            </a>
        </div>

        <div class="card stat-card p-4" style="max-width: 850px;">
            <div class="d-flex justify-content-between align-items-start border-bottom pb-3 mb-3 flex-wrap gap-2">
                <div>
                    <span class="badge bg-danger-subtle text-danger border mb-2 fs-6">
                        <?= !empty($incident['isBroadcast']) ? 'Emergency Broadcast' : 'Emergency Call Log' ?>
                    </span>
                    <h5 class="mb-1 fw-bold"><?= e($incident['title'] ?? $incident['contactName'] ?? 'Emergency Log') ?></h5>
                    <div class="small text-muted"><i class="bi bi-clock me-1"></i> <?= e($formattedTime) ?></div>
                </div>
            </div>

            <div class="row g-3 mb-4 bg-light rounded p-3">
                <div class="col-sm-6">
                    <span class="text-muted small d-block">Contacted Agency / Responder</span>
                    <strong><?= e($incident['contactName'] ?? 'Emergency Service') ?></strong>
                    <?php if (!empty($incident['contactPhone'])): ?>
                        <div class="small text-muted"><i class="bi bi-telephone me-1"></i><?= e($incident['contactPhone']) ?></div>
                    <?php endif; ?>
                </div>
                <div class="col-sm-6">
                    <span class="text-muted small d-block">Logged By Staff</span>
                    <strong><?= e($incident['triggeredByName'] ?? 'Senior Houseparent') ?></strong>
                </div>
            </div>

            <?php if (!empty($incident['studentName'])): ?>
                <div class="mb-3">
                    <span class="text-muted small d-block">Involved Student</span>
                    <strong><?= e($incident['studentName']) ?></strong>
                </div>
            <?php endif; ?>

            <div class="mb-3">
                <h6 class="fw-bold mb-1">Call Discussion & Incident Notes</h6>
                <p class="text-muted p-3 bg-body-tertiary rounded"><?= e($incident['notes'] ?? 'No notes recorded.') ?></p>
            </div>

            <?php if (!empty($incident['actionTaken'])): ?>
                <div class="mb-4">
                    <h6 class="fw-bold mb-1">Action Taken</h6>
                    <p class="text-muted p-3 bg-body-tertiary rounded"><?= e($incident['actionTaken']) ?></p>
                </div>
            <?php endif; ?>

            <div class="d-flex justify-content-end">
                <a class="btn btn-outline-secondary" href="<?= url('views/senior-houseparent/emergency-alerts/index.php') ?>">Close</a>
            </div>
        </div>
    </div>
</div>
<?php require APP_ROOT . '/app/views/components/footer/footer.php'; ?>

