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
require APP_ROOT . '/app/middleware/RoleMiddleware/RoleMiddleware.php';

use App\Services\FirebaseService;

$id = sanitize($_GET['id'] ?? '');
if ($id === '') {
    flash('error', 'Log ID is required.');
    redirect(url('views/admin/activity-logs/index.php'));
}

$firebase = FirebaseService::getInstance();
$log = $firebase->getDocument(COL_ACTIVITY_LOGS, $id);
if (!$log) {
    flash('error', 'Activity log not found.');
    redirect(url('views/admin/activity-logs/index.php'));
}

$actorName = (string) ($log['userName'] ?? $log['performedByName'] ?? '');
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
if ($actorName === '') $actorName = 'System / Administrator';

$rawTime = (string) ($log['timestamp'] ?? $log['createdAt'] ?? $log['time'] ?? '');
$formattedTime = $rawTime !== '' ? (date('F d, Y - H:i:s', strtotime($rawTime)) ?: $rawTime) : 'Not recorded';

$pageTitle = 'Activity Log Details';
$navItems = [
    ['icon' => 'bi-speedometer2', 'label' => 'Dashboard', 'href' => url('views/admin/dashboard.php')],
    ['icon' => 'bi-list-check', 'label' => 'Activity Logs', 'href' => url('views/admin/activity-logs/index.php'), 'active' => true],
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
                <h5 class="mb-1">Audit Record Inspector</h5>
                <p class="text-muted mb-0">System audit forensics and transactional log details.</p>
            </div>
            <a class="btn btn-outline-secondary btn-sm" href="<?= url('views/admin/activity-logs/index.php') ?>">
                <i class="bi bi-arrow-left me-1"></i> Back to Logs
            </a>
        </div>

        <div class="card stat-card p-4" style="max-width: 850px;">
            <div class="d-flex justify-content-between align-items-start border-bottom pb-3 mb-3 flex-wrap gap-2">
                <div>
                    <span class="badge bg-primary-subtle text-primary border mb-2 fs-6">
                        <?= e(ucwords(str_replace(['_', '-'], ' ', (string)($log['event'] ?? $log['action'] ?? 'Activity')))) ?>
                    </span>
                    <h5 class="mb-1 fw-bold"><?= e($log['details'] ?? $log['description'] ?? $log['message'] ?? '—') ?></h5>
                    <div class="small text-muted">
                        <i class="bi bi-clock me-1"></i> <?= e($formattedTime) ?>
                    </div>
                </div>
                <?php if (!empty($log['isManual'])): ?>
                    <span class="badge bg-info p-2">Admin Directive</span>
                <?php else: ?>
                    <span class="badge bg-secondary-subtle text-secondary border p-2">System Audit</span>
                <?php endif; ?>
            </div>

            <div class="row g-3 mb-4 bg-light rounded p-3">
                <div class="col-sm-6">
                    <span class="text-muted small d-block">Performed By</span>
                    <strong><?= e($actorName) ?></strong>
                </div>
                <div class="col-sm-6">
                    <span class="text-muted small d-block">User Identifier</span>
                    <code class="small"><?= e($rawActorId ?: '—') ?></code>
                </div>
                <div class="col-sm-6">
                    <span class="text-muted small d-block">Client IP</span>
                    <span class="font-monospace small"><?= e($log['ip'] ?? $log['ipAddress'] ?? '—') ?></span>
                </div>
                <div class="col-sm-6">
                    <span class="text-muted small d-block">Document Log ID</span>
                    <code class="small"><?= e($id) ?></code>
                </div>
            </div>

            <?php if (!empty($log['data']) && is_array($log['data'])): ?>
                <div class="mb-4">
                    <h6 class="fw-bold mb-2">Payload Data / Changes</h6>
                    <pre class="bg-dark text-light p-3 rounded small mb-0"><code><?= e(json_encode($log['data'], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)) ?></code></pre>
                </div>
            <?php endif; ?>

            <div class="d-flex justify-content-end">
                <a class="btn btn-outline-secondary" href="<?= url('views/admin/activity-logs/index.php') ?>">Close</a>
            </div>
        </div>
    </div>
</div>
<?php require APP_ROOT . '/app/views/components/footer/footer.php'; ?>

