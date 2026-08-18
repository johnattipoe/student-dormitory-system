<?php
// Ensure bootstrap is loaded
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
require APP_ROOT . '/app/middleware/RoleMiddleware.php';

use App\Services\VisitorAlertService;
use App\Services\FirebaseService;

// Check for manual overstay check
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $_POST['action'] === 'check_overstays') {
    $alertService = new VisitorAlertService();
    $threshold = (int) sanitize($_POST['threshold'] ?? 2);
    $alerts = $alertService->checkForOvrstays($threshold);
    
    if (!empty($alerts)) {
        flash('info', 'Found ' . count($alerts) . ' visitor(s) with overstay');
    } else {
        flash('success', 'No visitor overstays detected');
    }
    redirect(url('views/security/visitors/overstay-alerts.php'));
}

$alertService = new VisitorAlertService();
$pendingAlerts = $alertService->getPendingAlerts();

$pageTitle = 'Visitor Overstay Alerts';
$navItems = [
    ['icon' => 'bi-speedometer2', 'label' => 'Dashboard', 'href' => url('views/security/dashboard/dashboard.php')],
    ['icon' => 'bi-people', 'label' => 'Visitors', 'href' => url('views/security/visitors/visitors.php')],
    ['icon' => 'bi-exclamation-triangle', 'label' => 'Overstay Alerts', 'href' => url('views/security/visitors/overstay-alerts.php'), 'active' => true],
];

require APP_ROOT . '/app/views/components/header.php';
require APP_ROOT . '/app/views/components/sidebar.php';
?>
<div class="main-content">
    <?php require APP_ROOT . '/app/views/components/navbar.php'; ?>
    <?php require APP_ROOT . '/app/views/components/alerts.php'; ?>
    <div class="content-wrapper">
        <div class="row g-3 mb-4">
            <div class="col-md-8">
                <h5>Visitor Overstay Alerts</h5>
            </div>
            <div class="col-md-4">
                <form method="POST" class="d-flex gap-2">
                    <input type="hidden" name="action" value="check_overstays">
                    <select name="threshold" class="form-select form-select-sm">
                        <option value="1">Overstay > 1 hour</option>
                        <option value="2" selected>Overstay > 2 hours</option>
                        <option value="3">Overstay > 3 hours</option>
                        <option value="4">Overstay > 4 hours</option>
                    </select>
                    <button type="submit" class="btn btn-sm btn-warning">
                        <i class="bi bi-play"></i> Check Now
                    </button>
                </form>
            </div>
        </div>

        <!-- Summary Cards -->
        <div class="row g-3 mb-4">
            <div class="col-md-3">
                <div class="card stat-card p-3">
                    <div class="text-muted small">Total Alerts (24h)</div>
                    <div class="fs-2 fw-bold text-danger"><?= count($pendingAlerts) ?></div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card stat-card p-3">
                    <div class="text-muted small">High Severity</div>
                    <div class="fs-2 fw-bold text-danger">
                        <?= count(array_filter($pendingAlerts, fn($a) => ($a['severity'] ?? '') === 'high')) ?>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card stat-card p-3">
                    <div class="text-muted small">Medium Severity</div>
                    <div class="fs-2 fw-bold text-warning">
                        <?= count(array_filter($pendingAlerts, fn($a) => ($a['severity'] ?? '') === 'medium')) ?>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card stat-card p-3">
                    <div class="text-muted small">Resolved</div>
                    <div class="fs-2 fw-bold text-success">
                        <?= count(array_filter($pendingAlerts, fn($a) => ($a['status'] ?? '') === 'resolved')) ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- Alerts Table -->
        <div class="card stat-card p-3">
            <h6 class="mb-3">Recent Overstay Alerts</h6>
            
            <div class="table-responsive">
                <table class="table table-hover data-table w-100">
                    <thead>
                        <tr>
                            <th>Visitor Name</th>
                            <th>Student</th>
                            <th>Check-in Time</th>
                            <th>Duration</th>
                            <th>Severity</th>
                            <th>Alert Sent</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!empty($pendingAlerts)): ?>
                            <?php foreach ($pendingAlerts as $alert): ?>
                                <tr>
                                    <td>
                                        <strong><?= e($alert['visitorName'] ?? '') ?></strong>
                                    </td>
                                    <td><?= e($alert['studentId'] ?? '—') ?></td>
                                    <td><?= e($alert['checkInTime'] ?? '') ?></td>
                                    <td><?= e($alert['durationFormatted'] ?? '') ?></td>
                                    <td>
                                        <span class="badge bg-<?= ($alert['severity'] ?? '') === 'high' ? 'danger' : 'warning' ?>">
                                            <?= e(ucfirst($alert['severity'] ?? 'medium')) ?>
                                        </span>
                                    </td>
                                    <td><?= e(substr($alert['alertSentAt'] ?? '', 11, 5)) ?></td>
                                    <td>
                                        <span class="badge bg-<?= ($alert['status'] ?? '') === 'resolved' ? 'success' : 'secondary' ?>">
                                            <?= e(ucfirst($alert['status'] ?? 'pending')) ?>
                                        </span>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="7" class="text-center text-muted">No overstay alerts in the last 24 hours.</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <div class="alert alert-info small mt-3">
                <strong>ℹ️ Note:</strong> Alerts are automatically created when visitors exceed the threshold duration. Security personnel and house masters are notified. Alerts are resolved when visitors check out.
            </div>
        </div>
    </div>
</div>
<?php require APP_ROOT . '/app/views/components/footer.php'; ?>
