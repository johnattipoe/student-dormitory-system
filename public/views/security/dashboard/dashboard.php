<?php
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

use App\Services\IncidentService;
use App\Services\VisitorService;

$visitorService = new VisitorService();
$incidentService = new IncidentService();
$visitors = $visitorService->all();
$incidents = $incidentService->all();
$insideVisitors = array_values(array_filter($visitors, static fn($v) => ($v['status'] ?? '') === 'inside'));
$pendingVisitors = array_values(array_filter($visitors, static fn($v) => ($v['status'] ?? '') === 'pending'));
$todayVisitors = array_values(array_filter($visitors, static function ($v) {
    $value = (string) ($v['checkInTime'] ?? $v['visitDate'] ?? $v['createdAt'] ?? '');
    return str_starts_with($value, date('Y-m-d'));
}));
$openIncidents = array_values(array_filter($incidents, static fn($i) => ($i['status'] ?? 'open') === 'open'));
$urgentIncidents = array_values(array_filter($incidents, static fn($i) => in_array(strtolower((string) ($i['priority'] ?? '')), ['high', 'critical', 'urgent'], true)));

usort($visitors, static fn($a, $b) => strcmp((string) ($b['checkInTime'] ?? $b['createdAt'] ?? ''), (string) ($a['checkInTime'] ?? $a['createdAt'] ?? '')));
usort($incidents, static fn($a, $b) => strcmp((string) ($b['reportedAt'] ?? $b['createdAt'] ?? ''), (string) ($a['reportedAt'] ?? $a['createdAt'] ?? '')));

$pageTitle = 'Security Dashboard';
$pageStyles = ['security.css'];
$navItems = [
    ['icon' => 'bi-speedometer2', 'label' => 'Dashboard', 'href' => url('views/security/dashboard/dashboard.php'), 'active' => true],
    ['icon' => 'bi-people', 'label' => 'Visitors', 'href' => url('views/security/visitors/visitors.php')],
    ['icon' => 'bi-journal-text', 'label' => 'Visitor History', 'href' => url('views/security/visitor-history/visitor-history.php')],
    ['icon' => 'bi-exclamation-triangle', 'label' => 'Incidents', 'href' => url('views/security/incidents/incidents.php')],
    ['icon' => 'bi-bar-chart', 'label' => 'Reports', 'href' => url('views/security/reports/index.php')],
    ['icon' => 'bi-bell', 'label' => 'Notifications', 'href' => url('views/security/notifications/notifications.php')],
];

require APP_ROOT . '/app/views/components/header.php';
require APP_ROOT . '/app/views/components/sidebar.php';
?>
<div class="main-content">
    <?php require APP_ROOT . '/app/views/components/navbar.php'; ?>
    <?php require APP_ROOT . '/app/views/components/alerts.php'; ?>
    <div class="content-wrapper security-portal">
        <section class="security-hero mb-4">
            <div class="security-hero-icon"><i class="bi bi-shield-lock"></i></div>
            <div>
                <span class="security-kicker">Gate operations</span>
                <h1>Welcome, <?= e(current_user()['name'] ?? 'Security Officer') ?></h1>
                <p>Monitor visitors, check-ins, check-outs, overstay alerts, and security incidents from one control panel.</p>
                <div class="security-badges">
                    <span class="badge bg-success"><i class="bi bi-person-check me-1"></i><?= e((string) count($insideVisitors)) ?> inside</span>
                    <span class="badge bg-warning"><i class="bi bi-hourglass-split me-1"></i><?= e((string) count($pendingVisitors)) ?> pending</span>
                    <span class="badge bg-danger"><i class="bi bi-exclamation-triangle me-1"></i><?= e((string) count($openIncidents)) ?> open incidents</span>
                </div>
            </div>
            <div class="security-hero-actions">
                <a class="btn btn-light" href="<?= url('views/security/register-visitor/register-visitor.php') ?>"><i class="bi bi-person-plus me-1"></i>Register visitor</a>
                <a class="btn btn-primary" href="<?= url('views/security/report-incident/report-incident.php') ?>"><i class="bi bi-flag me-1"></i>Report incident</a>
            </div>
        </section>

        <div class="row g-3 mb-4">
            <div class="col-md-3"><div class="security-stat"><span class="security-stat-icon blue"><i class="bi bi-people"></i></span><div><small>Total visitors</small><strong><?= e((string) count($visitors)) ?></strong></div></div></div>
            <div class="col-md-3"><div class="security-stat"><span class="security-stat-icon green"><i class="bi bi-door-open"></i></span><div><small>Currently inside</small><strong><?= e((string) count($insideVisitors)) ?></strong></div></div></div>
            <div class="col-md-3"><div class="security-stat"><span class="security-stat-icon orange"><i class="bi bi-calendar-day"></i></span><div><small>Today visitors</small><strong><?= e((string) count($todayVisitors)) ?></strong></div></div></div>
            <div class="col-md-3"><div class="security-stat"><span class="security-stat-icon red"><i class="bi bi-exclamation-octagon"></i></span><div><small>Urgent incidents</small><strong><?= e((string) count($urgentIncidents)) ?></strong></div></div></div>
        </div>

        <div class="row g-4">
            <div class="col-xl-8">
                <div class="security-card mb-4">
                    <div class="security-card-header">
                        <div><span class="security-kicker">Live board</span><h2>Recent visitor activity</h2><p>Latest visitor registrations and movement status.</p></div>
                        <a class="btn btn-outline-primary btn-sm" href="<?= url('views/security/visitors/visitors.php') ?>">Open visitors</a>
                    </div>
                    <div class="table-responsive">
                        <table class="table table-hover data-table w-100">
                            <thead><tr><th>Name</th><th>Student</th><th>Purpose</th><th>Status</th><th>Action</th></tr></thead>
                            <tbody>
                            <?php if (!$visitors): ?>
                                <tr><td colspan="5" class="text-center text-muted">No visitors registered.</td></tr>
                            <?php else: foreach (array_slice($visitors, 0, 10) as $visitor): ?>
                                <?php $status = strtolower((string) ($visitor['status'] ?? 'pending')); ?>
                                <tr>
                                    <td><?= e($visitor['visitorName'] ?? '-') ?></td>
                                    <td><?= e($visitor['studentId'] ?? '-') ?></td>
                                    <td><?= e($visitor['purpose'] ?? '-') ?></td>
                                    <td><span class="badge bg-<?= $status === 'inside' ? 'success' : ($status === 'pending' ? 'warning' : 'secondary') ?>"><?= e(ucfirst($status)) ?></span></td>
                                    <td><a class="btn btn-sm btn-outline-primary" href="<?= url('views/security/visitors/view.php?id=' . urlencode((string) ($visitor['id'] ?? ''))) ?>">View</a></td>
                                </tr>
                            <?php endforeach; endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

                <div class="security-card">
                    <div class="security-card-header">
                        <div><span class="security-kicker">Incident watch</span><h2>Recent incidents</h2><p>Open and recently reported security incidents.</p></div>
                        <a class="btn btn-outline-danger btn-sm" href="<?= url('views/security/incidents/incidents.php') ?>">Open incidents</a>
                    </div>
                    <div class="security-list">
                        <?php if (!$incidents): ?>
                            <div class="security-empty"><i class="bi bi-shield-check"></i><p>No incidents recorded.</p></div>
                        <?php else: foreach (array_slice($incidents, 0, 6) as $incident): ?>
                            <a href="<?= url('views/security/incidents/view.php?id=' . urlencode((string) ($incident['id'] ?? ''))) ?>">
                                <span><i class="bi bi-flag"></i><?= e($incident['title'] ?? 'Incident') ?></span>
                                <strong><?= e(ucfirst((string) ($incident['status'] ?? 'open'))) ?></strong>
                            </a>
                        <?php endforeach; endif; ?>
                    </div>
                </div>
            </div>

            <div class="col-xl-4">
                <aside class="security-side-card mb-4">
                    <span class="security-kicker">Quick operations</span>
                    <h2>Gate actions</h2>
                    <div class="d-grid gap-2">
                        <a class="btn btn-outline-success" href="<?= url('views/security/visitor-check-in/visitor-check-in.php') ?>"><i class="bi bi-box-arrow-in-right me-1"></i>Check in visitor</a>
                        <a class="btn btn-outline-warning" href="<?= url('views/security/visitor-check-out/visitor-check-out.php') ?>"><i class="bi bi-box-arrow-right me-1"></i>Check out visitor</a>
                        <a class="btn btn-outline-info" href="<?= url('views/security/visitors/overstay-alerts.php') ?>"><i class="bi bi-clock-history me-1"></i>Overstay alerts</a>
                        <a class="btn btn-secondary" href="<?= url('views/security/reports/index.php') ?>"><i class="bi bi-bar-chart me-1"></i>Reports</a>
                    </div>
                </aside>

                <aside class="security-side-card">
                    <span class="security-kicker">Status summary</span>
                    <h2>Security pulse</h2>
                    <div class="security-info-list">
                        <div><i class="bi bi-person-check text-success"></i><span>Inside now</span><strong><?= e((string) count($insideVisitors)) ?></strong></div>
                        <div><i class="bi bi-hourglass text-warning"></i><span>Pending approval</span><strong><?= e((string) count($pendingVisitors)) ?></strong></div>
                        <div><i class="bi bi-calendar-day text-info"></i><span>Today movement</span><strong><?= e((string) count($todayVisitors)) ?></strong></div>
                        <div><i class="bi bi-exclamation-triangle text-danger"></i><span>Open incidents</span><strong><?= e((string) count($openIncidents)) ?></strong></div>
                    </div>
                </aside>
            </div>
        </div>
    </div>
</div>
<?php require APP_ROOT . '/app/views/components/footer.php'; ?>
