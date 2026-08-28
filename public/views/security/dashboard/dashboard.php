<?php
if (!defined('APP_ROOT')) {
    $dir = __DIR__;
    for ($i = 0; $i < 10; $i++) {
        if (file_exists($dir . '/bootstrap.php')) { require $dir . '/bootstrap.php'; break; }
        $parent = dirname($dir);
        if ($parent === $dir) break;
        $dir = $parent;
    }
}
$allowedRoles = [ROLE_SECURITY];
require APP_ROOT . '/app/middleware/RoleMiddleware/RoleMiddleware.php';

use App\Services\IncidentService;
use App\Services\VisitorService;
use App\Services\FirebaseService;

$user = current_user() ?? [];
$officerName = trim(($user['name'] ?? '') ?: (($user['firstName'] ?? '') . ' ' . ($user['lastName'] ?? ''))) ?: 'Security Officer';
$guardPost   = (string)($user['guardPost'] ?? $user['station'] ?? 'Main Gate');

$visitorService = new VisitorService();
$incidentService = new IncidentService();
$visitors  = $visitorService->all();
$incidents = $incidentService->all();

$insideVisitors  = array_values(array_filter($visitors, static fn($v) => ($v['status'] ?? '') === 'inside'));
$pendingVisitors = array_values(array_filter($visitors, static fn($v) => ($v['status'] ?? '') === 'pending'));
$todayVisitors   = array_values(array_filter($visitors, static function($v) {
    $val = (string)($v['checkInTime'] ?? $v['visitDate'] ?? $v['createdAt'] ?? '');
    return str_starts_with($val, date('Y-m-d'));
}));
$openIncidents   = array_values(array_filter($incidents, static fn($i) => ($i['status'] ?? 'open') === 'open'));
$urgentIncidents = array_values(array_filter($incidents, static fn($i) => in_array(strtolower((string)($i['priority'] ?? '')), ['high', 'critical', 'urgent'], true)));

usort($visitors,  static fn($a, $b) => strcmp((string)($b['checkInTime'] ?? $b['createdAt'] ?? ''), (string)($a['checkInTime'] ?? $a['createdAt'] ?? '')));
usort($incidents, static fn($a, $b) => strcmp((string)($b['reportedAt'] ?? $b['createdAt'] ?? ''), (string)($a['reportedAt'] ?? $a['createdAt'] ?? '')));

$recentVisitors  = array_slice($visitors, 0, 6);
$recentIncidents = array_slice($incidents, 0, 5);

// Bulletins
$firebase = FirebaseService::getInstance();
$allAnn   = $firebase->getCollection('announcements', [], 50);
$secAnn   = array_values(array_filter($allAnn, fn($a) => ($a['status'] ?? 'published') === 'published' && in_array($a['audience'] ?? 'all', ['all', 'role'])));
usort($secAnn, fn($a, $b) => strcmp((string)($b['createdAt'] ?? ''), (string)($a['createdAt'] ?? '')));
$topAnn = array_slice($secAnn, 0, 3);

$pageTitle  = 'Security Dashboard';
$pageStyles = ['security.css'];
$navItems = [
    ['icon' => 'bi-speedometer2',       'label' => 'Dashboard',       'href' => url('views/security/dashboard/dashboard.php'), 'active' => true],
    ['icon' => 'bi-people',             'label' => 'Visitors',        'href' => url('views/security/visitors/visitors/visitors.php')],
    ['icon' => 'bi-journal-text',       'label' => 'Visitor History', 'href' => url('views/security/visitor-history/visitor-history.php')],
    ['icon' => 'bi-exclamation-triangle','label' => 'Incidents',      'href' => url('views/security/incidents/incidents/incidents.php')],
    ['icon' => 'bi-bar-chart',          'label' => 'Reports',         'href' => url('views/security/reports/index.php')],
    ['icon' => 'bi-bell',               'label' => 'Notifications',   'href' => url('views/security/notifications/notifications.php')],
];

require APP_ROOT . '/app/views/components/header/header.php';
require APP_ROOT . '/app/views/components/sidebar/sidebar.php';
?>
<div class="main-content">
    <?php require APP_ROOT . '/app/views/components/navbar/navbar.php'; ?>
    <?php require APP_ROOT . '/app/views/components/alerts/alerts.php'; ?>
    <div class="content-wrapper">

        <!-- Welcome Hero -->
        <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-3">
            <div>
                <h4 class="mb-1 fw-bold text-dark">
                    <i class="bi bi-shield-lock-fill text-dark me-2"></i>Welcome, <?= e($officerName) ?>
                </h4>
                <p class="text-muted mb-0">
                    Gate &amp; Campus Security Control &bull; Post: <strong><?= e($guardPost) ?></strong> &bull; <?= e(date('l, F j, Y')) ?>
                </p>
            </div>
            <div class="d-flex gap-2 flex-wrap">
                <a href="<?= url('views/security/register-visitor/register-visitor.php') ?>" class="btn btn-primary btn-sm">
                    <i class="bi bi-person-plus me-1"></i> Register Visitor
                </a>
                <a href="<?= url('views/security/report-incident/report-incident.php') ?>" class="btn btn-outline-danger btn-sm">
                    <i class="bi bi-flag me-1"></i> Report Incident
                </a>
                <a href="<?= url('views/security/emergency-alerts/index.php') ?>" class="btn btn-outline-secondary btn-sm">
                    <i class="bi bi-telephone-inbound me-1"></i> Alert Desk
                </a>
            </div>
        </div>

        <!-- Primary KPI Cards -->
        <div class="row g-3 mb-4">
            <div class="col-sm-6 col-lg-3">
                <div class="card stat-card h-100 p-3 border-start border-4 border-primary shadow-sm">
                    <div class="d-flex justify-content-between align-items-start">
                        <div>
                            <span class="text-muted small text-uppercase fw-semibold">Total Visitors</span>
                            <h3 class="fw-bold my-1 text-primary"><?= e((string) count($visitors)) ?></h3>
                            <span class="small text-muted"><?= count($todayVisitors) ?> registered today</span>
                        </div>
                        <div class="rounded-3 bg-primary bg-opacity-10 p-2 text-primary"><i class="bi bi-people fs-4"></i></div>
                    </div>
                </div>
            </div>

            <div class="col-sm-6 col-lg-3">
                <div class="card stat-card h-100 p-3 border-start border-4 border-success shadow-sm">
                    <div class="d-flex justify-content-between align-items-start">
                        <div>
                            <span class="text-muted small text-uppercase fw-semibold">Currently Inside</span>
                            <h3 class="fw-bold my-1 text-success"><?= e((string) count($insideVisitors)) ?></h3>
                            <span class="small text-muted"><?= count($pendingVisitors) ?> pending entry</span>
                        </div>
                        <div class="rounded-3 bg-success bg-opacity-10 p-2 text-success"><i class="bi bi-door-open fs-4"></i></div>
                    </div>
                </div>
            </div>

            <div class="col-sm-6 col-lg-3">
                <div class="card stat-card h-100 p-3 border-start border-4 border-<?= count($openIncidents) > 0 ? 'danger' : 'secondary' ?> shadow-sm">
                    <div class="d-flex justify-content-between align-items-start">
                        <div>
                            <span class="text-muted small text-uppercase fw-semibold">Open Incidents</span>
                            <h3 class="fw-bold my-1 text-<?= count($openIncidents) > 0 ? 'danger' : 'dark' ?>"><?= e((string) count($openIncidents)) ?></h3>
                            <span class="small text-muted"><?= count($incidents) ?> total logged</span>
                        </div>
                        <div class="rounded-3 bg-danger bg-opacity-10 p-2 text-danger"><i class="bi bi-exclamation-triangle fs-4"></i></div>
                    </div>
                </div>
            </div>

            <div class="col-sm-6 col-lg-3">
                <div class="card stat-card h-100 p-3 border-start border-4 border-<?= count($urgentIncidents) > 0 ? 'warning' : 'secondary' ?> shadow-sm">
                    <div class="d-flex justify-content-between align-items-start">
                        <div>
                            <span class="text-muted small text-uppercase fw-semibold">Urgent Incidents</span>
                            <h3 class="fw-bold my-1 text-<?= count($urgentIncidents) > 0 ? 'warning' : 'dark' ?>"><?= e((string) count($urgentIncidents)) ?></h3>
                            <span class="small text-muted">High / Critical priority</span>
                        </div>
                        <div class="rounded-3 bg-warning bg-opacity-10 p-2 text-warning"><i class="bi bi-exclamation-octagon fs-4"></i></div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Secondary Analytics Strip -->
        <div class="row g-3 mb-4">
            <div class="col-sm-6 col-md-3">
                <div class="card stat-card p-3 h-100 shadow-sm">
                    <small class="text-muted fw-bold">TODAY'S VISITORS</small>
                    <div class="fs-4 fw-bold text-primary mt-1"><?= count($todayVisitors) ?></div>
                    <small class="text-muted">Campus gate entries</small>
                </div>
            </div>
            <div class="col-sm-6 col-md-3">
                <div class="card stat-card p-3 h-100 shadow-sm">
                    <small class="text-muted fw-bold">AWAITING CLEARANCE</small>
                    <div class="fs-4 fw-bold text-warning mt-1"><?= count($pendingVisitors) ?></div>
                    <small class="text-muted">Pending check-in approval</small>
                </div>
            </div>
            <div class="col-sm-6 col-md-3">
                <div class="card stat-card p-3 h-100 shadow-sm">
                    <small class="text-muted fw-bold">GUARD POST</small>
                    <div class="fs-4 fw-bold text-dark mt-1"><?= e($guardPost) ?></div>
                    <small class="text-muted">Active station</small>
                </div>
            </div>
            <div class="col-sm-6 col-md-3">
                <div class="card stat-card p-3 h-100 shadow-sm">
                    <small class="text-muted fw-bold">CAMPUS STATUS</small>
                    <div class="fs-4 fw-bold text-success mt-1">Secure</div>
                    <small class="text-muted">All clear — routine watch</small>
                </div>
            </div>
        </div>

        <!-- Main Workspace -->
        <div class="row g-4 mb-4">

            <!-- Left: Recent Visitors + Incidents -->
            <div class="col-lg-8">

                <!-- Visitor Log -->
                <div class="card stat-card shadow-sm mb-4">
                    <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center flex-wrap gap-2">
                        <div>
                            <h6 class="mb-0 fw-bold"><i class="bi bi-people me-2 text-primary"></i>Recent Visitor Activity</h6>
                            <small class="text-muted">Live gate registration and movement status</small>
                        </div>
                        <a href="<?= url('views/security/visitors/visitors/visitors.php') ?>" class="btn btn-outline-secondary btn-sm">
                            Full Log <i class="bi bi-arrow-right ms-1"></i>
                        </a>
                    </div>
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-hover align-middle mb-0">
                                <thead class="table-light">
                                    <tr>
                                        <th>Visitor</th>
                                        <th>Student</th>
                                        <th>Purpose</th>
                                        <th>Status</th>
                                        <th class="text-end">Action</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (empty($recentVisitors)): ?>
                                        <tr><td colspan="5" class="text-center text-muted py-4">
                                            <i class="bi bi-person-x fs-3 d-block text-secondary mb-1"></i>
                                            No visitors registered yet.
                                        </td></tr>
                                    <?php else: ?>
                                        <?php foreach ($recentVisitors as $v): ?>
                                            <?php
                                            $vStatus = strtolower((string)($v['status'] ?? 'pending'));
                                            $vBadge  = match($vStatus) { 'inside' => 'bg-success', 'pending' => 'bg-warning text-dark', 'outside', 'left' => 'bg-secondary', default => 'bg-primary' };
                                            $vId = (string)($v['id'] ?? '');
                                            ?>
                                            <tr>
                                                <td>
                                                    <strong><?= e($v['visitorName'] ?? $v['name'] ?? 'Visitor') ?></strong>
                                                    <small class="text-muted d-block"><?= e($v['phone'] ?? '') ?></small>
                                                </td>
                                                <td><?= e($v['studentId'] ?? '—') ?></td>
                                                <td><small class="text-muted"><?= e(mb_strimwidth((string)($v['purpose'] ?? ''), 0, 25, '…')) ?></small></td>
                                                <td><span class="badge <?= $vBadge ?>"><?= ucfirst(e($vStatus)) ?></span></td>
                                                <td class="text-end">
                                                    <?php if ($vId !== ''): ?>
                                                        <a class="btn btn-sm btn-outline-primary" href="<?= url('views/security/visitors/view/view.php?id=' . urlencode($vId)) ?>">View</a>
                                                    <?php endif; ?>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <!-- Recent Incidents -->
                <div class="card stat-card shadow-sm">
                    <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center flex-wrap gap-2">
                        <div>
                            <h6 class="mb-0 fw-bold"><i class="bi bi-exclamation-triangle me-2 text-danger"></i>Recent Incidents</h6>
                            <small class="text-muted">Gate breaches, overstay alerts, and campus incidents</small>
                        </div>
                        <a href="<?= url('views/security/incidents/incidents/incidents.php') ?>" class="btn btn-outline-secondary btn-sm">
                            All Incidents <i class="bi bi-arrow-right ms-1"></i>
                        </a>
                    </div>
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-hover align-middle mb-0">
                                <thead class="table-light">
                                    <tr><th>Incident</th><th>Priority</th><th>Status</th></tr>
                                </thead>
                                <tbody>
                                    <?php if (empty($recentIncidents)): ?>
                                        <tr><td colspan="3" class="text-center text-muted py-4">
                                            <i class="bi bi-shield-check fs-3 d-block text-secondary mb-1"></i>No incidents recorded.
                                        </td></tr>
                                    <?php else: ?>
                                        <?php foreach ($recentIncidents as $inc): ?>
                                            <?php
                                            $iSt = strtolower((string)($inc['status'] ?? 'open'));
                                            $iPr = strtolower((string)($inc['priority'] ?? 'low'));
                                            $sBadge = match($iSt) { 'resolved' => 'bg-success', 'closed' => 'bg-secondary', default => 'bg-danger' };
                                            $pBadge = match($iPr) { 'high', 'critical', 'urgent' => 'bg-danger', 'medium' => 'bg-warning text-dark', default => 'bg-secondary' };
                                            ?>
                                            <tr>
                                                <td>
                                                    <strong class="d-block"><?= e($inc['title'] ?? 'Incident') ?></strong>
                                                    <small class="text-muted"><?= e(substr((string)($inc['reportedAt'] ?? $inc['createdAt'] ?? ''), 0, 10)) ?></small>
                                                </td>
                                                <td><span class="badge <?= $pBadge ?>"><?= ucfirst(e($iPr)) ?></span></td>
                                                <td><span class="badge <?= $sBadge ?>"><?= ucfirst(e($iSt)) ?></span></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

            </div>

            <!-- Right: Quick Actions + Bulletins -->
            <div class="col-lg-4">

                <!-- Gate Control Actions -->
                <div class="card stat-card shadow-sm mb-4">
                    <div class="card-header bg-white py-3">
                        <h6 class="mb-0 fw-bold"><i class="bi bi-grid me-2 text-primary"></i>Gate Control Actions</h6>
                    </div>
                    <div class="card-body p-3">
                        <div class="row g-2">
                            <div class="col-6">
                                <a href="<?= url('views/security/register-visitor/register-visitor.php') ?>" class="btn btn-outline-primary w-100 p-3 d-flex flex-column align-items-center">
                                    <i class="bi bi-person-plus fs-3 mb-1"></i>
                                    <span class="small fw-bold">Register Visitor</span>
                                </a>
                            </div>
                            <div class="col-6">
                                <a href="<?= url('views/security/report-incident/report-incident.php') ?>" class="btn btn-outline-danger w-100 p-3 d-flex flex-column align-items-center">
                                    <i class="bi bi-flag fs-3 mb-1"></i>
                                    <span class="small fw-bold">Report Incident</span>
                                </a>
                            </div>
                            <div class="col-6">
                                <a href="<?= url('views/security/visitors/visitors/visitors.php') ?>" class="btn btn-outline-success w-100 p-3 d-flex flex-column align-items-center">
                                    <i class="bi bi-people fs-3 mb-1"></i>
                                    <span class="small fw-bold">Visitor Log</span>
                                </a>
                            </div>
                            <div class="col-6">
                                <a href="<?= url('views/security/visitor-history/visitor-history.php') ?>" class="btn btn-outline-info w-100 p-3 d-flex flex-column align-items-center">
                                    <i class="bi bi-journal-text fs-3 mb-1"></i>
                                    <span class="small fw-bold">History</span>
                                </a>
                            </div>
                            <div class="col-6">
                                <a href="<?= url('views/security/emergency-alerts/index.php') ?>" class="btn btn-outline-warning w-100 p-3 d-flex flex-column align-items-center">
                                    <i class="bi bi-telephone-inbound fs-3 mb-1"></i>
                                    <span class="small fw-bold">Alert Desk</span>
                                </a>
                            </div>
                            <div class="col-6">
                                <a href="<?= url('views/security/reports/index.php') ?>" class="btn btn-outline-dark w-100 p-3 d-flex flex-column align-items-center">
                                    <i class="bi bi-bar-chart fs-3 mb-1"></i>
                                    <span class="small fw-bold">Reports</span>
                                </a>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Bulletins -->
                <div class="card stat-card shadow-sm">
                    <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
                        <h6 class="mb-0 fw-bold"><i class="bi bi-megaphone me-2 text-warning"></i>Security Bulletins</h6>
                    </div>
                    <div class="card-body p-3">
                        <?php if (empty($topAnn)): ?>
                            <p class="text-muted small text-center my-2">No bulletins posted.</p>
                        <?php else: ?>
                            <div class="d-flex flex-column gap-2">
                                <?php foreach ($topAnn as $ann): ?>
                                    <div class="p-2 rounded-3 bg-light border">
                                        <?php if (!empty($ann['isUrgent'])): ?>
                                            <span class="badge bg-danger mb-1">Urgent</span>
                                        <?php endif; ?>
                                        <div class="fw-bold small text-dark"><?= e($ann['title'] ?? 'Notice') ?></div>
                                        <p class="text-muted small mb-0"><?= e(mb_strimwidth((string)($ann['message'] ?? ''), 0, 70, '…')) ?></p>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

            </div>
        </div>

    </div>
</div>
<?php require APP_ROOT . '/app/views/components/footer/footer.php'; ?>
