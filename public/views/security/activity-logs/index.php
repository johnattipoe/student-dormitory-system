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

$pageTitle = 'Security Activity Logs & Gate Audit Trail';

$firebase = FirebaseService::getInstance();
$allLogs = $firebase->getCollection(COL_ACTIVITY_LOGS, [], 500);
$visitorLogs = $firebase->getCollection('visitors', [], 500);

$securityLogs = [];
foreach ($allLogs as $l) {
    $action = strtolower((string)($l['event'] ?? $l['action'] ?? $l['type'] ?? ''));
    if (str_contains($action, 'security') || str_contains($action, 'gate') || str_contains($action, 'visitor') || ($l['role'] ?? '') === ROLE_SECURITY || ($l['performedByRole'] ?? '') === ROLE_SECURITY) {
        $securityLogs[] = $l;
    }
}

foreach ($visitorLogs as $v) {
    $securityLogs[] = [
        'id' => $v['id'] ?? null,
        'event' => 'Visitor Check-' . ($v['status'] === 'checked_out' ? 'Out' : 'In'),
        'action' => 'Gate Access',
        'type' => 'visitor_access',
        'details' => ($v['visitorName'] ?? $v['name'] ?? 'Visitor') . ' visited ' . ($v['studentName'] ?? 'Student') . ' (' . ($v['purpose'] ?? 'General Visit') . ')',
        'performedByName' => $v['checkedInByName'] ?? $v['securityOfficer'] ?? 'Gate Security',
        'priority' => 'normal',
        'timestamp' => $v['checkInTime'] ?? $v['createdAt'] ?? date(DATE_ATOM),
        'ip' => '127.0.0.1',
    ];
}

usort($securityLogs, function ($a, $b) {
    $tA = strtotime((string) ($a['timestamp'] ?? $a['createdAt'] ?? ''));
    $tB = strtotime((string) ($b['timestamp'] ?? $b['createdAt'] ?? ''));
    return $tB <=> $tA;
});

$search = strtolower(sanitize($_GET['search'] ?? ''));
$categoryFilter = sanitize($_GET['category'] ?? '');
$fromDate = sanitize($_GET['from_date'] ?? '');
$toDate = sanitize($_GET['to_date'] ?? '');

$logs = array_values(array_filter($securityLogs, function ($log) use ($search, $categoryFilter, $fromDate, $toDate) {
    $event = strtolower((string) ($log['event'] ?? $log['action'] ?? ''));
    $details = strtolower((string) ($log['details'] ?? $log['description'] ?? ''));
    $officer = strtolower((string) ($log['performedByName'] ?? $log['userName'] ?? ''));

    if ($categoryFilter !== '') {
        if (!str_contains($event, $categoryFilter) && !str_contains($details, $categoryFilter)) {
            return false;
        }
    }

    $rawTime = (string) ($log['timestamp'] ?? $log['createdAt'] ?? '');
    $logDate = substr($rawTime, 0, 10);
    if ($fromDate !== '' && $logDate < $fromDate) return false;
    if ($toDate !== '' && $logDate > $toDate) return false;

    if ($search !== '') {
        return str_contains($event, $search) || str_contains($details, $search) || str_contains($officer, $search);
    }
    return true;
}));

$totalCount = count($securityLogs);
$todayDate = date('Y-m-d');
$todayCount = count(array_filter($securityLogs, fn($l) => str_starts_with((string)($l['timestamp'] ?? $l['createdAt'] ?? ''), $todayDate)));
$manualCount = count(array_filter($securityLogs, fn($l) => !empty($l['isManual'])));

$navItems = [
    ['icon' => 'bi-speedometer2', 'label' => 'Dashboard', 'href' => url('views/security/dashboard/dashboard.php')],
    ['icon' => 'bi-person-check', 'label' => 'Visitors', 'href' => url('views/security/visitors/visitors/visitors.php')],
    ['icon' => 'bi-shield-exclamation', 'label' => 'Incidents', 'href' => url('views/security/incidents/incidents/incidents.php')],
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
                <h5 class="mb-1">Campus Security & Gate Activity Logs</h5>
                <p class="text-muted mb-0">Audit trail of gate entries, visitor access, perimeter patrols, and security events.</p>
            </div>
            <div class="d-flex gap-2 flex-wrap">
                <a class="btn btn-primary btn-sm" href="<?= url('views/security/activity-logs/create/create.php') ?>">
                    <i class="bi bi-plus-circle me-1"></i> Log Gate Observation
                </a>
                <a class="btn btn-outline-secondary btn-sm" href="<?= url('views/security/activity-logs/export/export.php') ?>">
                    <i class="bi bi-download me-1"></i> Export CSV
                </a>
            </div>
        </div>

        <!-- KPI Metrics -->
        <div class="row g-3 mb-4">
            <div class="col-sm-6 col-lg-4">
                <div class="card stat-card p-3 h-100 border-start border-4 border-primary">
                    <small class="text-muted">Total Security Log Records</small>
                    <strong class="fs-2 text-primary my-1"><?= e((string) $totalCount) ?></strong>
                    <span class="small text-muted">Gate & access operations</span>
                </div>
            </div>
            <div class="col-sm-6 col-lg-4">
                <div class="card stat-card p-3 h-100 border-start border-4 border-success">
                    <small class="text-muted">Gate Events Today</small>
                    <strong class="fs-2 text-success my-1"><?= e((string) $todayCount) ?></strong>
                    <span class="small text-muted"><?= e(date('F d, Y')) ?></span>
                </div>
            </div>
            <div class="col-sm-6 col-lg-4">
                <div class="card stat-card p-3 h-100 border-start border-4 border-warning">
                    <small class="text-muted">Officer Patrol Notes</small>
                    <strong class="fs-2 text-warning my-1"><?= e((string) $manualCount) ?></strong>
                    <span class="small text-muted">Field observations</span>
                </div>
            </div>
        </div>

        <!-- Filters Form -->
        <div class="card stat-card p-3 mb-4">
            <form method="GET" class="row g-2">
                <div class="col-md-4">
                    <label class="form-label small text-muted mb-1">Search Keywords</label>
                    <input name="search" class="form-control form-control-sm" placeholder="Search visitor, officer, or details..." value="<?= e($search) ?>">
                </div>
                <div class="col-md-3">
                    <label class="form-label small text-muted mb-1">Category</label>
                    <select name="category" class="form-select form-select-sm">
                        <option value="">All Categories</option>
                        <option value="visitor" <?= $categoryFilter === 'visitor' ? 'selected' : '' ?>>Visitor Check-in/Out</option>
                        <option value="patrol" <?= $categoryFilter === 'patrol' ? 'selected' : '' ?>>Perimeter Patrol</option>
                        <option value="vehicle" <?= $categoryFilter === 'vehicle' ? 'selected' : '' ?>>Vehicle Entry</option>
                        <option value="incident" <?= $categoryFilter === 'incident' ? 'selected' : '' ?>>Security Incidents</option>
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label small text-muted mb-1">From Date</label>
                    <input type="date" name="from_date" class="form-control form-control-sm" value="<?= e($fromDate) ?>">
                </div>
                <div class="col-md-2">
                    <label class="form-label small text-muted mb-1">To Date</label>
                    <input type="date" name="to_date" class="form-control form-control-sm" value="<?= e($toDate) ?>">
                </div>
                <div class="col-md-1 d-flex align-items-end gap-1">
                    <button class="btn btn-primary btn-sm flex-fill" title="Filter"><i class="bi bi-funnel"></i></button>
                    <a class="btn btn-outline-secondary btn-sm" href="<?= url('views/security/activity-logs/index.php') ?>" title="Reset"><i class="bi bi-arrow-counterclockwise"></i></a>
                </div>
            </form>
        </div>

        <!-- Table -->
        <div class="card stat-card p-3">
            <div class="table-responsive">
                <table class="table table-hover data-table w-100 align-middle">
                    <thead>
                        <tr>
                            <th>Timestamp</th>
                            <th>Duty Officer</th>
                            <th>Event / Access</th>
                            <th>Details & Log Summary</th>
                            <th class="text-end">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!empty($logs)): ?>
                            <?php foreach ($logs as $log): ?>
                                <?php
                                    $rawTime = (string) ($log['timestamp'] ?? $log['createdAt'] ?? '');
                                    $formattedTime = $rawTime !== '' ? (date('M d, Y H:i', strtotime($rawTime)) ?: $rawTime) : '—';
                                    $officer = (string) ($log['performedByName'] ?? $log['userName'] ?? 'Security Officer');
                                    $event = (string) ($log['event'] ?? $log['action'] ?? 'Gate Activity');
                                    $details = (string) ($log['details'] ?? $log['description'] ?? '—');
                                    $logId = (string) ($log['id'] ?? '');

                                    $eventLower = strtolower($event);
                                    $badgeColor = match(true) {
                                        str_contains($eventLower, 'incident') || str_contains($eventLower, 'alert') => 'danger',
                                        str_contains($eventLower, 'out') || str_contains($eventLower, 'patrol') => 'warning',
                                        default => 'success'
                                    };
                                ?>
                                <tr>
                                    <td class="text-nowrap small text-muted"><i class="bi bi-clock me-1"></i><?= e($formattedTime) ?></td>
                                    <td><strong><?= e($officer) ?></strong></td>
                                    <td>
                                        <span class="badge bg-<?= e($badgeColor) ?>-subtle text-<?= e($badgeColor) ?> border">
                                            <?= e(ucwords(str_replace(['_', '-'], ' ', $event))) ?>
                                        </span>
                                    </td>
                                    <td><?= e(mb_strimwidth($details, 0, 75, '...')) ?></td>
                                    <td class="text-end">
                                        <?php if ($logId !== ''): ?>
                                            <a class="btn btn-sm btn-outline-secondary" href="<?= url('views/security/activity-logs/view/view.php?id=' . urlencode($logId)) ?>">
                                                <i class="bi bi-eye"></i> View
                                            </a>
                                        <?php else: ?>
                                            <span class="text-muted small">—</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="5" class="text-center text-muted py-4">No security logs recorded.</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
<?php require APP_ROOT . '/app/views/components/footer/footer.php'; ?>

